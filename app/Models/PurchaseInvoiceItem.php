<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'product_variant_id',
        'name',
        'variant_name',
        'hsn_code',
        'unit',
        'quantity',
        'list_price',
        'discount_percent',
        'cost_price',
        'selling_price',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'list_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
    ];

    protected $appends = [
        'name_hi',
    ];

    public function getNameHiAttribute(): ?string
    {
        if (! $this->relationLoaded('product')) {
            return null;
        }

        return $this->product?->name_hi;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
