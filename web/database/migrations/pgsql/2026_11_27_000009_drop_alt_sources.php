<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gỡ đa nguồn: đề tài chỉ dùng một nguồn ảnh Sentinel-2. Bỏ hai bảng đối chứng (transect_alt_lrr, alt_source_years)
 * và cột shorelines.source; khoá duy nhất trả về (year, water_index) như trước migration 2026_11_26_000007.
 * Dữ liệu đối chiếu ba nguồn vẫn nằm ngoài CSDL, ở pipeline/data/alt và docs/doi-chieu-du-lieu.md.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('DROP TABLE IF EXISTS transect_alt_lrr');
        $db->statement('DROP TABLE IF EXISTS alt_source_years');
        // Khoá mới không chứa source: bỏ dòng đối chứng trước, nếu không sẽ trùng (year, water_index) với bản chính.
        $db->statement("DELETE FROM shorelines WHERE source <> 'main'");
        $db->statement('ALTER TABLE shorelines DROP CONSTRAINT IF EXISTS shorelines_year_water_index_source_key');
        $db->statement('ALTER TABLE shorelines DROP COLUMN source');
        $db->statement('ALTER TABLE shorelines ADD CONSTRAINT shorelines_year_water_index_key UNIQUE (year, water_index)');
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->statement("ALTER TABLE shorelines ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'main'");
        $db->statement('ALTER TABLE shorelines DROP CONSTRAINT shorelines_year_water_index_key');
        $db->statement('ALTER TABLE shorelines ADD CONSTRAINT shorelines_year_water_index_source_key UNIQUE (year, water_index, source)');
        $db->statement('
            CREATE TABLE transect_alt_lrr (
                id BIGSERIAL PRIMARY KEY,
                transect_id BIGINT NOT NULL REFERENCES transects(id) ON DELETE CASCADE,
                source VARCHAR(20) NOT NULL,
                lrr_m_per_year DOUBLE PRECISION NULL,
                change_class VARCHAR(20) NULL,
                diff_vs_main DOUBLE PRECISION NULL,
                UNIQUE (transect_id, source)
            )');
        $db->statement('
            CREATE TABLE alt_source_years (
                source VARCHAR(20) NOT NULL,
                year SMALLINT NOT NULL,
                scenes_used SMALLINT NULL,
                low_confidence BOOLEAN NOT NULL DEFAULT FALSE,
                PRIMARY KEY (source, year)
            )');
    }
};
