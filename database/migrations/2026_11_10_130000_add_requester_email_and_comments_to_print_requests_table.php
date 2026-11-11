<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The requester's verified sign-in email (so they can follow their own requests), the owner's
     * private note, and the owner's reply that the requester sees on the share link.
     */
    public function up(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->string('requester_email')->nullable()->after('requester_contact');
            $table->text('private_note')->nullable()->after('notes');
            $table->text('reply')->nullable()->after('private_note');

            $table->index(['user_id', 'requester_email']);
        });
    }

    public function down(): void
    {
        Schema::table('print_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'requester_email']);
            $table->dropColumn(['requester_email', 'private_note', 'reply']);
        });
    }
};
