<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secret part of the user's read-only share link; null while sharing is off.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('sso_subject');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
