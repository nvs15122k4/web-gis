<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * - Sạt lở tham chiếu: thêm mức độ theo cách phân loại của QĐ 01/2011/QĐ-TTg (đặc biệt nguy hiểm / nguy hiểm /
 *   bình thường), chiều dài và địa điểm — đúng các trường bản đồ sạt lở ĐBSCL của Tổng cục PCTT công bố.
 * - View transects_styled: thêm is_significant để lọc "chỉ transect có ý nghĩa thống kê" bằng CQL_FILTER.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $db->statement("
            ALTER TABLE reference_landslide_events
                ADD COLUMN danger_level VARCHAR(30) NULL
                    CHECK (danger_level IN ('dac_biet_nguy_hiem', 'nguy_hiem', 'binh_thuong')),
                ADD COLUMN length_m DOUBLE PRECISION NULL,
                ADD COLUMN location TEXT NULL");

        // CREATE OR REPLACE VIEW chỉ cho phép thêm cột ở cuối.
        $db->statement('
            CREATE OR REPLACE VIEW transects_styled AS
            SELECT t.id, t.transect_code, t.geom, s.change_class, s.epr_m_per_year, s.lrr_m_per_year, s.is_significant
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id');
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('DROP VIEW IF EXISTS transects_styled');
        $db->statement('
            CREATE VIEW transects_styled AS
            SELECT t.id, t.transect_code, t.geom, s.change_class, s.epr_m_per_year, s.lrr_m_per_year
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id');
        $db->statement('ALTER TABLE reference_landslide_events DROP COLUMN danger_level, DROP COLUMN length_m, DROP COLUMN location');
    }
};
