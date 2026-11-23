<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Khoảng tin cậy 95% của LRR (nửa bề rộng, m/năm) tính bằng phân phối Student t với bậc tự do n − 2 của từng
 * transect. Lưu sẵn từ pipeline để web không phải tự suy ra hệ số t (trước đây web hiển thị 1,96·SE, lệch với
 * tiêu chí ý nghĩa thống kê của pipeline). View transects_styled thêm unc_lrr ở cuối để WFS/CQL_FILTER dùng được.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('ALTER TABLE shoreline_change_stats ADD COLUMN unc_lrr DOUBLE PRECISION NULL');
        $db->statement('
            CREATE OR REPLACE VIEW transects_styled AS
            SELECT t.id, t.transect_code, t.geom, s.change_class, s.epr_m_per_year, s.lrr_m_per_year, s.is_significant, s.unc_lrr
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id');
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->statement('DROP VIEW IF EXISTS transects_styled');
        $db->statement('
            CREATE VIEW transects_styled AS
            SELECT t.id, t.transect_code, t.geom, s.change_class, s.epr_m_per_year, s.lrr_m_per_year, s.is_significant
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id');
        $db->statement('ALTER TABLE shoreline_change_stats DROP COLUMN unc_lrr');
    }
};
