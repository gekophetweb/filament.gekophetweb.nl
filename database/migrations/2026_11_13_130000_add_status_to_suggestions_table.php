<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the owner did with a suggestion (open, planned, done or declined); the requester sees it too.
     */
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->string('status')->default('open')->index()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
