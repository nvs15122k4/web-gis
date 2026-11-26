<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cột `source` cho bảng shorelines để chứa thêm đường bờ của các bản dữ liệu đối chứng độc lập
 * (Sentinel-2 Collection 1 = 's2c1', Landsat 8/9 = 'landsat') bên cạnh bản chính ('main').
 * Mặc định 'main' nên mọi dòng cũ và mọi đoạn mã ghi không nêu nguồn vẫn là bản chính; khoá duy nhất đổi
 * từ (year, water_index) sang (year, water_index, source) để cùng năm/chỉ số có thể có nhiều nguồn.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $db->statement("ALTER TABLE shorelines ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'main'");
        $db->statement('ALTER TABLE shorelines DROP CONSTRAINT shorelines_year_water_index_key');
        $db->statement('ALTER TABLE shorelines ADD CONSTRAINT shorelines_year_water_index_source_key UNIQUE (year, water_index, source)');
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        // Khoá cũ không chứa source: phải bỏ dòng đối chứng trước, nếu không sẽ trùng (year, water_index) với bản chính.
        $db->statement("DELETE FROM shorelines WHERE source <> 'main'");
        $db->statement('ALTER TABLE shorelines DROP CONSTRAINT shorelines_year_water_index_source_key');
        $db->statement('ALTER TABLE shorelines ADD CONSTRAINT shorelines_year_water_index_key UNIQUE (year, water_index)');
        $db->statement('ALTER TABLE shorelines DROP COLUMN source');
    }
};
