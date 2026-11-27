<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\RequisitionStatus;
use App\Models\Material;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Support\Quantity;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh perubahan pada Purchase Requisition melalui kelas ini. Setiap method:
 *  1. memeriksa hak akses lewat Policy (tidak bergantung pada tombol di UI),
 *  2. berjalan di dalam transaksi dengan baris PR terkunci (aman dari aksi bersamaan),
 *  3. mengikuti alur status dari enum RequisitionStatus.
 */
class PurchaseRequisitionService
{
    public function __construct(private readonly DocumentNumberService $numbers)
    {
    }

    /**
     * @param  array{needed_date?: ?string, notes?: ?string}  $data
     * @param  array<int, array{material_id: int|string, quantity: string|int|float, notes?: ?string}>  $items
     */
    public function create(User $actor, array $data, array $items): PurchaseRequisition
    {
        Gate::forUser($actor)->authorize('create', PurchaseRequisition::class);

        // Validasi dulu, sebelum nomor dokumen dipakai
        $rows = $this->prepareItems($items);

        return DB::transaction(function () use ($actor, $data, $rows) {
            $requisition = PurchaseRequisition::create([
                'number' => $this->numbers->next(DocumentType::PurchaseRequisition),
                'department_id' => $actor->department_id,   // PR selalu atas nama departemen pembuatnya
                'requested_by' => $actor->id,
                'status' => RequisitionStatus::Draft,
                'needed_date' => $data['needed_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $requisition->items()->createMany($rows);

            return $requisition->load('items');
        });
    }

    /**
     * Mengganti isi PR draft (header dan seluruh item) dengan data baru.
     */
    public function update(PurchaseRequisition $requisition, User $actor, array $data, array $items): PurchaseRequisition
    {
        $rows = $this->prepareItems($items);

        return DB::transaction(function () use ($requisition, $actor, $data, $rows) {
            $locked = $this->lock($requisition);

            Gate::forUser($actor)->authorize('update', $locked);

            $locked->update([
                'needed_date' => $data['needed_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->items()->delete();
            $locked->items()->createMany($rows);

            return $locked->load('items');
        });
    }

    public function submit(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return $this->transition(
            $requisition,
            $actor,
            'submit',
            RequisitionStatus::Submitted,
            guard: function (PurchaseRequisition $locked): void {
                if (! $locked->items()->exists()) {
                    throw ValidationException::withMessages(['items' => 'PR tanpa item tidak dapat diajukan.']);
                }
            },
        );
    }

    public function approve(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return $this->transition($requisition, $actor, 'approve', RequisitionStatus::Approved, [
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(PurchaseRequisition $requisition, User $actor, string $reason): PurchaseRequisition
    {
        $reason = trim($reason);

        return $this->transition(
            $requisition,
            $actor,
            'reject',
            RequisitionStatus::Rejected,
            [
                'approved_by' => $actor->id,     // kolom ini mencatat pengambil keputusan (setuju atau tolak)
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ],
            guard: function () use ($reason): void {
                if ($reason === '') {
                    throw ValidationException::withMessages(['rejection_reason' => 'Alasan penolakan wajib diisi.']);
                }
            },
        );
    }

    /**
     * Menandai PR sebagai sudah menjadi PO. Dipanggil PurchaseOrderService di dalam
     * transaksinya, sehingga bila pembuatan PO gagal, status PR ikut dikembalikan.
     */
    public function markConverted(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return $this->transition($requisition, $actor, 'convert', RequisitionStatus::Converted);
    }

    public function reopen(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return $this->transition($requisition, $actor, 'reopen', RequisitionStatus::Draft, [
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Pindah status: kunci baris, cek hak akses (dengan status terbaru), jalankan pemeriksaan
     * tambahan, pastikan transisinya sah, lalu simpan.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        PurchaseRequisition $requisition,
        User $actor,
        string $ability,
        RequisitionStatus $to,
        array $attributes = [],
        ?Closure $guard = null,
    ): PurchaseRequisition {
        return DB::transaction(function () use ($requisition, $actor, $ability, $to, $attributes, $guard) {
            $locked = $this->lock($requisition);

            Gate::forUser($actor)->authorize($ability, $locked);

            if ($guard) {
                $guard($locked);
            }

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Status {$locked->status->getLabel()} tidak dapat diubah menjadi {$to->getLabel()}.",
                ]);
            }

            $locked->forceFill(['status' => $to] + $attributes)->save();

            return $locked;
        });
    }

    private function lock(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return PurchaseRequisition::query()
            ->whereKey($requisition->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Memeriksa seluruh item sekaligus dan mengumpulkan semua kesalahan,
     * agar pengguna melihat semua masalah dalam satu kali kirim.
     *
     * @return array<int, array{material_id: int, quantity: string, notes: ?string}>
     */
    private function prepareItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu item material wajib diisi.']);
        }

        $items = array_values($items);

        $materials = Material::query()
            ->whereIn('id', collect($items)->pluck('material_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $errors = [];
        $seen = [];
        $rows = [];

        foreach ($items as $index => $item) {
            $materialId = $item['material_id'] ?? null;
            $material = $materialId ? $materials->get($materialId) : null;

            if (! $material) {
                $errors["items.{$index}.material_id"] = 'Material tidak ditemukan.';

                continue;
            }

            if (! $material->is_active) {
                $errors["items.{$index}.material_id"] = "Material {$material->name} tidak aktif.";

                continue;
            }

            if (isset($seen[$material->id])) {
                $errors["items.{$index}.material_id"] = "Material {$material->name} sudah ada pada PR ini.";

                continue;
            }

            $seen[$material->id] = true;

            $quantity = trim((string) ($item['quantity'] ?? ''));
            $label = "Kuantitas {$material->name} ({$material->base_uom})";

            if ($message = Quantity::error($quantity, (int) $material->qty_decimals, $label)) {
                $errors["items.{$index}.quantity"] = $message;

                continue;
            }

            $rows[] = [
                'material_id' => $material->id,
                'quantity' => $quantity,
                'notes' => $item['notes'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }
}
