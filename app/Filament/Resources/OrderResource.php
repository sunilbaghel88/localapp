<?php

namespace App\Filament\Resources;

use App\Actions\GrantOrderRewardPoints;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
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
                Forms\Components\Section::make('Order Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                            ->searchable(['first_name', 'last_name', 'email', 'phone'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('shop_id')
                            ->label('Shop')
                            ->options(fn () => auth()->user()
                                ? auth()->user()->shops()->pluck('name', 'id')
                                : [])
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('electrician_user_id', null))
                            ->required(),
                        Forms\Components\Select::make('electrician_user_id')
                            ->label('Partner')
                            ->options(function (Forms\Get $get): array {
                                $shopId = $get('shop_id');
                                if (! $shopId) {
                                    return [];
                                }

                                $shop = Shop::query()
                                    ->with(['shopType.rewardUserTypes'])
                                    ->find($shopId);

                                $rewardTypeIds = $shop?->shopType?->rewardUserTypeIds() ?? [];
                                if (! $shop || ! ($shop->shopType?->supports_partner_rewards) || $rewardTypeIds === []) {
                                    return [];
                                }

                                return $shop->partners()
                                    ->withAnyUserTypeIds($rewardTypeIds)
                                    ->where('users.is_active', true)
                                    ->orderBy('users.first_name')
                                    ->orderBy('users.last_name')
                                    ->get(['users.id', 'users.first_name', 'users.last_name'])
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->name])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Forms\Get $get): bool => blank($get('shop_id')))
                            ->helperText('Support partner on this order (electrician, plumber, etc.).')
                            ->nullable(),
                        Forms\Components\Select::make('status')
                            ->label('Order Status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Select::make('address_id')
                            ->label('Shipping Address')
                            ->relationship('address', 'address_line1')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Search & add products using AI')
                    ->description('Type naturally (or dictate) and let AI add matching products to the order lines.')
                    ->schema([
                        Forms\Components\Textarea::make('ai_prompt')
                            ->label('What do you want to add?')
                            ->rows(3)
                            ->placeholder('Add 2 Havells 5A MCB and 1 coil Finolex 1.5mm wire')
                            ->helperText('Tip: include brand + key specs for best matches.')
                            ->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('apply_ai_prompt')
                                ->label('Search & add to items')
                                ->icon('heroicon-m-magnifying-glass')
                                ->action(fn ($livewire, Forms\Get $get, Forms\Set $set) => $livewire->applyAiPrompt($get, $set)),
                        ])->columnSpanFull(),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof CreateOrder)
                    ->columnSpanFull(),
                Forms\Components\Section::make('Order items')
                    ->description('Search and add products from the selected shop. Totals are calculated automatically from these lines.')
                    ->schema([
                        Forms\Components\Repeater::make('order_items_data')
                            ->label('Products')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                                    ->getSearchResultsUsing(function (string $search, $livewire): array {
                                        $shopId = $livewire->data['shop_id'] ?? null;
                                        if (! $shopId) {
                                            return [];
                                        }

                                        return Product::query()
                                            ->where('shop_id', $shopId)
                                            ->whereRaw('LOWER(name) like ?', ['%'.strtolower($search).'%'])
                                            ->orderBy('name')
                                            ->limit(50)
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set): void {
                                        $set('product_variant_id', null);
                                        $set('brand_name', null);
                                        $set('unit_price', null);
                                        $set('line_total', null);
                                        if (! $state) {
                                            return;
                                        }
                                        $product = Product::with([
                                            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
                                            'brand',
                                        ])
                                            ->find($state);
                                        if (! $product) {
                                            return;
                                        }
                                        $set('brand_name', $product->brand?->name ?? null);
                                        $variants = $product->variants;
                                        if ($variants->count() === 1) {
                                            $variant = $variants->first();
                                            $set('product_variant_id', $variant->id);
                                            $price = (float) $variant->price;
                                            $qty = (int) ($get('quantity') ?: 1);
                                            $set('unit_price', $price);
                                            $set('line_total', $qty * $price);
                                        }
                                    }),
                                Forms\Components\Select::make('product_variant_id')
                                    ->label('Variant')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->nullable()
                                    ->options(function (Forms\Get $get): array {
                                        $productId = $get('product_id');
                                        if (! $productId) {
                                            return [];
                                        }

                                        return ProductVariant::query()
                                            ->where('product_id', $productId)
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->orderBy('sku')
                                            ->get()
                                            ->mapWithKeys(fn ($v) => [$v->id => $v->name ?: $v->sku ?: '#'.$v->id])
                                            ->all();
                                    })
                                    ->required(fn (Forms\Get $get): bool => ProductVariant::where('product_id', $get('product_id'))->where('is_active', true)->exists())
                                    ->visible(fn (Forms\Get $get): bool => ProductVariant::where('product_id', $get('product_id'))->where('is_active', true)->exists())
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set): void {
                                        $set('unit_price', null);
                                        $set('line_total', null);
                                        if (! $state) {
                                            return;
                                        }
                                        $variant = ProductVariant::find($state);
                                        if (! $variant) {
                                            return;
                                        }
                                        $price = (float) $variant->price;
                                        $qty = (int) ($get('quantity') ?: 1);
                                        $set('unit_price', $price);
                                        $set('line_total', $qty * $price);
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set): void {
                                        $variantId = $get('product_variant_id');
                                        if (! $variantId) {
                                            return;
                                        }
                                        $variant = ProductVariant::find($variantId);
                                        if (! $variant) {
                                            return;
                                        }
                                        $qty = (int) ($state ?: 1);
                                        $price = (float) $variant->price;
                                        $set('unit_price', $price);
                                        $set('line_total', $qty * $price);
                                    }),
                                Forms\Components\TextInput::make('brand_name')
                                    ->label('Brand')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('line_total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columns(6)
                            ->defaultItems(0)
                            ->addActionLabel('Add product')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof CreateOrder)
                    ->columnSpanFull(),
                Forms\Components\Section::make('Totals')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('discount_total')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('shipping_total')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('tax_total')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('grand_total')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(3)
                    ->visible(fn ($livewire) => ! ($livewire instanceof CreateOrder)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #')->sortable(),
                Tables\Columns\TextColumn::make('shop.name')->label('Shop'),
                Tables\Columns\TextColumn::make('user.name')->label('Customer'),
                Tables\Columns\TextColumn::make('electricianUser.name')
                    ->label('Partner')
                    ->placeholder('—')
                    ->toggleable(),
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
                    Tables\Actions\Action::make('grant_reward')
                        ->label('Grant Reward Points')
                        ->icon('heroicon-m-star')
                        ->color('warning')
                        ->form(GrantOrderRewardPoints::formSchema())
                        ->action(fn (array $data, Order $record) => GrantOrderRewardPoints::handle($record, $data))
                        ->visible(fn ($record) => $record->electrician_user_id !== null),
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
                                ->title('Order status updated for '.$records->count().' order(s)')
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
                                ->title('Payment status updated for '.$records->count().' order(s)')
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
                                ->title('Marked '.$records->count().' order(s) as shipped')
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
                                ->title('Marked '.$records->count().' order(s) as delivered')
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
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
