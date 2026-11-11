<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who may send print requests through the user's share link: one email, "@domain" or "*" per line.
     * Empty means the share link takes no print requests.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('print_requester_emails')->nullable()->after('emails_print_requests');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('print_requester_emails');
        });
    }
};
