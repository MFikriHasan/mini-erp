<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh perubahan pada Purchase Order melalui kelas ini (pola sama dengan PurchaseRequisitionService:
 * Policy diperiksa di sini, baris dikunci, dan seluruh langkah berjalan dalam satu transaksi).
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PurchaseRequisitionService $requisitions,
    ) {
    }

    /**
     * Membuat PO (status draft) dari PR yang sudah disetujui, dan menandai PR sebagai converted.
     * Kedua langkah itu satu transaksi: bila salah satu gagal, keduanya dibatalkan.
     *
     * @param  array<int|string, string|int|float|null>  $unitPrices  material_id => harga satuan
     * @param  array{order_date?: ?string, notes?: ?string}  $data
     */
    public function createFromRequisition(
        PurchaseRequisition $requisition,
        User $actor,
        int|string|null $vendorId,
        array $unitPrices,
        array $data = [],
    ): PurchaseOrder {
        Gate::forUser($actor)->authorize('convert', $requisition);

        [$vendor, $orderDate, $rows] = $this->prepare($requisition, $vendorId, $unitPrices, $data);

        return DB::transaction(function () use ($requisition, $actor, $vendor, $orderDate, $rows, $data) {
            // Memeriksa hak akses dengan status terbaru, mengunci PR, lalu mengubahnya menjadi converted
            $this->requisitions->markConverted($requisition, $actor);

            $order = PurchaseOrder::create([
                'number' => $this->numbers->next(DocumentType::PurchaseOrder, $orderDate),
                'purchase_requisition_id' => $requisition->id,
                'vendor_id' => $vendor->id,
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $orderDate->toDateString(),
                'created_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($rows);

            return $order->load('items');
        });
    }

    public function issue(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return $this->transition($order, $actor, 'issue', PurchaseOrderStatus::Issued);
    }

    public function cancel(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return $this->transition($order, $actor, 'cancel', PurchaseOrderStatus::Cancelled);
    }

    private function transition(PurchaseOrder $order, User $actor, string $ability, PurchaseOrderStatus $to): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor, $ability, $to) {
            $locked = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize($ability, $locked);

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Status {$locked->status->getLabel()} tidak dapat diubah menjadi {$to->getLabel()}.",
                ]);
            }

            $locked->forceFill(['status' => $to])->save();

            return $locked;
        });
    }

    /**
     * Memeriksa vendor, tanggal, dan harga seluruh item sekaligus (semua kesalahan dikumpulkan).
     *
     * @return array{0: Vendor, 1: Carbon, 2: array<int, array<string, mixed>>}
     */
    private function prepare(PurchaseRequisition $requisition, int|string|null $vendorId, array $unitPrices, array $data): array
    {
        $errors = [];

        $vendor = $vendorId ? Vendor::find($vendorId) : null;

        if (! $vendor) {
            $errors['vendor_id'] = 'Vendor tidak ditemukan.';
        } elseif (! $vendor->is_active) {
            $errors['vendor_id'] = "Vendor {$vendor->name} tidak aktif.";
        }

        try {
            $orderDate = isset($data['order_date']) ? Carbon::parse($data['order_date']) : now();
        } catch (InvalidFormatException) {
            $orderDate = now();
            $errors['order_date'] = 'Tanggal PO tidak valid.';
        }

        $rows = [];

        foreach ($requisition->items()->with('material')->get() as $item) {
            $price = trim((string) ($unitPrices[$item->material_id] ?? ''));
            $label = $item->material?->name ?? 'material';

            if ($message = $this->priceError($price, $label)) {
                $errors["unit_prices.{$item->material_id}"] = $message;

                continue;
            }

            $rows[] = [
                'material_id' => $item->material_id,
                'quantity' => (string) $item->quantity,
                'unit_price' => bcadd($price, '0', 2),
            ];
        }

        if ($rows === [] && $errors === []) {
            $errors['items'] = 'PR tidak memiliki item.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$vendor, $orderDate, $rows];
    }

    private function priceError(string $price, string $label): ?string
    {
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $price)) {
            return "Harga {$label} harus berupa angka positif dengan maksimal 2 angka di belakang koma.";
        }

        if (bccomp($price, '0', 2) <= 0) {
            return "Harga {$label} harus lebih dari 0.";
        }

        return null;
    }
}
