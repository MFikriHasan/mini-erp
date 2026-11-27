<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * PO tidak dibuat atau diubah lewat form biasa: dibuat dari PR yang disetujui
 * (PurchaseOrderService), lalu hanya diterbitkan atau dibatalkan oleh Purchasing.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canSee($user);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->canSee($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return false;
    }

    public function issue(User $user, PurchaseOrder $order): bool
    {
        return $this->roleOf($user) === UserRole::Purchasing
            && $order->status->canTransitionTo(PurchaseOrderStatus::Issued);
    }

    // Tidak dapat dibatalkan setelah ada barang yang diterima
    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $this->roleOf($user) === UserRole::Purchasing
            && $order->status->canTransitionTo(PurchaseOrderStatus::Cancelled);
    }

    // Dipakai nanti oleh layanan Goods Receipt
    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $this->roleOf($user) === UserRole::Warehouse
            && $order->status->canReceiveGoods();
    }

    private function canSee(User $user): bool
    {
        return in_array(
            $this->roleOf($user),
            [UserRole::Admin, UserRole::Manager, UserRole::Purchasing, UserRole::Warehouse],
            true,
        );
    }

    private function roleOf(User $user): ?UserRole
    {
        $role = $user->role;

        // Tahan terhadap cast yang belum terpasang (role berupa string)
        return $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
    }
}
