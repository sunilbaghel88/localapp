<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
        'supports_partner_rewards',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_partner_rewards' => 'boolean',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'shop_type_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ShopTypeField::class, 'shop_type_id')->orderBy('sort_order');
    }

    /**
     * User types eligible for partner rewards on shops of this type
     * (e.g. Electrician, Plumber).
     */
    public function rewardUserTypes(): BelongsToMany
    {
        return $this->belongsToMany(UserType::class, 'shop_type_user_type')->withTimestamps();
    }

    public function supportsPartnerRewards(): bool
    {
        return (bool) $this->supports_partner_rewards
            && $this->rewardUserTypes()->exists();
    }

    public function rewardUserTypeIds(): array
    {
        if ($this->relationLoaded('rewardUserTypes')) {
            return $this->rewardUserTypes->pluck('id')->all();
        }

        return $this->rewardUserTypes()->pluck('user_types.id')->all();
    }
}
