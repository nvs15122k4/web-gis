<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View để GeoServer publish layer `transects` có sẵn `change_class` (tô màu SLD) và chỉ số chính (GetFeatureInfo).
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        DB::connection('pgsql')->statement('
            CREATE OR REPLACE VIEW transects_styled AS
            SELECT t.id, t.transect_code, t.geom, s.change_class, s.epr_m_per_year, s.lrr_m_per_year
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP VIEW IF EXISTS transects_styled');
    }
};
