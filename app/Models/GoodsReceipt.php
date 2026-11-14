<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected function casts(): array
    {
        return ['receipt_date' => 'date'];
    }
    
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }


    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }


    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
