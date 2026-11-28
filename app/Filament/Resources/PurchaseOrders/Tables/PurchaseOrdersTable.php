<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Actions\PurchaseOrderActions;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Models\PurchaseOrder;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable(),
                TextColumn::make('purchaseRequisition.number')
                    ->label('Asal PR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (PurchaseOrder $record): string => PurchaseOrderForm::total($record))
                    ->alignEnd(),
                TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                PurchaseOrderActions::issue(),
                PurchaseOrderActions::cancel(),
            ]);
    }
}
