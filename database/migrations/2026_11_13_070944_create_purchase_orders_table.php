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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)
                ->unique();
            $table->foreignId('purchase_requisition_id')
                ->unique()
                ->constrained('purchase_requisitions')
                ->restrictOnDelete();
            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->restrictOnDelete();
            $table->enum('status', ['draft', 'issued', 'partially_received', 'completed', 'cancelled'])
                ->default('draft')
                ->index();
            $table->date('order_date');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('notes')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
