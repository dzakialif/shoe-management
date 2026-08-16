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
        Schema::create('shoes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')
                ->constrained(table: 'categories', column: 'id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignUlid('brand_id')
                ->constrained('brands', 'id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name', 200);
            $table->string('size', 50);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shoes');
    }
};
