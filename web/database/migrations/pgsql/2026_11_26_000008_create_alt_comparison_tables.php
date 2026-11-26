<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Số liệu của hai bản dữ liệu đối chứng độc lập (không thay thế bản chính):
 *  - transect_alt_lrr: LRR, nhóm biến động và độ lệch so với bản chính của từng transect theo từng nguồn.
 *    Bản chính vẫn nằm ở shoreline_change_stats, không chép lại để khỏi có hai nguồn sự thật.
 *  - alt_source_years: số cảnh ảnh dùng được mỗi năm và cờ độ tin cậy thấp (vd S2 C1 năm 2018, 2019, 2022).
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        $db->statement('
            CREATE TABLE transect_alt_lrr (
                id BIGSERIAL PRIMARY KEY,
                transect_id BIGINT NOT NULL REFERENCES transects(id) ON DELETE CASCADE,
                source VARCHAR(20) NOT NULL, -- s2c1 | landsat
                lrr_m_per_year DOUBLE PRECISION NULL,
                change_class VARCHAR(20) NULL, -- accretion | stable | mild_erosion | severe_erosion
                diff_vs_main DOUBLE PRECISION NULL, -- LRR đối chứng trừ LRR bản chính (m/năm)
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

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('alt_source_years');
        Schema::connection('pgsql')->dropIfExists('transect_alt_lrr');
    }
};
