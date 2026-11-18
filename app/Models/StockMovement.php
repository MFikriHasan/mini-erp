<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Ledger stok: append-only. Baris tidak pernah diubah atau dihapus;
 * koreksi dilakukan dengan baris REVERSAL.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'material_id',
        'storage_location_id',
        'movement_type',
        'quantity',
        'reference_type',
        'reference_id',
        'reversal_of_id',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity' => 'decimal:3',
            'posted_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // Dokumen sumber (GoodsReceiptItem, GoodsIssueItem, ...)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Baris asli yang dibalik oleh baris ini
    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    // Baris pembalik untuk baris ini (jika ada)
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock ledger tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Stock ledger tidak boleh dihapus.'));
    }
}
