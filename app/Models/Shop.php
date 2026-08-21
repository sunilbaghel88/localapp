<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_type_id',
        'name',
        'slug',
        'description',
        'phone',
        'alternate_phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'status',
        'owner_photo',
        'aadhar_card',
        'shop_license',
        'gst_certificate',
        'electricity_bill',
        'shop_front_photo',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shopType(): BelongsTo
    {
        return $this->belongsTo(ShopType::class);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user')->withTimestamps();
    }

    public function rewardRedemptionRequests(): HasMany
    {
        return $this->hasMany(RewardRedemptionRequest::class);
    }

    public function scopeOn(Builder $query): Builder
    {
        return $query->where('status', 'on');
    }
}
