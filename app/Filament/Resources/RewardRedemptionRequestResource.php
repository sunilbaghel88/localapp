<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RewardRedemptionRequestResource\Pages;
use App\Models\RewardRedemptionRequest;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RewardRedemptionRequestResource extends Resource
{
    protected static ?string $model = RewardRedemptionRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Reward Redemptions';

    protected static ?string $modelLabel = 'Reward Redemption';

    protected static ?string $pluralModelLabel = 'Reward Redemptions';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Electrician')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Shop')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_points')
                    ->label('Points')
                    ->sortable(),
                Tables\Columns\TextColumn::make('redemption_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Processed by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RewardRedemptionRequest $record): void {
                        $result = DB::transaction(function () use ($record) {
                            $request = RewardRedemptionRequest::query()
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $request || $request->status !== 'pending') {
                                return 'already_processed';
                            }

                            $user = $request->user()->lockForUpdate()->first();
                            if (! $user) {
                                return 'missing_user';
                            }

                            if ((int) $user->reward_points < (int) $request->requested_points) {
                                return 'insufficient_points';
                            }

                            $user->decrement('reward_points', (int) $request->requested_points);
                            $request->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'rejection_reason' => null,
                            ]);

                            return 'approved';
                        });

                        if ($result === 'approved') {
                            Notification::make()
                                ->title('Redemption approved and points deducted.')
                                ->success()
                                ->send();

                            return;
                        }

                        if ($result === 'insufficient_points') {
                            Notification::make()
                                ->title('Electrician does not have enough points anymore.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Request is already processed or unavailable.')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (RewardRedemptionRequest $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data, RewardRedemptionRequest $record): void {
                        $updated = RewardRedemptionRequest::query()
                            ->whereKey($record->id)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'rejected',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'rejection_reason' => $data['rejection_reason'],
                            ]);

                        if ($updated) {
                            Notification::make()
                                ->title('Redemption request rejected.')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Request is already processed.')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (RewardRedemptionRequest $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->whereIn('shop_id', $user->shops()->pluck('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardRedemptionRequests::route('/'),
        ];
    }
}
