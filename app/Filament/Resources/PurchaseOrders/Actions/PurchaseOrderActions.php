<?php

namespace App\Filament\Resources\PurchaseOrders\Actions;

use App\Filament\Support\ServiceActionRunner;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Closure;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Tombol Terbitkan dan Batalkan PO (tampil sesuai Policy, ditegakkan oleh PurchaseOrderService). */
class PurchaseOrderActions
{
    public static function issue(?Closure $onSuccess = null): Action
    {
        return Action::make('issue')
            ->label('Terbitkan')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Terbitkan Purchase Order?')
            ->modalDescription('PO yang diterbitkan siap menerima barang dari vendor.')
            ->visible(fn (PurchaseOrder $record): bool => Gate::allows('issue', $record))
            ->action(fn (PurchaseOrder $record) => ServiceActionRunner::run(
                fn () => app(PurchaseOrderService::class)->issue($record, self::actor()),
                'PO diterbitkan',
                $onSuccess,
            ));
    }

    public static function cancel(?Closure $onSuccess = null): Action
    {
        return Action::make('cancel')
            ->label('Batalkan')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Batalkan Purchase Order?')
            ->modalDescription('PR asal tetap berstatus "Menjadi PO" dan tidak dapat dibuatkan PO baru.')
            ->visible(fn (PurchaseOrder $record): bool => Gate::allows('cancel', $record))
            ->action(fn (PurchaseOrder $record) => ServiceActionRunner::run(
                fn () => app(PurchaseOrderService::class)->cancel($record, self::actor()),
                'PO dibatalkan',
                $onSuccess,
            ));
    }

    private static function actor(): User
    {
        $user = Auth::user();

        assert($user instanceof User);

        return $user;
    }
}
