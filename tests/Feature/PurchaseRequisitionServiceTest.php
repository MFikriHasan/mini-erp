<?php

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Material;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\PurchaseRequisitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

function prUser(UserRole $role, ?int $departmentId = null): User
{
    return User::factory()->create(['role' => $role, 'department_id' => $departmentId]);
}

function prMaterial(string $code, string $uom, int $decimals, bool $active = true): Material
{
    return Material::create([
        'code' => $code, 'name' => "Material {$code}", 'base_uom' => $uom,
        'qty_decimals' => $decimals, 'min_stock' => 0, 'is_active' => $active,
    ]);
}

/** Menjalankan aksi yang diharapkan gagal validasi dan mengembalikan daftar kesalahannya. */
function prValidationErrors(Closure $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    throw new RuntimeException('Diharapkan ValidationException, tetapi tidak dilempar.');
}

beforeEach(function () {
    $this->service = app(PurchaseRequisitionService::class);

    $this->dept = Department::create(['code' => 'PRD', 'name' => 'Produksi']);
    $this->otherDept = Department::create(['code' => 'MNT', 'name' => 'Pemeliharaan']);

    $this->staff = prUser(UserRole::Staff, $this->dept->id);
    $this->manager = prUser(UserRole::Manager, $this->dept->id);
    $this->purchasing = prUser(UserRole::Purchasing);

    $this->pcs = prMaterial('MAT-001', 'PCS', 0);
    $this->kg = prMaterial('MAT-008', 'KG', 3);

    $this->draft = fn () => $this->service->create($this->staff, ['notes' => 'Uji'], [
        ['material_id' => $this->pcs->id, 'quantity' => '10'],
    ]);
});

// ---------------- Membuat PR ----------------
it('staff membuat PR draft dengan nomor otomatis dan item', function () {
    $pr = $this->service->create($this->staff, ['needed_date' => '2026-12-01', 'notes' => 'Butuh segera'], [
        ['material_id' => $this->pcs->id, 'quantity' => '10'],
        ['material_id' => $this->kg->id, 'quantity' => '12.5'],
    ]);

    expect($pr->number)->toBe('PR-'.now()->year.'-0001')
        ->and($pr->status)->toBe(RequisitionStatus::Draft)
        ->and($pr->department_id)->toBe($this->dept->id)
        ->and($pr->requested_by)->toBe($this->staff->id)
        ->and($pr->items)->toHaveCount(2);
});

it('peran selain staff tidak boleh membuat PR', function (UserRole $role) {
    $user = prUser($role, $this->dept->id);

    expect(fn () => $this->service->create($user, [], [['material_id' => $this->pcs->id, 'quantity' => '1']]))
        ->toThrow(AuthorizationException::class);
})->with([UserRole::Admin, UserRole::Manager, UserRole::Purchasing, UserRole::Warehouse]);

it('staff tanpa departemen tidak boleh membuat PR', function () {
    $staff = prUser(UserRole::Staff, null);

    expect(fn () => $this->service->create($staff, [], [['material_id' => $this->pcs->id, 'quantity' => '1']]))
        ->toThrow(AuthorizationException::class);
});

// ---------------- Validasi item ----------------
it('menolak pecahan pada material PCS', function () {
    $errors = prValidationErrors(fn () => $this->service->create($this->staff, [], [
        ['material_id' => $this->pcs->id, 'quantity' => '2.5'],
    ]));

    expect($errors)->toHaveKey('items.0.quantity');
});

it('menolak desimal melebihi presisi material KG', function () {
    $errors = prValidationErrors(fn () => $this->service->create($this->staff, [], [
        ['material_id' => $this->kg->id, 'quantity' => '12.5555'],
    ]));

    expect($errors)->toHaveKey('items.0.quantity');
});

it('menolak kuantitas nol atau negatif', function (string $quantity) {
    $errors = prValidationErrors(fn () => $this->service->create($this->staff, [], [
        ['material_id' => $this->kg->id, 'quantity' => $quantity],
    ]));

    expect($errors)->toHaveKey('items.0.quantity');
})->with(['0', '-1', '0.000']);

it('menolak PR tanpa item', function () {
    expect(prValidationErrors(fn () => $this->service->create($this->staff, [], [])))->toHaveKey('items');
});

it('menolak material ganda, tidak aktif, dan tidak dikenal, dengan semua kesalahan terkumpul', function () {
    $inactive = prMaterial('MAT-009', 'PCS', 0, active: false);

    $errors = prValidationErrors(fn () => $this->service->create($this->staff, [], [
        ['material_id' => $this->pcs->id, 'quantity' => '1'],
        ['material_id' => $this->pcs->id, 'quantity' => '2'],     // ganda
        ['material_id' => $inactive->id, 'quantity' => '1'],      // tidak aktif
        ['material_id' => 99999, 'quantity' => '1'],              // tidak ada
    ]));

    expect($errors)->toHaveKeys(['items.1.material_id', 'items.2.material_id', 'items.3.material_id']);
});

it('tidak menghabiskan nomor dokumen saat validasi gagal', function () {
    prValidationErrors(fn () => $this->service->create($this->staff, [], [
        ['material_id' => $this->pcs->id, 'quantity' => '2.5'],
    ]));

    expect(($this->draft)()->number)->toBe('PR-'.now()->year.'-0001');
});

// ---------------- Mengubah PR ----------------
it('pembuat dapat mengganti isi PR draft', function () {
    $pr = ($this->draft)();

    $updated = $this->service->update($pr, $this->staff, ['notes' => 'Revisi'], [
        ['material_id' => $this->kg->id, 'quantity' => '3.25'],
    ]);

    expect($updated->notes)->toBe('Revisi')
        ->and($updated->items)->toHaveCount(1)
        ->and($updated->items->first()->material_id)->toBe($this->kg->id);
});

it('PR yang sudah diajukan tidak boleh diubah', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    expect(fn () => $this->service->update($pr, $this->staff, [], [['material_id' => $this->pcs->id, 'quantity' => '1']]))
        ->toThrow(AuthorizationException::class);
});

// ---------------- Mengajukan ----------------
it('pembuat dapat mengajukan PR draft', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    expect($pr->status)->toBe(RequisitionStatus::Submitted);
});

it('staff lain di departemen yang sama tidak boleh mengajukan PR orang lain', function () {
    $pr = ($this->draft)();
    $colleague = prUser(UserRole::Staff, $this->dept->id);

    expect(fn () => $this->service->submit($pr, $colleague))->toThrow(AuthorizationException::class);
});

// ---------------- Persetujuan ----------------
it('manager menyetujui PR yang diajukan dan keputusan tercatat', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    $approved = $this->service->approve($pr, $this->manager);

    expect($approved->status)->toBe(RequisitionStatus::Approved)
        ->and($approved->approved_by)->toBe($this->manager->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('hanya manager yang boleh menyetujui', function (string $who) {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    expect(fn () => $this->service->approve($pr, $this->{$who}))->toThrow(AuthorizationException::class);
})->with(['staff', 'purchasing']);

it('manager tidak boleh menyetujui PR miliknya sendiri', function () {
    $own = PurchaseRequisition::create([
        'number' => 'PR-SOD-0001', 'department_id' => $this->dept->id,
        'requested_by' => $this->manager->id, 'status' => RequisitionStatus::Submitted,
    ]);

    expect(fn () => $this->service->approve($own, $this->manager))->toThrow(AuthorizationException::class);
});

it('PR draft tidak bisa disetujui', function () {
    expect(fn () => $this->service->approve(($this->draft)(), $this->manager))->toThrow(AuthorizationException::class);
});

it('PR yang sudah disetujui tidak bisa disetujui atau ditolak lagi', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);
    $this->service->approve($pr, $this->manager);
    $secondManager = prUser(UserRole::Manager, $this->dept->id);

    expect(fn () => $this->service->approve($pr, $secondManager))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->reject($pr, $secondManager, 'Terlambat'))->toThrow(AuthorizationException::class);
});

// ---------------- Penolakan dan pembukaan kembali ----------------
it('penolakan wajib menyertakan alasan', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    expect(prValidationErrors(fn () => $this->service->reject($pr, $this->manager, '   ')))->toHaveKey('rejection_reason')
        ->and($pr->fresh()->status)->toBe(RequisitionStatus::Submitted);
});

it('manager menolak PR dengan alasan', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);

    $rejected = $this->service->reject($pr, $this->manager, 'Anggaran belum tersedia');

    expect($rejected->status)->toBe(RequisitionStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('Anggaran belum tersedia')
        ->and($rejected->approved_by)->toBe($this->manager->id);
});

it('pembuat membuka kembali PR yang ditolak, memperbaikinya, lalu mengajukan ulang', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);
    $this->service->reject($pr, $this->manager, 'Jumlah terlalu besar');

    $reopened = $this->service->reopen($pr, $this->staff);

    expect($reopened->status)->toBe(RequisitionStatus::Draft)
        ->and($reopened->rejection_reason)->toBeNull()
        ->and($reopened->approved_by)->toBeNull()
        ->and($reopened->approved_at)->toBeNull();

    $this->service->update($reopened, $this->staff, [], [['material_id' => $this->pcs->id, 'quantity' => '5']]);
    $resubmitted = $this->service->submit($reopened, $this->staff);

    expect($resubmitted->status)->toBe(RequisitionStatus::Submitted);
});

it('hanya pembuat yang boleh membuka kembali PR yang ditolak', function () {
    $pr = $this->service->submit(($this->draft)(), $this->staff);
    $this->service->reject($pr, $this->manager, 'Ditolak');
    $colleague = prUser(UserRole::Staff, $this->dept->id);

    expect(fn () => $this->service->reopen($pr, $colleague))->toThrow(AuthorizationException::class);
});

// ---------------- Kebijakan melihat ----------------
it('staff hanya melihat PR departemennya sendiri', function () {
    $pr = ($this->draft)();
    $outsider = prUser(UserRole::Staff, $this->otherDept->id);

    expect(Gate::forUser($this->staff)->allows('view', $pr))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $pr))->toBeFalse()
        ->and(Gate::forUser($this->manager)->allows('view', $pr))->toBeTrue()
        ->and(Gate::forUser($this->purchasing)->allows('view', $pr))->toBeTrue();
});

it('gudang tidak melihat daftar PR', function () {
    $warehouse = prUser(UserRole::Warehouse);

    expect(Gate::forUser($warehouse)->allows('viewAny', PurchaseRequisition::class))->toBeFalse();
});
