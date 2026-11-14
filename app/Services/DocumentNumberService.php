<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;


class DocumentNumberService
{
    public function next(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $year = ($date ?? now())->year;

        return DB::transaction(function () use ($type, $year) {
            
            DB::table('document_sequences')->insertOrIgnore([
                'type' => $type->value,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            
            $sequence = DocumentSequence::query()
                ->where('type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return sprintf('%s-%d-%04d', $type->value, $year, $sequence->last_number);
        });
    }
}
