<?php

namespace App\Rules;

use App\Models\Material;
use App\Support\Quantity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Dipakai di form (Filament) dan Form Request untuk kuantitas dokumen (PR, PO, GR).
 *
 * Contoh di Filament:
 *   TextInput::make('quantity')->rules(fn (Get $get) => [new ValidQuantity($get('material_id'))])
 */
class ValidQuantity implements ValidationRule
{
    public function __construct(private readonly int|string|null $materialId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $material = $this->materialId ? Material::find($this->materialId) : null;

        if (! $material) {
            $fail('Pilih material terlebih dahulu.');

            return;
        }

        $label = "Kuantitas {$material->name} ({$material->base_uom})";

        if ($message = Quantity::error($value, (int) $material->qty_decimals, $label)) {
            $fail($message);
        }
    }
}
