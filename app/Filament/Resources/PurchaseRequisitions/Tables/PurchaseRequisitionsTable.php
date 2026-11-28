<?php

namespace App\Filament\Resources\PurchaseRequisitions\Tables;

use App\Enums\RequisitionStatus;
use App\Filament\Resources\PurchaseRequisitions\Actions\PurchaseRequisitionActions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor PR')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Departemen')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Pemohon'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('needed_date')
                    ->label('Dibutuhkan')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RequisitionStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                PurchaseRequisitionActions::approve(),
                PurchaseRequisitionActions::reject(),
                PurchaseRequisitionActions::createPurchaseOrder(),
            ]);
    }
}
