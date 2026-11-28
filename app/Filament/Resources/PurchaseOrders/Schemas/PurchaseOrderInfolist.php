<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number'),
                TextEntry::make('purchaseRequisition.id')
                    ->label('Purchase requisition'),
                TextEntry::make('vendor.name')
                    ->label('Vendor'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('order_date')
                    ->date(),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
