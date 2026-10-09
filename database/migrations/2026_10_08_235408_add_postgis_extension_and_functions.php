<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Create materialized view for average ratings
        DB::statement('
            CREATE MATERIALIZED VIEW route_avg_ratings AS
            SELECT
                route_id,
                COUNT(*) as rating_count,
                ROUND(AVG(rating)::numeric, 2) as avg_rating,
                COUNT(*) FILTER (WHERE rating = 5) as stars_5,
                COUNT(*) FILTER (WHERE rating = 4) as stars_4,
                COUNT(*) FILTER (WHERE rating = 3) as stars_3,
                COUNT(*) FILTER (WHERE rating = 2) as stars_2,
                COUNT(*) FILTER (WHERE rating = 1) as stars_1
            FROM route_ratings
            GROUP BY route_id
        ');
        DB::statement('CREATE UNIQUE INDEX idx_route_avg_ratings_route_id ON route_avg_ratings(route_id)');

        // Function to update updated_at column
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Triggers for updated_at
        foreach (['users', 'routes', 'route_features', 'route_comments', 'group_rides'] as $table) {
            DB::unprepared("
                DROP TRIGGER IF EXISTS update_{$table}_updated_at ON {$table};
                CREATE TRIGGER update_{$table}_updated_at
                BEFORE UPDATE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
            ");
        }

        // Trigger to clear attendees on ride version change
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

        DB::unprepared('
            DROP TRIGGER IF EXISTS trigger_clear_attendees_on_version_change ON group_rides;
            CREATE TRIGGER trigger_clear_attendees_on_version_change
            BEFORE UPDATE ON group_rides
            FOR EACH ROW EXECUTE FUNCTION clear_attendees_on_version_change();
        ');

        // Trigger to refresh materialized view on rating change
        DB::unprepared('
            CREATE OR REPLACE FUNCTION refresh_route_avg_rating()
            RETURNS TRIGGER AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW CONCURRENTLY route_avg_ratings;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            DROP TRIGGER IF EXISTS trigger_refresh_route_avg_rating ON route_ratings;
            CREATE TRIGGER trigger_refresh_route_avg_rating
            AFTER INSERT OR UPDATE OR DELETE ON route_ratings
            FOR EACH STATEMENT EXECUTE FUNCTION refresh_route_avg_rating();
        ');

        // Helper function: get nearby routes
        DB::unprepared('
            CREATE OR REPLACE FUNCTION get_nearby_routes(
                p_lat DOUBLE PRECISION,
                p_lng DOUBLE PRECISION,
                p_distance_meters INTEGER DEFAULT 50000,
                p_limit INTEGER DEFAULT 20
            )
            RETURNS TABLE (
                id BIGINT,
                name VARCHAR(255),
                distance_km DECIMAL(8,2),
                elevation_gain_m INTEGER,
                difficulty VARCHAR(20),
                avg_rating NUMERIC,
                bearing_deg INTEGER,
                distance_m DOUBLE PRECISION
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    r.id,
                    r.name,
                    r.distance_km,
                    r.elevation_gain_m,
                    r.difficulty,
                    COALESCE(rav.avg_rating, 0) as avg_rating,
                    ROUND(DEGREES(ST_Azimuth(
                        ST_SetSRID(ST_MakePoint(p_lng, p_lat), 4326)::geography,
                        ST_StartPoint(r.geometry)::geography
                    )))::INTEGER as bearing_deg,
                    ST_Distance(
                        ST_SetSRID(ST_MakePoint(p_lng, p_lat), 4326)::geography,
                        ST_StartPoint(r.geometry)::geography
                    ) as distance_m
                FROM routes r
                LEFT JOIN route_avg_ratings rav ON rav.route_id = r.id
                WHERE r.is_public = TRUE
                AND r.geometry IS NOT NULL
                AND ST_DWithin(
                    r.geometry::geography,
                    ST_SetSRID(ST_MakePoint(p_lng, p_lat), 4326)::geography,
                    p_distance_meters
                )
                ORDER BY distance_m
                LIMIT p_limit;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Helper function: get user route stats
        DB::unprepared('
            CREATE OR REPLACE FUNCTION get_user_route_stats(p_user_id BIGINT)
            RETURNS TABLE (
                total_routes BIGINT,
                total_distance_km DECIMAL(10,2),
                total_elevation_gain_m BIGINT,
                avg_rating NUMERIC,
                total_rides_joined BIGINT
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    COUNT(r.id) as total_routes,
                    COALESCE(SUM(r.distance_km), 0) as total_distance_km,
                    COALESCE(SUM(r.elevation_gain_m), 0) as total_elevation_gain_m,
                    COALESCE(ROUND(AVG(rav.avg_rating)::numeric, 2), 0) as avg_rating,
                    COUNT(DISTINCT ra.group_ride_id) as total_rides_joined
                FROM routes r
                LEFT JOIN route_avg_ratings rav ON rav.route_id = r.id
                LEFT JOIN ride_attendees ra ON ra.user_id = p_user_id
                WHERE r.user_id = p_user_id;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS get_user_route_stats(BIGINT)');
        DB::statement('DROP FUNCTION IF EXISTS get_nearby_routes(DOUBLE PRECISION, DOUBLE PRECISION, INTEGER, INTEGER)');
        DB::statement('DROP TRIGGER IF EXISTS trigger_refresh_route_avg_rating ON route_ratings');
        DB::statement('DROP FUNCTION IF EXISTS refresh_route_avg_rating()');
        DB::statement('DROP TRIGGER IF EXISTS trigger_clear_attendees_on_version_change ON group_rides');
        DB::statement('DROP FUNCTION IF EXISTS clear_attendees_on_version_change()');

        foreach (['users', 'routes', 'route_features', 'route_comments', 'group_rides'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS update_{$table}_updated_at ON {$table};");
        }

        DB::statement('DROP FUNCTION IF EXISTS update_updated_at_column()');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS route_avg_ratings');

        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
