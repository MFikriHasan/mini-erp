<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

class Material extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_uom',
        'qty_decimals',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:3',
            'qty_decimals' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    // Many to Many via stock_balances (pivot membawa quantity)
    public function storageLocations(): BelongsToMany
    {
        return $this->belongsToMany(StorageLocation::class, 'stock_balances')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Apakah material ini sudah dipakai dokumen atau ledger.
     * Memakai query langsung agar tidak bergantung pada model dokumen yang belum dibuat.
     * (Boleh diganti dengan versi berbasis relasi setelah model-model itu ada.)
     */
    public function hasTransactions(): bool
    {
        foreach ([
            'purchase_requisition_items',
            'purchase_order_items',
            'goods_receipt_items',
            'stock_movements',
        ] as $table) {
            if (DB::table($table)->where('material_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    protected static function booted(): void
    {
        // Lapis pengaman terakhir (form sudah memvalidasi lebih dulu dengan pesan yang ramah)
        static::saving(function (Material $material) {
            if ($material->qty_decimals < 0 || $material->qty_decimals > 3) {
                throw new LogicException('qty_decimals harus antara 0 dan 3.');
            }

            if ($material->exists
                && $material->isDirty('qty_decimals')
                && $material->qty_decimals < $material->getOriginal('qty_decimals')
                && $material->hasTransactions()) {
                throw new LogicException('Jumlah desimal tidak boleh diturunkan setelah material dipakai transaksi.');
            }
        });
    }
}
