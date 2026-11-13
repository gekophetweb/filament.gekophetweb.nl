<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The conversation on a suggestion after it was sent: replies from the owner and answers from the requester.
     */
    public function up(): void
    {
        Schema::create('suggestion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_owner');
            $table->text('body');
            $table->timestamps();

            $table->index(['suggestion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_messages');
    }
};
