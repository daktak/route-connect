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
        Schema::table('ride_attendees', function (Blueprint $table) {
            $table->dropUnique(['group_ride_id', 'user_id']);
            $table->unique(['group_ride_id', 'user_id', 'ride_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ride_attendees', function (Blueprint $table) {
            $table->dropUnique(['group_ride_id', 'user_id', 'ride_version']);
            $table->unique(['group_ride_id', 'user_id']);
        });
    }
};
