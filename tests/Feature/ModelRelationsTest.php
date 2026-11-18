<?php

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

function buildProcurementChain(): array
{
    $dept = Department::create(['code' => 'PRD', 'name' => 'Produksi']);
    $user = User::factory()->create(['role' => UserRole::Staff, 'department_id' => $dept->id]);
    $vendor = Vendor::create(['code' => 'VND-001', 'name' => 'Vendor Contoh']);
    $location = StorageLocation::create(['code' => 'GD-01', 'name' => 'Gudang Utama']);
    $material = Material::create(['code' => 'MAT-008', 'name' => 'Plat Baja 5 mm', 'base_uom' => 'KG', 'qty_decimals' => 3, 'min_stock' => 0]);

    $pr = PurchaseRequisition::create([
        'number' => 'PR-2026-0001', 'department_id' => $dept->id, 'requested_by' => $user->id,
        'status' => RequisitionStatus::Approved,
    ]);
    PurchaseRequisitionItem::create(['purchase_requisition_id' => $pr->id, 'material_id' => $material->id, 'quantity' => '12.500']);

    $po = PurchaseOrder::create([
        'number' => 'PO-2026-0001', 'purchase_requisition_id' => $pr->id, 'vendor_id' => $vendor->id,
        'status' => PurchaseOrderStatus::Issued, 'order_date' => '2026-11-17', 'created_by' => $user->id,
    ]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'material_id' => $material->id, 'quantity' => '12.500', 'unit_price' => '15000.00',
    ]);

    $gr = GoodsReceipt::create([
        'number' => 'GR-2026-0001', 'purchase_order_id' => $po->id, 'storage_location_id' => $location->id,
        'received_by' => $user->id, 'receipt_date' => '2026-11-18',
    ]);
    $grItem = GoodsReceiptItem::create([
        'goods_receipt_id' => $gr->id, 'purchase_order_item_id' => $poItem->id, 'material_id' => $material->id, 'quantity' => '12.500',
    ]);

    $movement = $grItem->stockMovements()->create([
        'material_id' => $material->id, 'storage_location_id' => $location->id,
        'movement_type' => MovementType::GoodsReceipt, 'quantity' => '12.500',
        'posted_by' => $user->id, 'posted_at' => now(),
    ]);

    return compact('dept', 'user', 'vendor', 'location', 'material', 'pr', 'po', 'poItem', 'gr', 'grItem', 'movement');
}

it('menelusuri rantai PR sampai PO sampai GR lewat relasi', function () {
    $c = buildProcurementChain();

    expect($c['pr']->items)->toHaveCount(1)
        ->and($c['pr']->purchaseOrder->is($c['po']))->toBeTrue()
        ->and($c['po']->purchaseRequisition->is($c['pr']))->toBeTrue()
        ->and($c['po']->vendor->is($c['vendor']))->toBeTrue()
        ->and($c['po']->goodsReceipts->first()->is($c['gr']))->toBeTrue()
        ->and($c['gr']->items->first()->is($c['grItem']))->toBeTrue()
        ->and($c['dept']->purchaseRequisitions->first()->is($c['pr']))->toBeTrue()
        ->and($c['user']->department->is($c['dept']))->toBeTrue();
});

it('meng-cast status ke enum', function () {
    $c = buildProcurementChain();

    expect($c['pr']->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and($c['po']->fresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

it('menghitung sisa PO secara eksak', function () {
    $c = buildProcurementChain();

    expect($c['poItem']->remaining_quantity)->toBe('12.500');

    $c['poItem']->update(['received_quantity' => '4.125']);

    expect($c['poItem']->fresh()->remaining_quantity)->toBe('8.375');
});

it('menghubungkan ledger ke dokumen sumber lewat relasi polimorfik', function () {
    $c = buildProcurementChain();

    expect($c['movement']->reference->is($c['grItem']))->toBeTrue()
        ->and($c['movement']->movement_type)->toBe(MovementType::GoodsReceipt);
});

it('menghubungkan baris pembalik ke baris aslinya', function () {
    $c = buildProcurementChain();

    $reversal = StockMovement::create([
        'material_id' => $c['material']->id, 'storage_location_id' => $c['location']->id,
        'movement_type' => MovementType::Reversal, 'quantity' => '-12.500',
        'reference_type' => $c['movement']->reference_type, 'reference_id' => $c['movement']->reference_id,
        'reversal_of_id' => $c['movement']->id, 'posted_by' => $c['user']->id, 'posted_at' => now(),
    ]);

    expect($c['movement']->reversal->is($reversal))->toBeTrue()
        ->and($reversal->reversedMovement->is($c['movement']))->toBeTrue();
});

it('menolak mengubah atau menghapus ledger', function () {
    $c = buildProcurementChain();

    expect(fn () => $c['movement']->update(['quantity' => '1.000']))->toThrow(LogicException::class)
        ->and(fn () => $c['movement']->delete())->toThrow(LogicException::class);
});

it('menghubungkan material dan lokasi lewat saldo stok (many to many)', function () {
    $c = buildProcurementChain();

    StockBalance::create([
        'material_id' => $c['material']->id, 'storage_location_id' => $c['location']->id, 'quantity' => '12.500',
    ]);

    $location = $c['material']->storageLocations()->first();

    expect($location->is($c['location']))->toBeTrue()
        ->and(Quantity::compare($location->pivot->quantity, '12.500'))->toBe(0)
        ->and($c['location']->materials()->first()->is($c['material']))->toBeTrue();
});

it('mendeteksi material yang sudah dipakai transaksi', function () {
    $material = Material::create(['code' => 'MAT-X', 'name' => 'Belum dipakai', 'base_uom' => 'PCS', 'qty_decimals' => 0, 'min_stock' => 0]);

    expect($material->hasTransactions())->toBeFalse();

    $c = buildProcurementChain();

    expect($c['material']->hasTransactions())->toBeTrue();
});
