<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PurchaseOrderForm::fillItems($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        // Muat ulang record agar tombol yang tampil mengikuti status terbaru
        $refresh = function (): void {
            $this->getRecord()->refresh();
            $this->refreshFormData(['status']);
        };

        return [
            PurchaseOrderActions::issue($refresh),
            PurchaseOrderActions::cancel($refresh),
        ];
    }
}
