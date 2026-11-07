<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spools no longer track price, location or notes. The values are deleted;
 * rolling back brings the columns back empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spools', function (Blueprint $table) {
            $table->dropColumn(['price', 'location', 'notes']);
        });
    }

    public function down(): void
    {
        Schema::table('spools', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable()->after('purchased_at');
            $table->string('location')->nullable()->after('price');
            $table->text('notes')->nullable()->after('location');
        });
    }
};
