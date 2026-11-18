<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageLocation extends Model
{
    protected $fillable = ['code', 'name'];

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
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
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'stock_balances')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
