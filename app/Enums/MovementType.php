<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasLabel, HasColor
{
    case GoodsReceipt = 'GR';
    case GoodsIssue = 'GI';
    case Reversal = 'REVERSAL';

    public function getLabel(): string
    {
        return match ($this) {
            self::GoodsReceipt => 'Penerimaan Barang',
            self::GoodsIssue => 'Pengeluaran Barang',
            self::Reversal => 'Pembalikan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GoodsReceipt => 'success',
            self::GoodsIssue => 'warning',
            self::Reversal => 'danger',
        };
    }

    public function sign(): ?int
    {
        return match ($this) {
            self::GoodsReceipt => 1,
            self::GoodsIssue => -1,
            self::Reversal => null,
        };
    }
}
