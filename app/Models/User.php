<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
        'owner_permissions',
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
            'owner_permissions' => 'array',
        ];
    }
    
    public function canAccessOwnerTab(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if ($this->role !== 'owner') {
            return false;
        }

        $permissions = $this->owner_permissions ?? [];

        return in_array($permission, $permissions, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->role === 'admin';
        }

        if ($panel->getId() === 'owner') {
            return $this->role === 'admin' || $this->role === 'owner';
        }

        return false;
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
}
