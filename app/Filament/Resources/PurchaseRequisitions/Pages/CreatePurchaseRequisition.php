<?php

namespace App\Filament\Resources\PurchaseRequisitions\Pages;

use App\Filament\Resources\PurchaseRequisitions\Actions\PurchaseRequisitionActions;
use App\Filament\Resources\PurchaseRequisitions\PurchaseRequisitionResource;
use App\Services\PurchaseRequisitionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreatePurchaseRequisition extends CreateRecord
{
    protected static string $resource = PurchaseRequisitionResource::class;

    // Pembuatan selalu lewat service: nomor otomatis, validasi presisi per material, pemeriksaan Policy
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseRequisitionService::class)->create(
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
