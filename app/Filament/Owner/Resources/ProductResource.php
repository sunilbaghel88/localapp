<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\ProductResource\Pages;
use App\Filament\Owner\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('shop_id')
                    ->label('Shop')
                    ->relationship(
                        name: 'shop',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                    )
                    ->required()
                    ->preload()
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('brand')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->default('draft'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->label('Shop')->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'published',
                        'warning' => 'archived',
                    ]),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('shop_id')
                    ->label('Shop')
                    ->relationship('shop', 'name', fn (Builder $query) => $query->where('user_id', auth()->id()))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('update_status')
                        ->label('Update Status')
                        ->icon('heroicon-m-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'draft' => 'Draft',
                                    'published' => 'Published',
                                    'archived' => 'Archived',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Status updated for ' . $records->count() . ' product(s)')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'published']);
                            \Filament\Notifications\Notification::make()
                                ->title('Published ' . $records->count() . ' product(s)')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-m-archive-box')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each->update(['status' => 'archived']);
                            \Filament\Notifications\Notification::make()
                                ->title('Archived ' . $records->count() . ' product(s)')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('shop', fn ($q) => $q->where('user_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
