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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Comunes
            $table->enum('type', ['product', 'raw_material']);
            $table->string('name')->nullable();
            $table->string('color')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('location')->nullable();

            // Solo para materia prima
            $table->string('material')->nullable();
            $table->string('supplier')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2)->nullable(); // Se calcula automáticamente

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
