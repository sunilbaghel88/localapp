<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopPeopleResource\Pages;
use App\Models\Shop;
use App\Models\ShopType;
use App\Models\User;
use App\Models\UserType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShopPeopleResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Customers & Partners';

    protected static ?string $modelLabel = 'person';

    protected static ?string $pluralModelLabel = 'Customers & Partners';

    protected static ?string $slug = 'customers-partners';

    protected static ?string $navigationGroup = 'Application';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAssignUserTypes() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canAssignUserTypes() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canAssignUserTypes() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $record instanceof User
            ? $actor->canAssignUserTypesTo($record)
            : false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('kind')
                    ->label('Type')
                    ->options([
                        'customer' => 'Customer',
                        'partner' => 'Partner',
                    ])
                    ->default('customer')
                    ->required()
                    ->live()
                    ->visibleOn('create'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Mobile')
                    ->tel()
                    ->required()
                    ->maxLength(15),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->visibleOn('edit'),
                Forms\Components\Select::make('shop_id')
                    ->label('Attach to shop')
                    ->options(fn () => static::ownedShopsQuery()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('kind') === 'partner')
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('kind') === 'partner'),
                Forms\Components\Select::make('user_type_id')
                    ->label('Partner type')
                    ->options(fn () => static::partnerTypeOptions())
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && $get('kind') === 'partner'),
                Forms\Components\Select::make('user_type_ids')
                    ->label('Partner types')
                    ->multiple()
                    ->options(fn () => static::partnerTypeOptions())
                    ->searchable()
                    ->preload()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Forms\Components\Select::make('shop_ids')
                    ->label('Attached shops')
                    ->multiple()
                    ->options(fn () => static::ownedShopsQuery()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('userTypes.name')
                    ->label('Types')
                    ->badge()
                    ->placeholder('Customer')
                    ->separator(','),
                Tables\Columns\TextColumn::make('partnerShops.name')
                    ->label('Shops')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('reward_points')
                    ->label('Points')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('first_name');
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        $actorId = $actor?->id;

        return parent::getEloquentQuery()
            ->withoutSuperAdmins()
            ->with([
                'userTypes',
                'partnerShops' => fn ($q) => $actorId
                    ? $q->where('shops.user_id', $actorId)
                    : $q,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopPeople::route('/'),
            'create' => Pages\CreateShopPerson::route('/create'),
            'edit' => Pages\EditShopPerson::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone'];
    }

    /**
     * @return array<int, string>
     */
    protected static function partnerTypeOptions(): array
    {
        $ids = ShopType::rewardEligibleUserTypeIds();
        if ($ids === []) {
            return [];
        }

        return UserType::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function ownedShopsQuery()
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return Shop::query()->whereRaw('1 = 0');
        }

        return $actor->shops()->orderBy('name');
    }
}
