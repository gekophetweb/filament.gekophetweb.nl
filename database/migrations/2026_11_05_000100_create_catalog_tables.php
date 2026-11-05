<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('filament_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('line')->nullable()->index();
            $table->string('article_name');
            $table->string('article_number')->unique();
            $table->char('color_hex', 7);
            $table->char('color_hex_2', 7)->nullable();
            $table->string('color_family')->index();
            $table->decimal('diameter', 4, 2)->default(1.75);
            $table->unsignedInteger('nominal_weight')->default(1000);
            $table->boolean('has_refill')->default(false);
            $table->string('product_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('article_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_products');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('brands');
    }
};
