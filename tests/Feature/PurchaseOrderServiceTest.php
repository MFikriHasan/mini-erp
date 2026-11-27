<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DocumentNumberService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequisitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

function poUser(UserRole $role, ?int $departmentId = null): User
{
    return User::factory()->create(['role' => $role, 'department_id' => $departmentId]);
}

function poValidationErrors(Closure $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Diharapkan ValidationException, tetapi tidak dilempar.');
}

beforeEach(function () {
    $this->prService = app(PurchaseRequisitionService::class);
    $this->poService = app(PurchaseOrderService::class);

    $this->dept = Department::create(['code' => 'PRD', 'name' => 'Produksi']);
    $this->staff = poUser(UserRole::Staff, $this->dept->id);
    $this->manager = poUser(UserRole::Manager, $this->dept->id);
    $this->purchasing = poUser(UserRole::Purchasing);
    $this->warehouse = poUser(UserRole::Warehouse);

    $this->vendor = Vendor::create(['code' => 'VND-001', 'name' => 'Vendor Uji', 'is_active' => true]);
    $this->pcs = Material::create(['code' => 'MAT-001', 'name' => 'Baut', 'base_uom' => 'PCS', 'qty_decimals' => 0, 'min_stock' => 0]);
    $this->kg = Material::create(['code' => 'MAT-008', 'name' => 'Plat Baja', 'base_uom' => 'KG', 'qty_decimals' => 3, 'min_stock' => 0]);

    $this->prices = [$this->pcs->id => '2500', $this->kg->id => '15000.5'];

    // PR yang sudah melewati alur lengkap sampai disetujui
    $this->approvedPr = function (): PurchaseRequisition {
        $pr = $this->prService->create($this->staff, [], [
            ['material_id' => $this->pcs->id, 'quantity' => '10'],
            ['material_id' => $this->kg->id, 'quantity' => '12.5'],
        ]);
        $pr = $this->prService->submit($pr, $this->staff);

        return $this->prService->approve($pr, $this->manager);
    };

    // PO draft dari PR yang disetujui
    $this->draftPo = function (): PurchaseOrder {
        return $this->poService->createFromRequisition(($this->approvedPr)(), $this->purchasing, $this->vendor->id, $this->prices);
    };
});

// ---------------- Membuat PO dari PR ----------------
it('purchasing mengubah PR yang disetujui menjadi PO draft', function () {
    $pr = ($this->approvedPr)();

    $po = $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $this->prices, ['notes' => 'Kirim segera']);

    expect($po->number)->toBe('PO-'.now()->year.'-0001')
        ->and($po->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($po->vendor_id)->toBe($this->vendor->id)
        ->and($po->created_by)->toBe($this->purchasing->id)
        ->and($po->purchase_requisition_id)->toBe($pr->id)
        ->and($po->items)->toHaveCount(2)
        ->and($pr->fresh()->status)->toBe(RequisitionStatus::Converted);

    $kg = $po->items->firstWhere('material_id', $this->kg->id);

    expect($kg->quantity)->toBe('12.500')
        ->and($kg->unit_price)->toBe('15000.50')
        ->and($kg->received_quantity)->toBe('0.000');
});

it('peran selain purchasing tidak boleh membuat PO', function (UserRole $role) {
    $pr = ($this->approvedPr)();
    $user = poUser($role, $this->dept->id);

    expect(fn () => $this->poService->createFromRequisition($pr, $user, $this->vendor->id, $this->prices))
        ->toThrow(AuthorizationException::class)
        ->and($pr->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and(PurchaseOrder::count())->toBe(0);
})->with([UserRole::Staff, UserRole::Manager, UserRole::Warehouse, UserRole::Admin]);

it('PR yang belum disetujui tidak bisa dijadikan PO', function (string $state) {
    $pr = $this->prService->create($this->staff, [], [['material_id' => $this->pcs->id, 'quantity' => '1']]);

    if ($state === 'submitted') {
        $pr = $this->prService->submit($pr, $this->staff);
    }

    expect(fn () => $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, [$this->pcs->id => '100']))
        ->toThrow(AuthorizationException::class);
})->with(['draft', 'submitted']);

it('menolak vendor yang kosong, tidak dikenal, atau tidak aktif', function () {
    $pr = ($this->approvedPr)();
    $inactive = Vendor::create(['code' => 'VND-002', 'name' => 'Vendor Nonaktif', 'is_active' => false]);

    foreach ([null, 99999, $inactive->id] as $vendorId) {
        expect(poValidationErrors(fn () => $this->poService->createFromRequisition($pr, $this->purchasing, $vendorId, $this->prices)))
            ->toHaveKey('vendor_id');
    }
});

it('menolak harga yang tidak valid', function (string $price) {
    $pr = ($this->approvedPr)();
    $prices = [$this->pcs->id => '2500', $this->kg->id => $price];

    expect(poValidationErrors(fn () => $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $prices)))
        ->toHaveKey("unit_prices.{$this->kg->id}");
})->with(['', '0', '-5', '12.345', 'abc', '1e3']);

it('menolak harga yang tidak lengkap tanpa mengubah apa pun', function () {
    $pr = ($this->approvedPr)();

    poValidationErrors(fn () => $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, [$this->pcs->id => '2500']));

    expect($pr->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and(PurchaseOrder::count())->toBe(0);

    // Nomor PO tidak terpakai oleh percobaan yang gagal
    $po = $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $this->prices);

    expect($po->number)->toBe('PO-'.now()->year.'-0001');
});

it('satu PR tidak bisa dijadikan PO dua kali', function () {
    $pr = ($this->approvedPr)();
    $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $this->prices);

    expect(fn () => $this->poService->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $this->prices))
        ->toThrow(AuthorizationException::class)
        ->and(PurchaseOrder::count())->toBe(1);
});

it('membatalkan perubahan status PR bila pembuatan PO gagal di tengah jalan', function () {
    $pr = ($this->approvedPr)();

    $this->mock(DocumentNumberService::class, function ($mock) {
        $mock->shouldReceive('next')->andThrow(new RuntimeException('nomor gagal dibuat'));
    });

    $service = app(PurchaseOrderService::class);

    expect(fn () => $service->createFromRequisition($pr, $this->purchasing, $this->vendor->id, $this->prices))
        ->toThrow(RuntimeException::class)
        ->and($pr->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and(PurchaseOrder::count())->toBe(0);
});

// ---------------- Menerbitkan dan membatalkan ----------------
it('purchasing menerbitkan PO draft', function () {
    $po = $this->poService->issue(($this->draftPo)(), $this->purchasing);

    expect($po->status)->toBe(PurchaseOrderStatus::Issued);
});

it('selain purchasing tidak boleh menerbitkan PO', function (string $who) {
    $po = ($this->draftPo)();

    expect(fn () => $this->poService->issue($po, $this->{$who}))->toThrow(AuthorizationException::class);
})->with(['staff', 'manager', 'warehouse']);

it('PO yang sudah terbit tidak bisa diterbitkan lagi', function () {
    $po = $this->poService->issue(($this->draftPo)(), $this->purchasing);

    expect(fn () => $this->poService->issue($po, $this->purchasing))->toThrow(AuthorizationException::class);
});

it('purchasing dapat membatalkan PO draft maupun PO yang sudah terbit', function () {
    $draft = $this->poService->cancel(($this->draftPo)(), $this->purchasing);
    $issued = $this->poService->cancel($this->poService->issue(($this->draftPo)(), $this->purchasing), $this->purchasing);

    expect($draft->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($issued->status)->toBe(PurchaseOrderStatus::Cancelled);
});

it('PO tidak bisa dibatalkan setelah ada barang yang diterima', function (PurchaseOrderStatus $status) {
    $po = ($this->draftPo)();
    $po->update(['status' => $status]);

    expect(fn () => $this->poService->cancel($po, $this->purchasing))->toThrow(AuthorizationException::class);
})->with([PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Completed]);

// ---------------- Kebijakan ----------------
it('hanya gudang yang boleh menerima barang pada PO yang terbit', function () {
    $issued = $this->poService->issue(($this->draftPo)(), $this->purchasing);
    $draft = ($this->draftPo)();

    expect(Gate::forUser($this->warehouse)->allows('receive', $issued))->toBeTrue()
        ->and(Gate::forUser($this->purchasing)->allows('receive', $issued))->toBeFalse()
        ->and(Gate::forUser($this->warehouse)->allows('receive', $draft))->toBeFalse();
});

it('staff tidak melihat PO, sedangkan peran pengadaan dan gudang melihatnya', function () {
    $po = ($this->draftPo)();

    expect(Gate::forUser($this->staff)->allows('view', $po))->toBeFalse()
        ->and(Gate::forUser($this->staff)->allows('viewAny', PurchaseOrder::class))->toBeFalse()
        ->and(Gate::forUser($this->purchasing)->allows('view', $po))->toBeTrue()
        ->and(Gate::forUser($this->warehouse)->allows('view', $po))->toBeTrue()
        ->and(Gate::forUser($this->manager)->allows('view', $po))->toBeTrue();
});
