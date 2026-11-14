<?php

use App\Enums\DocumentType;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Hapus baris ini bila Pest.php sudah menerapkan RefreshDatabase untuk folder Feature
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DocumentNumberService();
    $this->date = Carbon::create(2026, 11, 14);
});

it('menghasilkan nomor berurutan', function () {
    expect($this->service->next(DocumentType::PurchaseRequisition, $this->date))->toBe('PR-2026-0001')
        ->and($this->service->next(DocumentType::PurchaseRequisition, $this->date))->toBe('PR-2026-0002');
});

it('memisahkan urutan per tipe dokumen', function () {
    $this->service->next(DocumentType::PurchaseRequisition, $this->date);
    $this->service->next(DocumentType::PurchaseRequisition, $this->date);

    expect($this->service->next(DocumentType::PurchaseOrder, $this->date))->toBe('PO-2026-0001');
});

it('mengulang dari 0001 pada tahun baru', function () {
    $this->service->next(DocumentType::GoodsReceipt, $this->date);

    expect($this->service->next(DocumentType::GoodsReceipt, Carbon::create(2027, 1, 2)))->toBe('GR-2027-0001');
});

it('tidak melewatkan nomor bila transaksi di-rollback', function () {
    try {
        DB::transaction(function () {
            $this->service->next(DocumentType::PurchaseRequisition, $this->date);
            throw new RuntimeException('penyimpanan dokumen gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($this->service->next(DocumentType::PurchaseRequisition, $this->date))->toBe('PR-2026-0001');
});

it('memakai tahun dari tanggal dokumen, bukan tanggal hari ini', function () {
    expect($this->service->next(DocumentType::PurchaseOrder, Carbon::create(2025, 12, 31)))->toBe('PO-2025-0001');
});
