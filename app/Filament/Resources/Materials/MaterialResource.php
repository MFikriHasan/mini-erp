<?php

namespace App\Filament\Resources\Materials;

use App\Filament\Resources\Materials\Pages\ManageMaterials;
use App\Models\Material;
use App\Support\Quantity;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode material')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),

                TextInput::make('name')
                    ->label('Nama material')
                    ->required()
                    ->maxLength(150),

                TextInput::make('base_uom')
                    ->label('Satuan dasar')
                    ->required()
                    ->maxLength(10)
                    ->datalist(['PCS', 'SET', 'METER', 'KG', 'LITER'])
                    ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),

                Select::make('qty_decimals')
                    ->label('Jumlah desimal kuantitas')
                    ->options([
                        0 => '0 - bilangan bulat (PCS, SET)',
                        1 => '1',
                        2 => '2 (METER)',
                        3 => '3 (KG, LITER)',
                    ])
                    ->default(0)
                    ->required()
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText('Menentukan berapa angka di belakang koma yang boleh diinput pada PR, PO, dan Goods Receipt untuk material ini.')
                    ->rules([
                        // Aturan Q4: presisi boleh dinaikkan, tidak boleh diturunkan setelah ada transaksi
                        fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($record
                                && (int) $value < (int) $record->qty_decimals
                                && $record->hasTransactions()) {
                                $fail('Jumlah desimal tidak boleh diturunkan karena material sudah dipakai transaksi.');
                            }
                        },
                    ]),

                TextInput::make('min_stock')
                    ->label('Stok minimum')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step('any')
                    ->default(0)
                    ->rules([
                        // Nol diperbolehkan; selain itu ikuti presisi material
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (! is_numeric($value) || bccomp((string) $value, '0', Quantity::SCALE) === 0) {
                                return;
                            }

                            if ($message = Quantity::error($value, (int) $get('qty_decimals'), 'Stok minimum')) {
                                $fail($message);
                            }
                        },
                    ]),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_uom')
                    ->label('Satuan')
                    ->badge()
                    ->searchable(),
                TextColumn::make('min_stock')
                    ->label('Stok minimum')
                    ->formatStateUsing(fn (string|int|float|null $state, Material $record): string => Quantity::format($state ?? 0, (int) $record->qty_decimals))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('qty_decimals')
                    ->label('Desimal')
                    ->alignCenter()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status aktif'),
                SelectFilter::make('base_uom')
                    ->label('Satuan')
                    ->options(fn (): array => Material::query()
                        ->distinct()
                        ->orderBy('base_uom')
                        ->pluck('base_uom', 'base_uom')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                // Material yang sudah dipakai transaksi tidak dihapus (cukup dinonaktifkan)
                DeleteAction::make()
                    ->hidden(fn (Material $record): bool => $record->hasTransactions()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaterials::route('/'),
        ];
    }
}
