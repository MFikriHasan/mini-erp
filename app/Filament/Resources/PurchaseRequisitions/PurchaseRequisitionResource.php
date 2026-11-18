<?php

namespace App\Filament\Resources\PurchaseRequisitions;

use App\Enums\UserRole;
use App\Filament\Resources\PurchaseRequisitions\Pages\CreatePurchaseRequisition;
use App\Filament\Resources\PurchaseRequisitions\Pages\EditPurchaseRequisition;
use App\Filament\Resources\PurchaseRequisitions\Pages\ListPurchaseRequisitions;
use App\Filament\Resources\PurchaseRequisitions\Pages\ViewPurchaseRequisition;
use App\Filament\Resources\PurchaseRequisitions\Schemas\PurchaseRequisitionForm;
use App\Filament\Resources\PurchaseRequisitions\Tables\PurchaseRequisitionsTable;
use App\Models\PurchaseRequisition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return PurchaseRequisitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseRequisitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequisitions::route('/'),
            'create' => CreatePurchaseRequisition::route('/create'),
            'view' => ViewPurchaseRequisition::route('/{record}'),
            'edit' => EditPurchaseRequisition::route('/{record}/edit'),
        ];
    }

    /**
     * Staff hanya melihat PR departemennya sendiri (selaras dengan PurchaseRequisitionPolicy::view).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $role = $user?->role;
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        if ($role === UserRole::Staff) {
            $query->where('department_id', $user->department_id);
        }

        return $query;
    }
}
