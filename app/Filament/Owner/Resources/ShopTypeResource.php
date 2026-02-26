<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\ShopTypeResource\Pages;
use App\Filament\Owner\Resources\ShopTypeResource\RelationManagers;
use App\Models\ShopType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ShopTypeResource extends Resource
{
    protected static ?string $model = ShopType::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Shop Types';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessOwnerTab('shop_type') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation !== 'create' || blank($state)) {
                            return;
                        }
                        $set('slug', Str::slug($state));
                    }),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->readOnly(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->inline(false)
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('fields_count')
                    ->label('Attribute fields')
                    ->counts('fields')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopTypes::route('/'),
            'create' => Pages\CreateShopType::route('/create'),
            'edit' => Pages\EditShopType::route('/{record}/edit'),
        ];
    }
}
