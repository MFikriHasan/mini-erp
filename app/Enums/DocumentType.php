<?php

namespace App\Enums;

enum DocumentType: string
{
    case PurchaseRequisition = 'PR';
    case PurchaseOrder = 'PO';
    case GoodsReceipt = 'GR';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseRequisition => 'Purchase Requisition',
            self::PurchaseOrder => 'Purchase Order',
            self::GoodsReceipt => 'Goods Receipt',
        };
    }
}
