<?php

namespace App\Filament\Resources\PurchaseRequisitions\Pages;

use App\Filament\Resources\PurchaseRequisitions\Actions\PurchaseRequisitionActions;
use App\Filament\Resources\PurchaseRequisitions\PurchaseRequisitionResource;
use App\Filament\Resources\PurchaseRequisitions\Schemas\PurchaseRequisitionForm;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseRequisition extends ViewRecord
{
    protected static string $resource = PurchaseRequisitionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PurchaseRequisitionForm::fillItems($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        // Muat ulang record agar tombol yang tampil mengikuti status terbaru
        $refresh = function (): void {
            $this->getRecord()->refresh();
            $this->refreshFormData(['status', 'rejection_reason']);
        };

        return [
            EditAction::make(),
            PurchaseRequisitionActions::submit($refresh),
            PurchaseRequisitionActions::approve($refresh),
            PurchaseRequisitionActions::reject($refresh),
            PurchaseRequisitionActions::reopen($refresh),
        ];
    }
}
