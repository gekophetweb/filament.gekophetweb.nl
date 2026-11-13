<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People who signed in on a share link without being on the owner's requester list and asked to be added.
     * One per email per owner; approving adds the email to the list.
     */
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name', 100);
            $table->text('message')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
