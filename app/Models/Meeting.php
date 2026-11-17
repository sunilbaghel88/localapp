<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $fillable = [
        'shop_id',
        'created_by',
        'title',
        'details',
        'location',
        'starts_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MeetingPhoto::class)->latest();
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing(['shop:id,name', 'photos']);

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'shop' => $this->shop ? ['id' => $this->shop->id, 'name' => $this->shop->name] : null,
            'title' => $this->title,
            'details' => $this->details,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->format('Y-m-d\\TH:i:s'),
            'photos' => $this->photos->map(fn (MeetingPhoto $photo) => $photo->toApiArray())->values(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
