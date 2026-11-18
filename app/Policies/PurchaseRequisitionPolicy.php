<?php

namespace App\Policies;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\PurchaseRequisition;
use App\Models\User;

/**
 * Siapa boleh melakukan apa pada Purchase Requisition.
 *
 * Prinsip: pemisahan tugas (segregation of duties). Pembuat PR tidak boleh
 * menyetujui PR miliknya sendiri, dan Manager tidak membuat PR.
 */
class PurchaseRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array(
            $this->roleOf($user),
            [UserRole::Admin, UserRole::Manager, UserRole::Purchasing, UserRole::Staff],
            true,
        );
    }

    public function view(User $user, PurchaseRequisition $requisition): bool
    {
        return match ($this->roleOf($user)) {
            UserRole::Admin, UserRole::Manager, UserRole::Purchasing => true,
            // Staff hanya melihat PR departemennya sendiri
            UserRole::Staff => $user->department_id !== null
                && (int) $user->department_id === (int) $requisition->department_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $this->roleOf($user) === UserRole::Staff && $user->department_id !== null;
    }

    public function update(User $user, PurchaseRequisition $requisition): bool
    {
        return $requisition->status->isEditable() && $this->isRequester($user, $requisition);
    }

    public function delete(User $user, PurchaseRequisition $requisition): bool
    {
        return $requisition->status->isEditable() && $this->isRequester($user, $requisition);
    }

    public function submit(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->isRequester($user, $requisition)
            && $requisition->status->canTransitionTo(RequisitionStatus::Submitted);
    }

    public function approve(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->canDecide($user, $requisition);
    }

    public function reject(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->canDecide($user, $requisition);
    }

    // PR yang ditolak dibuka kembali (menjadi draft) oleh pembuatnya untuk diperbaiki
    public function reopen(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->isRequester($user, $requisition)
            && $requisition->status->canTransitionTo(RequisitionStatus::Draft);
    }

    // Dipakai nanti oleh layanan pembuatan PO
    public function convert(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->roleOf($user) === UserRole::Purchasing
            && $requisition->status === RequisitionStatus::Approved;
    }

    private function canDecide(User $user, PurchaseRequisition $requisition): bool
    {
        return $this->roleOf($user) === UserRole::Manager
            && $requisition->status === RequisitionStatus::Submitted
            && ! $this->isRequester($user, $requisition);
    }

    private function isRequester(User $user, PurchaseRequisition $requisition): bool
    {
        return (int) $user->id === (int) $requisition->requested_by;
    }

    private function roleOf(User $user): ?UserRole
    {
        $role = $user->role;

        // Tahan terhadap cast yang belum terpasang (role berupa string)
        return $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
    }
}
