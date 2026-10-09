-- =====================================================================
-- Cycling Routes Database Schema
-- PostgreSQL 15+ with PostGIS 3.4+
-- Run as superuser or database owner
-- =====================================================================

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- =====================================================================
-- USERS TABLE
-- =====================================================================
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_users_email ON users(email);

-- =====================================================================
-- ROUTES TABLE
-- =====================================================================
CREATE TABLE routes (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    gpx_data JSONB NOT NULL DEFAULT '{}',
    geometry GEOMETRY(LINESTRING, 4326),
    distance_km DECIMAL(8,2),
    elevation_gain_m INTEGER,
    estimated_time_min INTEGER,
    difficulty VARCHAR(20) CHECK (difficulty IN ('easy', 'moderate', 'hard', 'expert')),
    is_public BOOLEAN DEFAULT TRUE,
    gpx_file_path VARCHAR(500),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Spatial index for geometry queries
CREATE INDEX idx_routes_geometry ON routes USING GIST (geometry);
CREATE INDEX idx_routes_user_id ON routes(user_id);
CREATE INDEX idx_routes_is_public ON routes(is_public) WHERE is_public = TRUE;
CREATE INDEX idx_routes_difficulty ON routes(difficulty);
CREATE INDEX idx_routes_distance ON routes(distance_km);
CREATE INDEX idx_routes_elevation ON routes(elevation_gain_m);
CREATE INDEX idx_routes_created_at ON routes(created_at DESC);

-- Full-text search on name and description
CREATE INDEX idx_routes_search ON routes USING GIN (
    to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, ''))
);

-- =====================================================================
-- ROUTE FEATURES TABLE
-- =====================================================================
CREATE TABLE route_features (
    id BIGSERIAL PRIMARY KEY,
    route_id BIGINT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    feature_type VARCHAR(50) NOT NULL,
    feature_subtype VARCHAR(50),
    description TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_route_features_route_id ON route_features(route_id);
CREATE INDEX idx_route_features_type ON route_features(feature_type);

-- Common feature types (for reference/validation)
-- gravel, steep, technical, scenic, road, water, cafe, shop, custom

-- =====================================================================
-- ROUTE RATINGS TABLE
-- =====================================================================
CREATE TABLE route_ratings (
    id BIGSERIAL PRIMARY KEY,
    route_id BIGINT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(route_id, user_id)
);

CREATE INDEX idx_route_ratings_route_id ON route_ratings(route_id);
CREATE INDEX idx_route_ratings_user_id ON route_ratings(user_id);

-- Materialized view for average ratings (refreshed via trigger or scheduled)
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
GROUP BY route_id;

CREATE UNIQUE INDEX idx_route_avg_ratings_route_id ON route_avg_ratings(route_id);

-- =====================================================================
-- ROUTE COMMENTS TABLE
-- =====================================================================
CREATE TABLE route_comments (
    id BIGSERIAL PRIMARY KEY,
    route_id BIGINT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    parent_id BIGINT REFERENCES route_comments(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_route_comments_route_id ON route_comments(route_id);
CREATE INDEX idx_route_comments_user_id ON route_comments(user_id);
CREATE INDEX idx_route_comments_parent_id ON route_comments(parent_id);
CREATE INDEX idx_route_comments_created_at ON route_comments(created_at DESC);

-- =====================================================================
-- GROUP RIDES TABLE
-- =====================================================================
CREATE TABLE group_rides (
    id BIGSERIAL PRIMARY KEY,
    route_id BIGINT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    organizer_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255),
    description TEXT,
    ride_date TIMESTAMP WITH TIME ZONE NOT NULL,
    meeting_point_lat DECIMAL(10,8),
    meeting_point_lng DECIMAL(11,8),
    meeting_point_name VARCHAR(255),
    max_participants INTEGER,
    status VARCHAR(20) DEFAULT 'planned' CHECK (status IN ('planned', 'confirmed', 'cancelled', 'completed')),
    version INTEGER DEFAULT 1 NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_group_rides_route_id ON group_rides(route_id);
CREATE INDEX idx_group_rides_organizer_id ON group_rides(organizer_id);
CREATE INDEX idx_group_rides_ride_date ON group_rides(ride_date);
CREATE INDEX idx_group_rides_status ON group_rides(status);
CREATE INDEX idx_group_rides_version ON group_rides(version);

-- =====================================================================
-- RIDE ATTENDEES TABLE
-- =====================================================================
CREATE TABLE ride_attendees (
    id BIGSERIAL PRIMARY KEY,
    group_ride_id BIGINT NOT NULL REFERENCES group_rides(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ride_version INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'tentative', 'declined')),
    note TEXT,
    joined_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(group_ride_id, user_id)
);

CREATE INDEX idx_ride_attendees_ride_id ON ride_attendees(group_ride_id);
CREATE INDEX idx_ride_attendees_user_id ON ride_attendees(user_id);
CREATE INDEX idx_ride_attendees_version ON ride_attendees(ride_version);

-- =====================================================================
-- NOTIFICATIONS TABLE (Laravel standard)
-- =====================================================================
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT NOT NULL,
    data JSONB NOT NULL,
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_notifications_notifiable ON notifications(notifiable_type, notifiable_id);
CREATE INDEX idx_notifications_read_at ON notifications(read_at) WHERE read_at IS NULL;

-- =====================================================================
-- TRIGGERS FOR UPDATED_AT TIMESTAMPS
-- =====================================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_routes_updated_at BEFORE UPDATE ON routes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_route_features_updated_at BEFORE UPDATE ON route_features FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_route_comments_updated_at BEFORE UPDATE ON route_comments FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_group_rides_updated_at BEFORE UPDATE ON group_rides FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_notifications_updated_at BEFORE UPDATE ON notifications FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =====================================================================
-- TRIGGER: AUTO-DELETE ATTENDEES ON RIDE VERSION CHANGE
-- =====================================================================
CREATE OR REPLACE FUNCTION clear_attendees_on_version_change()
RETURNS TRIGGER AS $$
BEGIN
    -- Only act if version changed and is not the initial insert
    IF OLD.version IS DISTINCT FROM NEW.version AND OLD.version IS NOT NULL THEN
        DELETE FROM ride_attendees
        WHERE group_ride_id = NEW.id
        AND ride_version < NEW.version;

        -- Log the clearing (optional: insert into audit table)
        -- INSERT INTO ride_version_changes (group_ride_id, old_version, new_version, cleared_count, changed_at)
        -- VALUES (NEW.id, OLD.version, NEW.version, cleared_count, NOW());
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_clear_attendees_on_version_change
BEFORE UPDATE ON group_rides
FOR EACH ROW
EXECUTE FUNCTION clear_attendees_on_version_change();

-- =====================================================================
-- TRIGGER: REFRESH MATERIALIZED VIEW ON RATING CHANGE
-- =====================================================================
CREATE OR REPLACE FUNCTION refresh_route_avg_rating()
RETURNS TRIGGER AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY route_avg_ratings;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_refresh_route_avg_rating
AFTER INSERT OR UPDATE OR DELETE ON route_ratings
FOR EACH STATEMENT
EXECUTE FUNCTION refresh_route_avg_rating();

-- =====================================================================
-- HELPER FUNCTIONS
-- =====================================================================

-- Get nearby routes within distance (meters) from a point
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

-- Get route stats for dashboard
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

-- =====================================================================
-- ROW LEVEL SECURITY (Optional - enable if needed)
-- =====================================================================
-- ALTER TABLE routes ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY routes_public_read ON routes FOR SELECT USING (is_public = TRUE);
-- CREATE POLICY routes_owner_write ON routes FOR ALL USING (user_id = current_user_id());

-- =====================================================================
-- SAMPLE DATA (Optional - for development)
-- =====================================================================
-- INSERT INTO users (name, email, password, email_verified_at)
-- VALUES ('Demo User', 'demo@example.com', '$2y$10$...', NOW());

-- =====================================================================
-- GRANTS (Adjust for your setup)
-- =====================================================================
-- GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO routes_app;
-- GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO routes_app;
-- GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO routes_app;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================
-- Check PostGIS version: SELECT PostGIS_Version();
-- List tables: \dt
-- Check indexes: \di
-- Check triggers: \dy+