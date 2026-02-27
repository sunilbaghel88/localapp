<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Brand;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
                    ->searchable()
                    ->live(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                        if ($operation !== 'create' || blank($state)) {
                            return;
                        }

                        $baseSlug = Str::slug($state);
                        $slug = $baseSlug;
                        $counter = 2;

                        while (Product::where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $counter;
                            $counter++;
                        }

                        $set('slug', $slug);
                    }),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->default('draft'),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->readOnly(),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->relationship(
                        name: 'brand',
                        titleAttribute: 'name',
                    )
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Brand name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $user = auth()->user();

                        $brand = Brand::create([
                            'name'        => $data['name'],
                            'slug'        => \Illuminate\Support\Str::slug($data['name']),
                            'is_approved' => true,
                            'created_by'  => $user?->id,
                        ]);

                        return $brand->getKey();
                    })
                    ->helperText('If you cannot find a brand add it manually by clicking + button. New brands require approval before they appear in the storefront.'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Section::make('Shop type attributes')
                    ->description('These attributes are defined for your shop type. Fill their values in each variant (Variants tab). They will be shown on the storefront product page.')
                    ->schema([
                        Forms\Components\Placeholder::make('shop_type_attributes_info')
                            ->label('')
                            ->content(function (Forms\Get $get): string {
                                $shopId = $get('shop_id');
                                if (! $shopId) {
                                    return 'Select a shop to see its shop type attributes.';
                                }
                                $shop = \App\Models\Shop::with('shopType.fields')->find($shopId);
                                if (! $shop?->shopType) {
                                    return 'This shop has no shop type set, or the shop type has no attribute fields.';
                                }
                                $fields = $shop->shopType->fields;
                                if ($fields->isEmpty()) {
                                    return 'No attribute fields defined for this shop type. Add them in Shop Types → edit "' . e($shop->shopType->name) . '" → Attribute fields.';
                                }
                                return 'Attribute keys for variants: ' . $fields->pluck('label')->join(', ') . '.';
                            })
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('shop_id')),
                    ])
                    ->collapsible()
                    ->visible(fn (Forms\Get $get): bool => (bool) $get('shop_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Shop')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'published',
                        'warning' => 'archived',
                    ]),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
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

