<?php

use App\Enums\DocumentType;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus as PO;
use App\Enums\RequisitionStatus as PR;

// ---- Purchase Requisition ----
it('PR mengikuti transisi yang sah', function () {
    expect(PR::Draft->canTransitionTo(PR::Submitted))->toBeTrue()
        ->and(PR::Submitted->canTransitionTo(PR::Approved))->toBeTrue()
        ->and(PR::Submitted->canTransitionTo(PR::Rejected))->toBeTrue()
        ->and(PR::Rejected->canTransitionTo(PR::Draft))->toBeTrue()
        ->and(PR::Approved->canTransitionTo(PR::Converted))->toBeTrue();
});

it('PR menolak transisi yang melompati alur', function () {
    expect(PR::Draft->canTransitionTo(PR::Approved))->toBeFalse()
        ->and(PR::Draft->canTransitionTo(PR::Converted))->toBeFalse()
        ->and(PR::Submitted->canTransitionTo(PR::Draft))->toBeFalse()
        ->and(PR::Approved->canTransitionTo(PR::Rejected))->toBeFalse();
});

it('PR berstatus converted adalah final', function () {
    expect(PR::Converted->isFinal())->toBeTrue()
        ->and(PR::Converted->allowedTransitions())->toBe([]);
});

it('hanya PR draft yang boleh diedit', function () {
    expect(PR::Draft->isEditable())->toBeTrue();

    foreach ([PR::Submitted, PR::Approved, PR::Rejected, PR::Converted] as $status) {
        expect($status->isEditable())->toBeFalse();
    }
});

// ---- Purchase Order ----
it('PO mengikuti transisi yang sah', function () {
    expect(PO::Draft->canTransitionTo(PO::Issued))->toBeTrue()
        ->and(PO::Issued->canTransitionTo(PO::PartiallyReceived))->toBeTrue()
        ->and(PO::Issued->canTransitionTo(PO::Completed))->toBeTrue()
        ->and(PO::PartiallyReceived->canTransitionTo(PO::Completed))->toBeTrue();
});

it('PO yang sudah menerima barang tidak bisa dibatalkan', function () {
    expect(PO::PartiallyReceived->canTransitionTo(PO::Cancelled))->toBeFalse();
});

it('PO selesai atau dibatalkan adalah final', function () {
    expect(PO::Completed->isFinal())->toBeTrue()
        ->and(PO::Cancelled->isFinal())->toBeTrue();
});

it('Goods Receipt hanya boleh pada PO terbit atau diterima sebagian', function () {
    expect(PO::Issued->canReceiveGoods())->toBeTrue()
        ->and(PO::PartiallyReceived->canReceiveGoods())->toBeTrue()
        ->and(PO::Draft->canReceiveGoods())->toBeFalse()
        ->and(PO::Completed->canReceiveGoods())->toBeFalse()
        ->and(PO::Cancelled->canReceiveGoods())->toBeFalse();
});

// ---- Movement & dokumen ----
it('tanda ledger sesuai jenis pergerakan', function () {
    expect(MovementType::GoodsReceipt->sign())->toBe(1)
        ->and(MovementType::GoodsIssue->sign())->toBe(-1)
        ->and(MovementType::Reversal->sign())->toBeNull();
});

it('nilai enum cocok dengan yang tersimpan di database', function () {
    expect(MovementType::from('REVERSAL'))->toBe(MovementType::Reversal)
        ->and(DocumentType::from('PO'))->toBe(DocumentType::PurchaseOrder)
        ->and(PO::from('partially_received'))->toBe(PO::PartiallyReceived)
        ->and(PR::tryFrom('tidak-ada'))->toBeNull();
});
