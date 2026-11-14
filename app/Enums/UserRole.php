<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor
{
    case Admin = 'admin';
    case Purchasing = 'purchasing';
    case Warehouse = 'warehouse';
    case Manager = 'manager';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Purchasing => 'Purchasing',
            self::Warehouse => 'Warehouse',
            self::Manager => 'Manager',
            self::Staff => 'Staff Departemen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Purchasing => 'info',
            self::Warehouse => 'warning',
            self::Manager => 'success',
            self::Staff => 'gray',
        };
    }

    public function canApproveRequisition(): bool
    {
        return $this === self::Manager;
    }

    public function canCreatePurchaseOrder(): bool
    {
        return $this === self::Purchasing;
    }

    public function canReceiveGoods(): bool
    {
        return $this === self::Warehouse;
    }
}
