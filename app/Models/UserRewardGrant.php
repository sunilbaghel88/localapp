<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRewardGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'offline_bill_id',
        'points',
        'granted_by',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function offlineBill(): BelongsTo
    {
        return $this->belongsTo(OfflineBill::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function sourceLabel(): string
    {
        if ($this->order_id) {
            return '#'.$this->order_id;
        }

        if ($this->offline_bill_id) {
            return 'Offline bill #'.$this->offline_bill_id;
        }

        return '—';
    }

    public function shopName(): ?string
    {
        return $this->order?->shop?->name ?? $this->offlineBill?->shop?->name;
    }
}
