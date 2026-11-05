<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filament_product_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('sealed')->index();
            $table->boolean('is_refill')->default(false);
            $table->unsignedInteger('initial_weight');
            $table->unsignedInteger('remaining_weight');
            $table->date('purchased_at')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('spool_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spool_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('weight')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['spool_id', 'created_at']);
        });

        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool');
            $table->json('arguments')->nullable();
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
        Schema::dropIfExists('spool_events');
        Schema::dropIfExists('spools');
    }
};
