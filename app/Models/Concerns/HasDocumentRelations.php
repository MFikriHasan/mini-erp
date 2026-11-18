<?php

namespace App\Models\Concerns;

use App\Models\Department;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Relasi User ke dokumen pengadaan. Pakai di model User: use HasDocumentRelations;
 */
trait HasDocumentRelations
{
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // PR yang dibuat user ini
    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'requested_by');
    }

    // PR yang disetujui/ditolak user ini
    public function approvedRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'approved_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'posted_by');
    }
}
