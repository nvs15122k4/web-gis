-- WebGIS Sông Hậu — CSDL không gian (PostgreSQL 14+ / PostGIS 3+)
-- Tạo DB:  createdb -U postgres webgis_spatial
-- Chạy:    psql -U postgres -d webgis_spatial -f database/sql/postgres.sql
-- Lưu ý: dùng file này HOẶC "php artisan migrate", không dùng cả hai trên cùng một DB.
-- Toàn bộ geometry lưu ở EPSG:4326.

CREATE EXTENSION IF NOT EXISTS postgis;

-- Một đường cơ sở song song xu hướng bờ sông, dùng để sinh transect
CREATE TABLE baseline (
    id BIGSERIAL PRIMARY KEY,
    geom geometry(LineString, 4326) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Đường bờ theo từng năm 2018–2026
CREATE TABLE shorelines (
    id BIGSERIAL PRIMARY KEY,
    year SMALLINT NOT NULL,
    water_index VARCHAR(20) NOT NULL,           -- NDWI | MNDWI | AWEI
    source_image_date DATE NULL,
    geom geometry(MultiLineString, 4326) NOT NULL,
    UNIQUE (year, water_index)
);

-- Mặt cắt vuông góc baseline
CREATE TABLE transects (
    id BIGSERIAL PRIMARY KEY,
    transect_code VARCHAR(50) NOT NULL UNIQUE,
    baseline_id BIGINT NOT NULL REFERENCES baseline(id),
    geom geometry(LineString, 4326) NOT NULL
);

-- Khoảng cách baseline → bờ theo năm (nguồn cho Chart.js)
CREATE TABLE transect_shoreline_distances (
    id BIGSERIAL PRIMARY KEY,
    transect_id BIGINT NOT NULL REFERENCES transects(id) ON DELETE CASCADE,
    year SMALLINT NOT NULL,
    distance_m DOUBLE PRECISION NOT NULL,
    UNIQUE (transect_id, year)
);

-- Kết quả tính sẵn từ pipeline Python (Laravel chỉ đọc)
CREATE TABLE shoreline_change_stats (
    id BIGSERIAL PRIMARY KEY,
    transect_id BIGINT NOT NULL UNIQUE REFERENCES transects(id) ON DELETE CASCADE,
    nsm_m DOUBLE PRECISION NULL,
    epr_m_per_year DOUBLE PRECISION NULL,
    lrr_m_per_year DOUBLE PRECISION NULL,
    se_lrr DOUBLE PRECISION NULL,
    r_squared DOUBLE PRECISION NULL,
    is_significant BOOLEAN NOT NULL DEFAULT FALSE,
    change_class VARCHAR(20) NOT NULL
        CHECK (change_class IN ('accretion', 'stable', 'mild_erosion', 'severe_erosion')),
    consecutive_erosion_periods SMALLINT NOT NULL DEFAULT 0,
    u_shoreline_avg DOUBLE PRECISION NULL
);

-- Đoạn bờ tổng hợp từ các transect "lùi bờ mạnh" liên tiếp
CREATE TABLE erosion_hotspots (
    id BIGSERIAL PRIMARY KEY,
    geom geometry(Geometry, 4326) NOT NULL,
    severity DOUBLE PRECISION NULL,             -- tốc độ lùi bờ trung bình (m/năm), dùng cho lọc min_rate
    period_start_year SMALLINT NULL,
    period_end_year SMALLINT NULL,
    notes TEXT NULL
);

-- Sạt lở đã công bố 2019–2020, chỉ để đối chiếu (không dùng huấn luyện)
CREATE TABLE reference_landslide_events (
    id BIGSERIAL PRIMARY KEY,
    geom geometry(Geometry, 4326) NOT NULL,
    event_date DATE NULL,
    description TEXT NULL,
    source VARCHAR(255) NULL,
    -- mức độ theo QĐ 01/2011/QĐ-TTg; NULL = chưa phân loại (giao diện ghi "Đang cập nhật")
    danger_level VARCHAR(30) NULL CHECK (danger_level IN ('dac_biet_nguy_hiem', 'nguy_hiem', 'binh_thuong')),
    length_m DOUBLE PRECISION NULL,
    location TEXT NULL
);

-- Chỉ mục không gian và chỉ mục lọc
CREATE INDEX baseline_geom_gist ON baseline USING GIST (geom);
CREATE INDEX shorelines_geom_gist ON shorelines USING GIST (geom);
CREATE INDEX transects_geom_gist ON transects USING GIST (geom);
CREATE INDEX erosion_hotspots_geom_gist ON erosion_hotspots USING GIST (geom);
CREATE INDEX reference_landslide_events_geom_gist ON reference_landslide_events USING GIST (geom);
CREATE INDEX shoreline_change_stats_class_idx ON shoreline_change_stats (change_class);
