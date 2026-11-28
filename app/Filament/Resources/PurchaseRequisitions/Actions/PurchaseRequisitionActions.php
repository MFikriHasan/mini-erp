<?php

namespace App\Filament\Resources\PurchaseRequisitions\Actions;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Support\ServiceActionRunner;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequisitionService;
use App\Support\Quantity;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Tombol alur Purchase Requisition. Tombol hanya TAMPIL bila Policy mengizinkan, tetapi
 * pengamanan sebenarnya ada di service (yang memeriksa Policy lagi).
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

    /**
     * Purchasing mengubah PR yang disetujui menjadi PO: memilih vendor dan mengisi harga satuan
     * setiap item. Setelah berhasil, pengguna dibawa ke halaman PO.
     */
    public static function createPurchaseOrder(): Action
    {
        return Action::make('createPurchaseOrder')
            ->label('Buat PO')
            ->icon('heroicon-o-shopping-cart')
            ->color('primary')
            ->modalHeading('Buat Purchase Order')
            ->modalDescription('Pilih vendor dan isi harga satuan setiap item. PR akan berstatus "Menjadi PO".')
            ->modalSubmitActionLabel('Buat PO')
            ->fillForm(fn (PurchaseRequisition $record): array => [
                'order_date' => now()->toDateString(),
                'prices' => $record->items()->with('material')->get()->map(fn ($item): array => [
                    'material_id' => $item->material_id,
                    'material' => "{$item->material->code} - {$item->material->name}",
                    'quantity' => Quantity::plain($item->quantity).' '.$item->material->base_uom,
                    'unit_price' => null,
                ])->all(),
            ])
            ->schema([
                Select::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn (): array => Vendor::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('order_date')
                    ->label('Tanggal PO')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->required(),
                Repeater::make('prices')
                    ->label('Harga satuan per item')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(3)
                    ->schema([
                        Hidden::make('material_id'),
                        TextInput::make('material')
                            ->label('Material')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('unit_price')
                            ->label('Harga satuan (Rp)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01'),
                    ]),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->maxLength(1000)
                    ->rows(2),
            ])
            ->visible(fn (PurchaseRequisition $record): bool => Gate::allows('convert', $record))
            ->action(function (array $data, PurchaseRequisition $record, $livewire): void {
                $prices = collect($data['prices'] ?? [])
                    ->mapWithKeys(fn (array $row): array => [$row['material_id'] => $row['unit_price'] ?? null])
                    ->all();

                $order = null;

                $created = self::run(function () use (&$order, $record, $data, $prices): void {
                    $order = app(PurchaseOrderService::class)->createFromRequisition(
                        $record,
                        self::actor(),
                        $data['vendor_id'] ?? null,
                        $prices,
                        ['order_date' => $data['order_date'] ?? null, 'notes' => $data['notes'] ?? null],
                    );
                }, 'Purchase Order berhasil dibuat');

                if ($created && $order instanceof PurchaseOrder) {
                    $livewire->redirect(PurchaseOrderResource::getUrl('view', ['record' => $order]));
                }
            });
    }

    /** Tautan ke PO yang dihasilkan dari PR ini (tampil bila PR sudah menjadi PO). */
    public static function viewPurchaseOrder(): Action
    {
        return Action::make('viewPurchaseOrder')
            ->label('Lihat PO')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->visible(fn (PurchaseRequisition $record): bool => $record->purchaseOrder !== null
                && Gate::allows('view', $record->purchaseOrder))
            ->url(fn (PurchaseRequisition $record): ?string => $record->purchaseOrder
                ? PurchaseOrderResource::getUrl('view', ['record' => $record->purchaseOrder])
                : null);
    }

    /** Dipertahankan agar halaman Create dan Edit tidak perlu diubah. */
    public static function notifyValidation(ValidationException $exception): void
    {
        ServiceActionRunner::notifyValidation($exception);
    }

    private static function run(Closure $operation, string $successTitle, ?Closure $onSuccess = null): bool
    {
        return ServiceActionRunner::run($operation, $successTitle, $onSuccess);
    }

    private static function actor(): User
    {
        $user = Auth::user();

        assert($user instanceof User);

        return $user;
    }
}
