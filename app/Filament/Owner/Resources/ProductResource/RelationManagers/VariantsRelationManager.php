<?php

namespace App\Filament\Owner\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Variant Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->prefix('₹'),
                Forms\Components\TextInput::make('compare_at_price')
                    ->label('Compare at Price')
                    ->numeric()
                    ->prefix('₹'),
                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\KeyValue::make('attributes')
                    ->keyLabel('Attribute')
                    ->valueLabel('Value')
                    ->addButtonLabel('Add attribute'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Variant Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('inr', divideBy: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('compare_at_price')
                    ->label('Compare Price')
                    ->money('inr', divideBy: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->sortable()
                    ->color(fn ($record) => $record->stock < 10 ? 'danger' : 'success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_stock')
                        ->label('Update Stock')
                        ->icon('heroicon-m-archive-box')
                        ->form([
                            Forms\Components\TextInput::make('stock')
                                ->label('New Stock Quantity')
                                ->numeric()
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['stock' => $data['stock']]);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Stock updated for ' . $records->count() . ' variant(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-m-check-circle')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                            \Filament\Notifications\Notification::make()
                                ->title('Activated ' . $records->count() . ' variant(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-m-x-circle')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                            \Filament\Notifications\Notification::make()
                                ->title('Deactivated ' . $records->count() . ' variant(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sku');
    }
}
