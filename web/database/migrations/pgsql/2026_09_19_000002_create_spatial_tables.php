<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 7 bảng PostGIS (Mục 3.1 kế hoạch). Laravel không có kiểu geometry gốc nên dùng raw SQL.
 * Toàn bộ geometry lưu ở EPSG:4326, chỉ mục GiST cho cột geom.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        $db->statement('
            CREATE TABLE baseline (
                id BIGSERIAL PRIMARY KEY,
                geom geometry(LineString, 4326) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW()
            )');

        $db->statement('
            CREATE TABLE shorelines (
                id BIGSERIAL PRIMARY KEY,
                year SMALLINT NOT NULL,
                water_index VARCHAR(20) NOT NULL,
                source_image_date DATE NULL,
                geom geometry(MultiLineString, 4326) NOT NULL,
                UNIQUE (year, water_index)
            )');

        $db->statement('
            CREATE TABLE transects (
                id BIGSERIAL PRIMARY KEY,
                transect_code VARCHAR(50) NOT NULL UNIQUE,
                baseline_id BIGINT NOT NULL REFERENCES baseline(id),
                geom geometry(LineString, 4326) NOT NULL
            )');

        $db->statement('
            CREATE TABLE transect_shoreline_distances (
                id BIGSERIAL PRIMARY KEY,
                transect_id BIGINT NOT NULL REFERENCES transects(id) ON DELETE CASCADE,
                year SMALLINT NOT NULL,
                distance_m DOUBLE PRECISION NOT NULL,
                UNIQUE (transect_id, year)
            )');

        $db->statement('
            CREATE TABLE shoreline_change_stats (
                id BIGSERIAL PRIMARY KEY,
                transect_id BIGINT NOT NULL UNIQUE REFERENCES transects(id) ON DELETE CASCADE,
                nsm_m DOUBLE PRECISION NULL,
                epr_m_per_year DOUBLE PRECISION NULL,
                lrr_m_per_year DOUBLE PRECISION NULL,
                se_lrr DOUBLE PRECISION NULL,
                r_squared DOUBLE PRECISION NULL,
                is_significant BOOLEAN NOT NULL DEFAULT FALSE,
                change_class VARCHAR(20) NOT NULL, -- accretion | stable | mild_erosion | severe_erosion
                consecutive_erosion_periods SMALLINT NOT NULL DEFAULT 0,
                u_shoreline_avg DOUBLE PRECISION NULL
            )');

        $db->statement('
            CREATE TABLE erosion_hotspots (
                id BIGSERIAL PRIMARY KEY,
                geom geometry(Geometry, 4326) NOT NULL,
                severity DOUBLE PRECISION NULL, -- tốc độ lùi bờ trung bình (m/năm), dùng cho lọc min_rate
                period_start_year SMALLINT NULL,
                period_end_year SMALLINT NULL,
                notes TEXT NULL
            )');

        $db->statement('
            CREATE TABLE reference_landslide_events (
                id BIGSERIAL PRIMARY KEY,
                geom geometry(Geometry, 4326) NOT NULL,
                event_date DATE NULL,
                description TEXT NULL,
                source VARCHAR(255) NULL
            )');

        foreach (['baseline', 'shorelines', 'transects', 'erosion_hotspots', 'reference_landslide_events'] as $table) {
            $db->statement("CREATE INDEX {$table}_geom_gist ON {$table} USING GIST (geom)");
        }
        $db->statement('CREATE INDEX shoreline_change_stats_class_idx ON shoreline_change_stats (change_class)');
    }

    public function down(): void
    {
        foreach ([
            'reference_landslide_events', 'erosion_hotspots', 'shoreline_change_stats',
            'transect_shoreline_distances', 'transects', 'shorelines', 'baseline',
        ] as $table) {
            Schema::connection('pgsql')->dropIfExists($table);
        }
    }
};
