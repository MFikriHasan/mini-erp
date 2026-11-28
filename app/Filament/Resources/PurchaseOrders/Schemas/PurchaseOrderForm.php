<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Support\Quantity;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Tampilan baca-saja PO (halaman View menonaktifkan seluruh field). */
class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Nomor PO'),

                        TextInput::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn ($state): ?string => ($state instanceof PurchaseOrderStatus
                                ? $state
                                : PurchaseOrderStatus::tryFrom((string) $state))?->getLabel()),

                        TextInput::make('vendor_name')
                            ->label('Vendor')
                            ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($record?->vendor?->name)),

                        TextInput::make('requisition_number')
                            ->label('Asal PR')
                            ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($record?->purchaseRequisition?->number)),

                        DatePicker::make('order_date')
                            ->label('Tanggal PO')
                            ->native(false)
                            ->displayFormat('d M Y'),

                        TextInput::make('creator_name')
                            ->label('Dibuat oleh')
                            ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($record?->creator?->name)),

                        TextInput::make('total')
                            ->label('Total nilai PO'),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Item')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(5)
                            ->schema([
                                TextInput::make('material')->label('Material'),
                                TextInput::make('quantity')->label('Dipesan'),
                                TextInput::make('received_quantity')->label('Diterima'),
                                TextInput::make('remaining_quantity')->label('Sisa'),
                                TextInput::make('unit_price')->label('Harga satuan'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Mengisi state tampilan (item dan total) dari relasi.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillItems(array $data, PurchaseOrder $record): array
    {
        $items = $record->items()->with('material')->get();

        $data['items'] = $items->map(fn ($item): array => [
            'material' => "{$item->material->code} - {$item->material->name}",
            'quantity' => Quantity::plain($item->quantity).' '.$item->material->base_uom,
            'received_quantity' => Quantity::plain($item->received_quantity).' '.$item->material->base_uom,
            'remaining_quantity' => Quantity::plain($item->remaining_quantity).' '.$item->material->base_uom,
            'unit_price' => self::rupiah($item->unit_price),
        ])->all();

        $data['total'] = self::total($record);

        return $data;
    }

    /** Total nilai PO, dihitung dengan bcmath dari kuantitas dan harga tiap item. */
    public static function total(PurchaseOrder $order): string
    {
        $total = '0';

        foreach ($order->items as $item) {
            $total = bcadd($total, bcmul((string) $item->quantity, (string) $item->unit_price, 5), 5);
        }

        return self::rupiah($total);
    }

    private static function rupiah(string|int|float|null $value): string
    {
        return 'Rp '.number_format((float) $value, 2, ',', '.');
    }
}
