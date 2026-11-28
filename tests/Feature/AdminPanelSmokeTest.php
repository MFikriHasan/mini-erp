<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Memastikan halaman Filament benar-benar tampil (tidak ada kesalahan nama method, kelas, atau route)
 * dan tombol yang benar muncul untuk peran yang benar.
 *
 * Prasyarat: model User mengimplementasikan Filament\Models\Contracts\FilamentUser
 * (di luar lingkungan "local" Filament menolak model yang tidak mengimplementasikannya).
 */

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

function uiUser(UserRole $role, ?int $departmentId = null): User
{
    return User::factory()->create(['role' => $role, 'department_id' => $departmentId]);
}

beforeEach(function () {
    $this->dept = Department::create(['code' => 'PRD', 'name' => 'Produksi']);
    $this->staff = uiUser(UserRole::Staff, $this->dept->id);
    $this->manager = uiUser(UserRole::Manager, $this->dept->id);
    $this->purchasing = uiUser(UserRole::Purchasing);
    $this->warehouse = uiUser(UserRole::Warehouse);

    $this->vendor = Vendor::create(['code' => 'VND-001', 'name' => 'Vendor Uji', 'is_active' => true]);
    $this->material = Material::create(['code' => 'MAT-001', 'name' => 'Baut', 'base_uom' => 'PCS', 'qty_decimals' => 0, 'min_stock' => 0]);

    $this->submittedPr = function (): PurchaseRequisition {
        $service = app(PurchaseRequisitionService::class);
        $pr = $service->create($this->staff, [], [['material_id' => $this->material->id, 'quantity' => '10']]);

        return $service->submit($pr, $this->staff);
    };

    $this->approvedPr = function (): PurchaseRequisition {
        return app(PurchaseRequisitionService::class)->approve(($this->submittedPr)(), $this->manager);
    };

    $this->draftPo = function (): PurchaseOrder {
        return app(PurchaseOrderService::class)->createFromRequisition(
            ($this->approvedPr)(),
            $this->purchasing,
            $this->vendor->id,
            [$this->material->id => '2500'],
        );
    };
});

it('staff membuka daftar PR departemennya', function () {
    $pr = ($this->submittedPr)();

    $this->actingAs($this->staff)->get('/admin/purchase-requisitions')
        ->assertOk()
        ->assertSee($pr->number);
});

it('manager melihat tombol Setujui dan Tolak pada PR yang diajukan', function () {
    $pr = ($this->submittedPr)();

    $this->actingAs($this->manager)->get("/admin/purchase-requisitions/{$pr->id}")
        ->assertOk()
        ->assertSee('Setujui')
        ->assertSee('Tolak');
});

it('purchasing melihat tombol Buat PO pada PR yang disetujui', function () {
    $pr = ($this->approvedPr)();

    $this->actingAs($this->purchasing)->get("/admin/purchase-requisitions/{$pr->id}")
        ->assertOk()
        ->assertSee('Buat PO');
});

it('purchasing membuka daftar dan halaman PO dengan tombol Terbitkan', function () {
    $po = ($this->draftPo)();

    $this->actingAs($this->purchasing)->get('/admin/purchase-orders')
        ->assertOk()
        ->assertSee($po->number);

    $this->actingAs($this->purchasing)->get("/admin/purchase-orders/{$po->id}")
        ->assertOk()
        ->assertSee($po->number)
        ->assertSee('Terbitkan');
});

it('gudang melihat PO tetapi tidak melihat tombol Terbitkan', function () {
    $po = ($this->draftPo)();

    $this->actingAs($this->warehouse)->get("/admin/purchase-orders/{$po->id}")
        ->assertOk()
        ->assertDontSee('Terbitkan');
});

it('staff tidak boleh membuka halaman PO', function () {
    $po = ($this->draftPo)();

    $this->actingAs($this->staff)->get('/admin/purchase-orders')->assertForbidden();
    $this->actingAs($this->staff)->get("/admin/purchase-orders/{$po->id}")->assertForbidden();
});
