<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'shop_type_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ShopTypeField::class, 'shop_type_id')->orderBy('sort_order');
    }
}
