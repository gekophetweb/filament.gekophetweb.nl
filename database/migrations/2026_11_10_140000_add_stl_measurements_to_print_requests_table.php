<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The uploaded STL's volume (mm³) and bounding box (mm, [x, y, z]), measured when the request comes in,
     * for an estimate of the filament it takes.
     */
    public function up(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->double('stl_volume')->nullable()->after('stl_size');
            $table->json('stl_dimensions')->nullable()->after('stl_volume');
        });
    }

    public function down(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->dropColumn(['stl_volume', 'stl_dimensions']);
        });
    }
};
