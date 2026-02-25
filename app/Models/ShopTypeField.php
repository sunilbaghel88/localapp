<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopTypeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_type_id',
        'label',
        'key',
        'field_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function shopType(): BelongsTo
    {
        return $this->belongsTo(ShopType::class);
    }
}
