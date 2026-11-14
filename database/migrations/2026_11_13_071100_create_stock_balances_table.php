<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->restrictOnDelete();
            $table->foreignId('storage_location_id')
                ->constrained('storage_locations')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3)
                ->default(0);
            $table->timestamps();

            $table->unique(['material_id', 'storage_location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
