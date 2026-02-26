<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\ShopResource\Pages;
use App\Filament\Owner\Resources\ShopResource\RelationManagers;
use App\Models\Shop;
use App\Models\ShopType;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
                Forms\Components\Select::make('shop_type_id')
                    ->label('Shop Type')
                    ->relationship(
                        name: 'shopType',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('sort_order'),
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Model $record): bool => $record?->shop_type_id !== null)
                    ->dehydrated()
                    ->helperText('Once a shop type is selected for a shop, it cannot be changed. Existing shops without a type can set it once.'),
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

                        while (Shop::where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $counter;
                            $counter++;
                        }

                        $set('slug', $slug);
                    }),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->readOnly(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\TextInput::make('alternate_phone')
                    ->label('Alternate number')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address_line1')
                    ->label('Address line 1')
                    ->maxLength(255),
                Forms\Components\TextInput::make('address_line2')
                    ->label('Address line 2')
                    ->maxLength(255),
                Forms\Components\TextInput::make('city')
                    ->maxLength(255),
                Forms\Components\Select::make('state')
                    ->label('State')
                    ->options(fn () => State::query()->orderBy('sort_order')->pluck('name', 'name'))
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('country')
                    ->maxLength(255),
                Forms\Components\TextInput::make('postal_code')
                    ->label('Postal code')
                    ->maxLength(20),
                Forms\Components\Select::make('status')
                    ->options([
                        'on' => 'On',
                        'off' => 'Off',
                    ])
                    ->default('on')
                    ->helperText('Only products from shops set to "On" are visible on the Dashboard.'),
                Forms\Components\Section::make('Shop front photo')
                    ->schema([
                        Forms\Components\FileUpload::make('shop_front_photo')
                            ->label('Front photo of the shop')
                            ->image()
                            ->disk('public')
                            ->directory('shop-documents/shop-front')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Upload or capture via camera. On mobile, choose "Take photo" when uploading.'),
                    ])
                    ->collapsible(),
                Forms\Components\Section::make('Documents')
                    ->schema([
                        Forms\Components\FileUpload::make('owner_photo')
                            ->label("Owner's photo")
                            ->image()
                            ->disk('public')
                            ->directory('shop-documents/owner-photos')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Upload or capture via camera. On mobile, choose "Take photo" when uploading.'),
                        Forms\Components\FileUpload::make('aadhar_card')
                            ->label('Aadhar Card')
                            ->disk('public')
                            ->directory('shop-documents/aadhar')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\FileUpload::make('shop_license')
                            ->label('Shop License')
                            ->disk('public')
                            ->directory('shop-documents/shop-license')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\FileUpload::make('gst_certificate')
                            ->label('GST Certificate')
                            ->disk('public')
                            ->directory('shop-documents/gst-certificate')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                        Forms\Components\FileUpload::make('electricity_bill')
                            ->label('Electricity Bill')
                            ->disk('public')
                            ->directory('shop-documents/electricity-bill')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('shop_front_photo')
                    ->label('Front photo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'on' ? 'On' : 'Off')
                    ->color(fn (string $state): string => $state === 'on' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'on' => 'On',
                        'off' => 'Off',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
