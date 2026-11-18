<?php

namespace App\Filament\Resources\PurchaseRequisitions\Pages;

use App\Filament\Resources\PurchaseRequisitions\Actions\PurchaseRequisitionActions;
use App\Filament\Resources\PurchaseRequisitions\PurchaseRequisitionResource;
use App\Filament\Resources\PurchaseRequisitions\Schemas\PurchaseRequisitionForm;
use App\Services\PurchaseRequisitionService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditPurchaseRequisition extends EditRecord
{
    protected static string $resource = PurchaseRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        // Setelah diajukan, PR tidak lagi bisa diedit, jadi pindah ke halaman lihat
        $toView = fn () => $this->redirect(
            PurchaseRequisitionResource::getUrl('view', ['record' => $this->getRecord()]),
        );

        return [
            ViewAction::make(),
            PurchaseRequisitionActions::submit($toView),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PurchaseRequisitionForm::fillItems($data, $this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PurchaseRequisitionService::class)->update(
                $record,
                Auth::user(),
                Arr::only($data, ['needed_date', 'notes']),
                $data['items'] ?? [],
            );
        } catch (ValidationException $exception) {
            PurchaseRequisitionActions::notifyValidation($exception);

            $this->halt();

            throw $exception;
        }
    }
}
