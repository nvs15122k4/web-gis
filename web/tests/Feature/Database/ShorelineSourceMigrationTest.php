<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Cột `source` của shorelines và hai bảng đối chứng (migration pgsql 000007, 000008). */
class ShorelineSourceMigrationTest extends TestCase
{
    private function pg()
    {
        return DB::connection('pgsql');
    }

    private function insert(int $year, string $index, ?string $source = null): void
    {
        $cols = $source === null ? '(year, water_index, geom)' : '(year, water_index, source, geom)';
        $vals = $source === null ? '?, ?' : '?, ?, ?';
        $bind = $source === null ? [$year, $index] : [$year, $index, $source];
        $this->pg()->statement(
            "INSERT INTO shorelines {$cols} VALUES ({$vals}, ST_Multi(ST_GeomFromText('LINESTRING(105.3 10.5, 105.31 10.5)', 4326)))",
            $bind
        );
    }

    public function test_source_column_exists_not_null_with_default_main(): void
    {
        $col = $this->pg()->selectOne("SELECT is_nullable, column_default FROM information_schema.columns WHERE table_name = 'shorelines' AND column_name = 'source'");
        $this->assertNotNull($col);
        $this->assertSame('NO', $col->is_nullable);
        $this->assertStringContainsString("'main'", $col->column_default);
    }

    public function test_row_inserted_without_source_is_main(): void
    {
        $this->insert(2040, 'MNDWI');
        $this->assertSame('main', $this->pg()->table('shorelines')->where('year', 2040)->value('source'));
    }

    public function test_fixture_shorelines_are_all_main(): void
    {
        $this->assertSame(0, $this->pg()->table('shorelines')->where('source', '<>', 'main')->count());
        $this->assertGreaterThan(0, $this->pg()->table('shorelines')->where('source', 'main')->count());
    }

    public function test_same_year_and_index_allowed_for_different_sources(): void
    {
        $this->insert(2041, 'MNDWI');
        $this->insert(2041, 'MNDWI', 's2c1');
        $this->insert(2041, 'MNDWI', 'landsat');
        $this->assertSame(3, $this->pg()->table('shorelines')->where('year', 2041)->count());
    }

    public function test_duplicate_year_index_source_is_rejected(): void
    {
        $this->insert(2042, 'MNDWI', 's2c1');
        $this->expectException(QueryException::class);
        $this->insert(2042, 'MNDWI', 's2c1');
    }

    public function test_old_unique_key_on_year_and_index_is_gone_and_new_key_exists(): void
    {
        $names = $this->pg()->table('pg_constraint')->where('conrelid', DB::raw("'shorelines'::regclass"))->where('contype', 'u')->pluck('conname')->all();
        $this->assertContains('shorelines_year_water_index_source_key', $names);
        $this->assertNotContains('shorelines_year_water_index_key', $names);
    }

    public function test_alt_tables_exist_with_unique_keys(): void
    {
        $tid = $this->pg()->table('transects')->value('id');
        $this->pg()->table('transect_alt_lrr')->insert(['transect_id' => $tid, 'source' => 's2c1', 'lrr_m_per_year' => -1.5]);
        $this->pg()->table('alt_source_years')->insert(['source' => 's2c1', 'year' => 2018, 'scenes_used' => 2, 'low_confidence' => true]);
        $this->assertSame(1, $this->pg()->table('transect_alt_lrr')->count());
        $this->assertSame(1, $this->pg()->table('alt_source_years')->count());
        $this->expectException(QueryException::class);
        $this->pg()->table('transect_alt_lrr')->insert(['transect_id' => $tid, 'source' => 's2c1', 'lrr_m_per_year' => 0]);
    }

    public function test_deleting_a_transect_cascades_to_alt_lrr(): void
    {
        $tid = $this->pg()->table('transects')->value('id');
        $this->pg()->table('transect_alt_lrr')->insert(['transect_id' => $tid, 'source' => 'landsat', 'lrr_m_per_year' => 1.0]);
        $this->pg()->table('transects')->where('id', $tid)->delete();
        $this->assertSame(0, $this->pg()->table('transect_alt_lrr')->where('transect_id', $tid)->count());
    }

    public function test_migrations_are_registered(): void
    {
        $dir = base_path('database/migrations/pgsql');
        $this->assertFileExists("{$dir}/2026_11_26_000007_add_source_to_shorelines.php");
        $this->assertFileExists("{$dir}/2026_11_26_000008_create_alt_comparison_tables.php");
    }
}
