<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Print requests that visitors send through a share link: an STL file or a link to a model,
     * plus up to four of the owner's filaments to print it in.
     */
    public function up(): void
    {
        Schema::create('print_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('requester_name', 100);
            $table->string('requester_contact')->nullable();
            $table->string('model_url', 2048)->nullable();
            $table->string('stl_path')->nullable();
            $table->string('stl_original_name')->nullable();
            $table->unsignedBigInteger('stl_size')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('filament_product_print_request', function (Blueprint $table) {
            $table->foreignId('print_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filament_product_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');

            $table->primary(['print_request_id', 'filament_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_product_print_request');
        Schema::dropIfExists('print_requests');
    }
};
