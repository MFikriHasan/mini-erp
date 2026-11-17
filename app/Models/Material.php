<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
