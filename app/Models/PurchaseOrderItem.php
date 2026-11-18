<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'quantity',
        'unit_price',
        'received_quantity',
    ];

    // Cermin default database, agar instance baru (sebelum disegarkan) tidak bernilai null
    protected $attributes = [
        'received_quantity' => '0.000',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    // Sisa yang belum diterima. Pakai bcmath (string), BUKAN operator `-` pada float.
    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(fn (): string => Quantity::sub($this->quantity, $this->received_quantity));
    }
}
