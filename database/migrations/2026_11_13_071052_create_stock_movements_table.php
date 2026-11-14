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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->restrictOnDelete();
            $table->foreignId('storage_location_id')
                ->constrained('storage_locations')
                ->restrictOnDelete();
            $table->enum('movement_type', ['GR', 'GI', 'REVERSAL'])
                ->index();
            $table->decimal('quantity', 15, 3);

            
            $table->morphs('reference');

            
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('stock_movements')
                ->restrictOnDelete();

            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamp('created_at')->useCurrent();

            
            $table->index(['material_id', 'storage_location_id', 'posted_at'], 'stock_movements_card_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
