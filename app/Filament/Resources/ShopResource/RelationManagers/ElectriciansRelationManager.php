<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ElectriciansRelationManager extends RelationManager
{
    protected static string $relationship = 'electricians';

    protected static ?string $title = 'Electricians';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        $shopType = $ownerRecord->shopType;
        if (! $shopType) {
            return false;
        }

        return $shopType->supports_electrician_rewards && $shopType->electrician_user_type_id !== null;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $ownerRecord = $this->getOwnerRecord();
        $electricianUserTypeId = $ownerRecord->shopType?->electrician_user_type_id;

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('reward_points')
                    ->label('Reward points')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordSelectOptionsQuery(function (Builder $query) use ($electricianUserTypeId) {
                        if (! $electricianUserTypeId) {
                            return $query->whereRaw('1 = 0');
                        }
                        return $query->where('user_type_id', $electricianUserTypeId)
                            ->where('is_active', true)
                            ->orderBy('first_name')
                            ->orderBy('last_name');
                    })
                    ->recordTitle(fn (User $record): string => $record->name . ($record->phone ? ' (' . $record->phone . ')' : ''))
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['first_name', 'last_name', 'email', 'phone']),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
