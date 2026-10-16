<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('group_rides', function (Blueprint $table) {
            if (! Schema::hasColumn('group_rides', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_rides', function (Blueprint $table) {
            if (Schema::hasColumn('group_rides', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
