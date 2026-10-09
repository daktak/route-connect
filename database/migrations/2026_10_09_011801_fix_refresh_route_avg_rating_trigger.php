<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // REFRESH MATERIALIZED VIEW CONCURRENTLY cannot run inside a trigger's
        // transaction, so refresh non-concurrently instead.
        DB::unprepared('
            CREATE OR REPLACE FUNCTION refresh_route_avg_rating()
            RETURNS TRIGGER AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW route_avg_ratings;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION refresh_route_avg_rating()
            RETURNS TRIGGER AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW CONCURRENTLY route_avg_ratings;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }
};
