<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfflineBill extends Model
{
    protected $fillable = [
        'shop_id',
        'created_by',
        'type',
        'payment_mode',
        'customer_id',
        'partner_id',
        'reward_points',
        'amount',
        'remarks',
        'image_path',
    ];

    protected $casts = [
        'reward_points' => 'integer',
        'amount' => 'decimal:2',
    ];

    protected $appends = [
        'image_url',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(UserRewardGrant::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        $path = ltrim((string) $this->image_path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return secure_url('media/'.$path);
    }
}
