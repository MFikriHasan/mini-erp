<?php

namespace App\Filament\Resources\PurchaseRequisitions\Schemas;

use App\Enums\RequisitionStatus;
use App\Models\Material;
use App\Models\PurchaseRequisition;
use App\Rules\ValidQuantity;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi PR')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Nomor PR')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view']),

                        TextInput::make('status')
                            ->label('Status')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view'])
                            ->formatStateUsing(fn ($state): ?string => ($state instanceof RequisitionStatus
                                ? $state
                                : RequisitionStatus::tryFrom((string) $state))?->getLabel()),

                        TextInput::make('department_name')
                            ->label('Departemen')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view'])
                            ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($record?->department?->name)),

                        TextInput::make('requester_name')
                            ->label('Pemohon')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn(['edit', 'view'])
                            ->afterStateHydrated(fn (TextInput $component, ?Model $record) => $component->state($record?->requester?->name)),

                        DatePicker::make('needed_date')
                            ->label('Tanggal dibutuhkan')
                            ->native(false)
                            ->displayFormat('d M Y'),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(fn (?Model $record): bool => filled($record?->rejection_reason)),
                    ]),

                Section::make('Item material')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->required()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Tambah material')
                            ->columns(4)
                            ->schema([
                                Select::make('material_id')
                                    ->label('Material')
                                    ->options(fn (): array => Material::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (Material $material): array => [$material->id => self::materialLabel($material)])
                                        ->all())
                                    ->getOptionLabelUsing(fn ($value): ?string => ($material = Material::find($value))
                                        ? self::materialLabel($material)
                                        : null)
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(2),

                                TextInput::make('quantity')
                                    ->label('Kuantitas')
                                    ->required()
                                    ->numeric()
                                    ->step('any')
                                    ->suffix(fn (Get $get): ?string => Material::find($get('material_id'))?->base_uom)
                                    ->helperText(function (Get $get): ?string {
                                        $material = Material::find($get('material_id'));

                                        if (! $material) {
                                            return null;
                                        }

                                        return (int) $material->qty_decimals === 0
                                            ? 'Hanya bilangan bulat.'
                                            : "Maksimal {$material->qty_decimals} angka di belakang koma.";
                                    })
                                    ->rules([fn (Get $get): ValidQuantity => new ValidQuantity($get('material_id'))]),

                                TextInput::make('notes')
                                    ->label('Keterangan')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    /**
     * Mengisi state repeater `items` dari relasi untuk halaman edit dan lihat.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillItems(array $data, PurchaseRequisition $record): array
    {
        $data['items'] = $record->items()->get()->map(function ($item): array {
            $quantity = (string) $item->quantity;

            // 12.500 tampil sebagai 12.5 dan 10.000 sebagai 10
            if (str_contains($quantity, '.')) {
                $quantity = rtrim(rtrim($quantity, '0'), '.');
            }

            return [
                'material_id' => $item->material_id,
                'quantity' => $quantity,
                'notes' => $item->notes,
            ];
        })->all();

        return $data;
    }

    private static function materialLabel(Material $material): string
    {
        return "{$material->code} - {$material->name} ({$material->base_uom})";
    }
}
