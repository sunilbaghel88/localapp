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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'user_type_id',
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

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(UserRewardGrant::class);
    }

    public function rewardRedemptionRequests(): HasMany
    {
        return $this->hasMany(RewardRedemptionRequest::class);
    }

    public function electricianShops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user')->withTimestamps();
    }

    public function isElectrician(): bool
    {
        if (! $this->user_type_id) {
            return false;
        }

        return ShopType::query()
            ->where('supports_electrician_rewards', true)
            ->where('electrician_user_type_id', $this->user_type_id)
            ->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists() && ! $this->isElectrician();
    }
}
