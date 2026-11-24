<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_rides', function (Blueprint $table) {
            $table->foreignId('recurrence_series_id')->nullable()->constrained('group_rides')->nullOnDelete();
            $table->json('recurrence_rule')->nullable();
            $table->integer('recurrence_index')->default(0);
            $table->index('recurrence_series_id');
        });
    }

    public function down(): void
    {
        Schema::table('group_rides', function (Blueprint $table) {
            $table->dropForeign(['recurrence_series_id']);
            $table->dropIndex(['recurrence_series_id']);
            $table->dropColumn(['recurrence_series_id', 'recurrence_rule', 'recurrence_index']);
        });
    }
};
