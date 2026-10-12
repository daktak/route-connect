<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_rides', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('ride_date');
        });

        // Reinterpret stored wall clock as UTC (existing values keep their digits).
        DB::statement('ALTER TABLE group_rides ALTER COLUMN ride_date TYPE timestamp with time zone USING ride_date AT TIME ZONE \'UTC\'');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE group_rides ALTER COLUMN ride_date TYPE timestamp USING ride_date AT TIME ZONE \'UTC\'');
        Schema::table('group_rides', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
