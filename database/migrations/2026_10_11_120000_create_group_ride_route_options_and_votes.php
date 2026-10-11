<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_ride_route_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_ride_id', 'route_id']);
            $table->index('group_ride_id');
            $table->index('route_id');
        });

        Schema::create('ride_route_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_ride_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('ride_version');
            $table->timestamps();

            $table->unique(['group_ride_id', 'user_id', 'ride_version']);
            $table->index('group_ride_id');
            $table->index('route_id');
            $table->index('user_id');
            $table->index('ride_version');
        });

        // Backfill one option per existing ride so single-route rides keep working.
        DB::statement('
            INSERT INTO group_ride_route_options (group_ride_id, route_id, sort_order, created_at, updated_at)
            SELECT id, route_id, 0, NOW(), NOW()
            FROM group_rides
            WHERE route_id IS NOT NULL
        ');

        // Also clear stale votes when a ride version changes.
        DB::unprepared('
            CREATE OR REPLACE FUNCTION clear_attendees_on_version_change()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.version IS DISTINCT FROM NEW.version AND OLD.version IS NOT NULL THEN
                    DELETE FROM ride_attendees
                    WHERE group_ride_id = NEW.id
                    AND ride_version < NEW.version;

                    DELETE FROM ride_route_votes
                    WHERE group_ride_id = NEW.id
                    AND ride_version < NEW.version;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION clear_attendees_on_version_change()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.version IS DISTINCT FROM NEW.version AND OLD.version IS NOT NULL THEN
                    DELETE FROM ride_attendees
                    WHERE group_ride_id = NEW.id
                    AND ride_version < NEW.version;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        Schema::dropIfExists('ride_route_votes');
        Schema::dropIfExists('group_ride_route_options');
    }
};
