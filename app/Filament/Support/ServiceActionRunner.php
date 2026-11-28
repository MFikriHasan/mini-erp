<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Menjalankan operasi service dari tombol Filament dan menerjemahkan kegagalannya
 * (validasi atau akses) menjadi notifikasi, bukan halaman error.
 */
final class ServiceActionRunner
{
    /** @return bool true bila operasi berhasil */
    public static function run(Closure $operation, string $successTitle, ?Closure $onSuccess = null): bool
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            self::notifyValidation($exception);

            return false;
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title('Tindakan tidak diizinkan')
                ->body('Anda tidak berwenang melakukan tindakan ini pada dokumen dengan status saat ini.')
                ->send();

            return false;
        }

        Notification::make()->success()->title($successTitle)->send();

        if ($onSuccess) {
            $onSuccess();
        }

        return true;
    }

    public static function notifyValidation(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Data tidak valid')
            ->body(collect($exception->errors())->flatten()->implode(' '))
            ->send();
    }
}
