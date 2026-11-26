<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ CSDL PostGIS với phần dữ liệu đối chứng độc lập (Sentinel-2 Collection 1, Landsat 8/9):
 * cột `shorelines.source` (main | các nguồn đối chứng) và hai bảng alt_source_years, transect_alt_lrr.
 * Trước đây các đối tượng này chỉ được tạo thủ công ở CSDL local nên production (Supabase) thiếu,
 * khiến /api/shorelines và /api/summary trả "đang cập nhật". Viết theo kiểu IF NOT EXISTS để chạy lại an toàn.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        $db->statement("ALTER TABLE shorelines ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'main'");
        $db->statement('ALTER TABLE shorelines DROP CONSTRAINT IF EXISTS shorelines_year_water_index_key');
        $db->statement('
            CREATE UNIQUE INDEX IF NOT EXISTS shorelines_year_water_index_source_key
            ON shorelines (year, water_index, source)');

        $db->statement('
            CREATE TABLE IF NOT EXISTS alt_source_years (
                source VARCHAR(20) NOT NULL,
                year SMALLINT NOT NULL,
                scenes_used SMALLINT NULL,
                low_confidence BOOLEAN NOT NULL DEFAULT FALSE,
                PRIMARY KEY (source, year)
            )');

        $db->statement('
            CREATE TABLE IF NOT EXISTS transect_alt_lrr (
                id BIGSERIAL PRIMARY KEY,
                transect_id BIGINT NOT NULL REFERENCES transects(id) ON DELETE CASCADE,
                source VARCHAR(20) NOT NULL,
                lrr_m_per_year DOUBLE PRECISION NULL,
                change_class VARCHAR(20) NULL,
                diff_vs_main DOUBLE PRECISION NULL,
                UNIQUE (transect_id, source)
            )');
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('DROP TABLE IF EXISTS transect_alt_lrr');
        $db->statement('DROP TABLE IF EXISTS alt_source_years');
        $db->statement('DROP INDEX IF EXISTS shorelines_year_water_index_source_key');
        $db->statement('ALTER TABLE shorelines DROP COLUMN IF EXISTS source');
    }
};
