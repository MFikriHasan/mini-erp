<?php

namespace App\Filament\Resources\PurchaseRequisitions\Actions;

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Services\PurchaseRequisitionService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Tombol alur persetujuan PR. Tombol hanya TAMPIL bila Policy mengizinkan, tetapi
 * pengamanan sebenarnya ada di PurchaseRequisitionService (yang memeriksa Policy lagi).
 *
 * Setiap method menerima callback opsional $onSuccess, dipakai halaman untuk
 * menyegarkan tampilan setelah status berubah.
 */
class PurchaseRequisitionActions
{
    public static function submit(?Closure $onSuccess = null): Action
    {
        return Action::make('submit')
            ->label('Ajukan')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Ajukan Purchase Requisition?')
            ->modalDescription('Setelah diajukan, PR tidak dapat diubah sampai Manager memutuskan.')
            ->visible(fn (PurchaseRequisition $record): bool => Gate::allows('submit', $record))
            ->action(fn (PurchaseRequisition $record) => self::run(
                fn () => app(PurchaseRequisitionService::class)->submit($record, self::actor()),
                'PR berhasil diajukan',
                $onSuccess,
            ));
    }

    public static function approve(?Closure $onSuccess = null): Action
    {
        return Action::make('approve')
            ->label('Setujui')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Setujui Purchase Requisition?')
            ->visible(fn (PurchaseRequisition $record): bool => Gate::allows('approve', $record))
            ->action(fn (PurchaseRequisition $record) => self::run(
                fn () => app(PurchaseRequisitionService::class)->approve($record, self::actor()),
                'PR disetujui',
                $onSuccess,
            ));
    }

    public static function reject(?Closure $onSuccess = null): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Tolak Purchase Requisition')
            ->modalSubmitActionLabel('Tolak PR')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan penolakan')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->visible(fn (PurchaseRequisition $record): bool => Gate::allows('reject', $record))
            ->action(fn (array $data, PurchaseRequisition $record) => self::run(
                fn () => app(PurchaseRequisitionService::class)->reject($record, self::actor(), (string) $data['reason']),
                'PR ditolak',
                $onSuccess,
            ));
    }

    public static function reopen(?Closure $onSuccess = null): Action
    {
        return Action::make('reopen')
            ->label('Buka kembali')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Buka kembali PR yang ditolak?')
            ->modalDescription('PR kembali menjadi draft agar dapat diperbaiki dan diajukan ulang.')
            ->visible(fn (PurchaseRequisition $record): bool => Gate::allows('reopen', $record))
            ->action(fn (PurchaseRequisition $record) => self::run(
                fn () => app(PurchaseRequisitionService::class)->reopen($record, self::actor()),
                'PR dibuka kembali sebagai draft',
                $onSuccess,
            ));
    }

    /** Menampilkan kesalahan validasi service sebagai notifikasi. */
    public static function notifyValidation(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Data tidak valid')
            ->body(collect($exception->errors())->flatten()->implode(' '))
            ->send();
    }

    private static function run(Closure $operation, string $successTitle, ?Closure $onSuccess): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            self::notifyValidation($exception);

            return;
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title('Tindakan tidak diizinkan')
                ->body('Anda tidak berwenang melakukan tindakan ini pada PR dengan status saat ini.')
                ->send();

            return;
        }

        Notification::make()->success()->title($successTitle)->send();

        if ($onSuccess) {
            $onSuccess();
        }
    }

    private static function actor(): User
    {
        $user = Auth::user();

        assert($user instanceof User);

        return $user;
    }
}
