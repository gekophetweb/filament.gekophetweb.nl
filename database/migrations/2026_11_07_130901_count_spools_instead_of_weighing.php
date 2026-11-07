<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spools are counted instead of weighed: a spool is boxed, on the spool holder or used up.
 * Existing weights are kept (columns become nullable) but the app no longer reads or writes them.
 */
return new class extends Migration
{
    /** @var array<string, string> old status => new status */
    private const STATUSES = ['sealed' => 'boxed', 'in_use' => 'on_spool', 'empty' => 'used_up'];

    /** @var array<string, string> old event type => new event type */
    private const EVENTS = ['emptied' => 'used_up'];

    public function up(): void
    {
        Schema::table('spools', function (Blueprint $table) {
            $table->string('status')->default('boxed')->change();
            $table->unsignedInteger('initial_weight')->nullable()->change();
            $table->unsignedInteger('remaining_weight')->nullable()->change();
        });

        $this->rename('spools', 'status', self::STATUSES);
        $this->rename('spool_events', 'type', self::EVENTS);
    }

    public function down(): void
    {
        $this->rename('spools', 'status', array_flip(self::STATUSES));
        $this->rename('spool_events', 'type', array_flip(self::EVENTS));

        DB::table('spools')->whereNull('initial_weight')->update(['initial_weight' => 1000]);
        DB::table('spools')->whereNull('remaining_weight')->update(['remaining_weight' => 0]);

        Schema::table('spools', function (Blueprint $table) {
            $table->string('status')->default('sealed')->change();
            $table->unsignedInteger('initial_weight')->nullable(false)->change();
            $table->unsignedInteger('remaining_weight')->nullable(false)->change();
        });
    }

    /** @param  array<string, string>  $map */
    private function rename(string $table, string $column, array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table($table)->where($column, $from)->update([$column => $to]);
        }
    }
};
