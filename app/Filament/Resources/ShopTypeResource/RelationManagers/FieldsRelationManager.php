<?php

namespace App\Filament\Resources\ShopTypeResource\RelationManagers;

use App\Models\ShopTypeField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Attribute fields';

    protected static ?string $recordTitleAttribute = 'label';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Field label (display name)')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (blank($state)) {
                            return;
                        }
                        $set('key', Str::slug($state));
                    }),
                Forms\Components\TextInput::make('key')
                    ->label('Attribute key (used in variants)')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Lowercase, no spaces. Used as the attribute key in product variants.'),
                Forms\Components\Select::make('field_type')
                    ->options([
                        'text' => 'Text',
                        'number' => 'Number',
                        'select' => 'Select (from options)',
                    ])
                    ->default('text')
                    ->required()
                    ->live(),
                Forms\Components\KeyValue::make('options')
                    ->label('Options (for Select type)')
                    ->keyLabel('Value')
                    ->valueLabel('Label')
                    ->addButtonLabel('Add option')
                    ->visible(fn (Forms\Get $get): bool => $get('field_type') === 'select'),
                Forms\Components\Toggle::make('is_required')
                    ->label('Required')
                    ->default(false),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\TextColumn::make('field_type')->badge(),
                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}

