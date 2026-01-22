<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\ProductVariantResource\Pages;
use App\Filament\Owner\Resources\ProductVariantResource\RelationManagers;
use App\Models\ProductVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship(
                        name: 'product',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereHas('shop', fn ($q) => $q->where('user_id', auth()->id())),
                    )
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('sku')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->required()
                    ->prefix('₹'),
                Forms\Components\TextInput::make('compare_at_price')
                    ->numeric()
                    ->prefix('₹'),
                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->required(),
                Forms\Components\KeyValue::make('attributes')
                    ->keyLabel('Attribute')
                    ->valueLabel('Value')
                    ->addButtonLabel('Add attribute'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('sku')->searchable(),
                Tables\Columns\TextColumn::make('price')->money('inr', divideBy: 1),
                Tables\Columns\TextColumn::make('stock')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariants::route('/'),
            'create' => Pages\CreateProductVariant::route('/create'),
            'edit' => Pages\EditProductVariant::route('/{record}/edit'),
        ];
    }
}
