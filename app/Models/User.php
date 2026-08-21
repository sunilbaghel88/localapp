<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use App\Models\ShopType;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasPanelShield;
    
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'reward_points',
    ];

    protected $appends = [
        'name',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function splitFullName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return ['first_name' => '', 'last_name' => ''];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [''];

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(($this->attributes['first_name'] ?? '').' '.($this->attributes['last_name'] ?? '')),
            set: function (?string $value): array {
                return self::splitFullName($value);
            },
        );
    }

    public function getFilamentName(): string
    {
        return $this->name !== '' ? $this->name : ($this->email ?? 'User');
    }

    public function scopeWhereNameLike(Builder $query, string $needle): Builder
    {
        return $query->where(function (Builder $q) use ($needle) {
            $q->where('first_name', 'like', $needle)
                ->orWhere('last_name', 'like', $needle);
        });
    }

    public function scopeOrderByName(Builder $query): Builder
    {
        return $query->orderBy('first_name')->orderBy('last_name');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function userTypes(): BelongsToMany
    {
        return $this->belongsToMany(UserType::class, 'user_user_type')->withTimestamps();
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(UserRewardGrant::class);
    }

    public function rewardRedemptionRequests(): HasMany
    {
        return $this->hasMany(RewardRedemptionRequest::class);
    }

    public function partnerShops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user')->withTimestamps();
    }

    /**
     * True when this user has a type that can earn partner rewards
     * for at least one shop type (electrician, plumber, etc.).
     */
    public function isPartner(): bool
    {
        $typeIds = $this->relationLoaded('userTypes')
            ? $this->userTypes->pluck('id')
            : $this->userTypes()->pluck('user_types.id');

        if ($typeIds->isEmpty()) {
            return false;
        }

        return ShopType::query()
            ->where('supports_partner_rewards', true)
            ->whereHas(
                'rewardUserTypes',
                fn (Builder $q) => $q->whereIn('user_types.id', $typeIds)
            )
            ->exists();
    }

    /** @deprecated Use isPartner() */
    public function isElectrician(): bool
    {
        return $this->isPartner();
    }

    public function scopeWithAnyUserTypeIds(Builder $query, array $userTypeIds): Builder
    {
        $userTypeIds = array_values(array_filter(array_map('intval', $userTypeIds)));
        if ($userTypeIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'userTypes',
            fn (Builder $q) => $q->whereIn('user_types.id', $userTypeIds)
        );
    }

    public function canAssignUserTypes(): bool
    {
        return $this->hasRole('super_admin')
            || $this->hasRole('shop_owner')
            || $this->can('update_user')
            || $this->shops()->exists();
    }

    public function canAssignUserTypesTo(User $target): bool
    {
        if (! $this->canAssignUserTypes()) {
            return false;
        }

        if ($target->hasRole('super_admin') && ! $this->hasRole('super_admin')) {
            return false;
        }

        return true;
    }

    public function scopeWithUserTypeId(Builder $query, ?int $userTypeId): Builder
    {
        if (! $userTypeId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'userTypes',
            fn (Builder $q) => $q->where('user_types.id', $userTypeId)
        );
    }

    public function userTypesPayload(): array
    {
        $types = $this->relationLoaded('userTypes')
            ? $this->userTypes
            : $this->userTypes()->orderBy('user_types.sort_order')->get();

        return $types
            ->sortBy('sort_order')
            ->map(fn (UserType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])
            ->values()
            ->all();
    }

    public function toAuthArray(): array
    {
        $this->loadMissing('userTypes');

        $role = $this->roles()->pluck('name')->first();
        $permissions = $this->getAllPermissions()->pluck('name') ?? collect();

        return array_merge($this->toArray(), [
            'role' => $role,
            'permissions' => $permissions,
            'is_partner' => $this->isPartner(),
            'is_electrician' => $this->isPartner(), // BC for mobile clients
            'user_types' => $this->userTypesPayload(),
            'can_assign_user_types' => $this->canAssignUserTypes(),
        ]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists() && ! $this->isPartner();
    }
}
