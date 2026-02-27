<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #')->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->label('Shop'),
                Tables\Columns\TextColumn::make('user.name')->label('Customer'),
                Tables\Columns\BadgeColumn::make('status'),
                Tables\Columns\BadgeColumn::make('payment_status'),
                Tables\Columns\TextColumn::make('grand_total')->money('inr', divideBy: 1),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('mark_processing')
                        ->label('Mark as Processing')
                        ->icon('heroicon-m-arrow-path')
                        ->color('info')
                        ->action(function ($record) {
                            $record->update(['status' => 'processing']);
                            \Filament\Notifications\Notification::make()
                                ->title('Order marked as processing')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->status === 'pending'),
                    Tables\Actions\Action::make('mark_shipped')
                        ->label('Mark as Shipped')
                        ->icon('heroicon-m-truck')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update(['status' => 'shipped']);
                            \Filament\Notifications\Notification::make()
                                ->title('Order marked as shipped')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'processing'])),
                    Tables\Actions\Action::make('mark_delivered')
                        ->label('Mark as Delivered')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update(['status' => 'delivered']);
                            \Filament\Notifications\Notification::make()
                                ->title('Order marked as delivered')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => in_array($record->status, ['pending', 'processing', 'shipped'])),
                    Tables\Actions\Action::make('mark_cancelled')
                        ->label('Cancel Order')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(function ($record) {
                            $record->update(['status' => 'cancelled']);
                            \Filament\Notifications\Notification::make()
                                ->title('Order cancelled')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->status !== 'cancelled' && $record->status !== 'delivered')
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('mark_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-m-currency-dollar')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update(['payment_status' => 'paid']);
                            \Filament\Notifications\Notification::make()
                                ->title('Payment status updated')
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->payment_status !== 'paid'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_status')
                        ->label('Update Order Status')
                        ->icon('heroicon-m-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Order Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'processing' => 'Processing',
                                    'shipped' => 'Shipped',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Order status updated for ' . $records->count() . ' order(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('update_payment_status')
                        ->label('Update Payment Status')
                        ->icon('heroicon-m-currency-dollar')
                        ->form([
                            Forms\Components\Select::make('payment_status')
                                ->label('Payment Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'paid' => 'Paid',
                                    'failed' => 'Failed',
                                    'refunded' => 'Refunded',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['payment_status' => $data['payment_status']]);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Payment status updated for ' . $records->count() . ' order(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('mark_shipped')
                        ->label('Mark as Shipped')
                        ->icon('heroicon-m-truck')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'shipped']);
                            \Filament\Notifications\Notification::make()
                                ->title('Marked ' . $records->count() . ' order(s) as shipped')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('mark_delivered')
                        ->label('Mark as Delivered')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'delivered']);
                            \Filament\Notifications\Notification::make()
                                ->title('Marked ' . $records->count() . ' order(s) as delivered')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
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
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}

