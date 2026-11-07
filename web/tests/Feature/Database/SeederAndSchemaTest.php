<?php

namespace Tests\Feature\Database;

use App\Models\ThresholdConfig;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\E2eFixtureSeeder;
use Database\Seeders\ThresholdConfigSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeederAndSchemaTest extends TestCase
{
    private function pg(): \Illuminate\Database\Connection
    {
        return \DB::connection('pgsql');
    }

    // ---- Seeder ----

    public function test_database_seeder_is_idempotent(): void
    {
        $u = User::count();
        $t = ThresholdConfig::count();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($u, User::count());
        $this->assertSame($t, ThresholdConfig::count());
    }

    public function test_fixture_seeder_is_idempotent_for_spatial_data(): void
    {
        $this->seed(E2eFixtureSeeder::class);
        $this->seed(E2eFixtureSeeder::class);
        foreach (['transects' => 10, 'shorelines' => 8, 'erosion_hotspots' => 2, 'reference_landslide_events' => 2, 'shoreline_change_stats' => 10, 'baseline' => 1] as $table => $n) {
            $this->assertSame($n, $this->pg()->table($table)->count(), $table);
        }
    }

    public function test_fixture_seeder_distance_rows_account_for_gaps(): void
    {
        // 10 transect x 8 năm - 2 (T05 thiếu 2020, 2021)
        $this->assertSame(78, $this->pg()->table('transect_shoreline_distances')->count());
        $t5 = $this->pg()->table('transects')->where('transect_code', 'T05')->value('id');
        $this->assertSame(6, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $t5)->count());
    }

    public function test_fixture_has_no_2022_anywhere(): void
    {
        $this->assertSame(0, $this->pg()->table('shorelines')->where('year', 2022)->count());
        $this->assertSame(0, $this->pg()->table('transect_shoreline_distances')->where('year', 2022)->count());
    }

    public function test_fixture_years_constant_matches_db(): void
    {
        $this->assertSame(E2eFixtureSeeder::YEARS, $this->pg()->table('shorelines')->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all());
    }

    public function test_fixture_class_distribution(): void
    {
        $d = $this->pg()->table('shoreline_change_stats')->selectRaw('change_class, count(*) n')->groupBy('change_class')->pluck('n', 'change_class')->all();
        $this->assertEquals(['accretion' => 2, 'stable' => 3, 'mild_erosion' => 2, 'severe_erosion' => 3], $d);
    }

    public function test_fixture_geometries_are_valid_and_srid_4326(): void
    {
        foreach (['transects', 'shorelines', 'erosion_hotspots', 'reference_landslide_events', 'baseline'] as $t) {
            $r = $this->pg()->selectOne("SELECT bool_and(ST_IsValid(geom)) v, min(ST_SRID(geom)) a, max(ST_SRID(geom)) b FROM {$t}");
            $this->assertTrue($r->v, $t);
            $this->assertSame(4326, $r->a);
            $this->assertSame(4326, $r->b);
        }
    }

    public function test_fixture_reference_events_only_in_north(): void
    {
        $lat = $this->pg()->selectOne('SELECT max(ST_Y(geom)) m FROM reference_landslide_events')->m;
        $south = $this->pg()->selectOne("SELECT ST_Y(ST_StartPoint(geom)) y FROM transects WHERE transect_code = 'T08'")->y;
        $this->assertLessThan($south, $lat);
    }

    public function test_seeded_admin_has_hashed_password_and_admin_role(): void
    {
        $a = User::where('email', 'admin@webgis.test')->first();
        $this->assertTrue($a->isAdmin());
        $this->assertTrue(Hash::check('password', $a->password));
        $this->assertNotSame('password', $a->getRawOriginal('password'));
    }

    public function test_viewer_seed_is_not_admin(): void
    {
        $this->assertFalse(User::where('email', 'viewer@webgis.test')->first()->isAdmin());
    }

    public function test_reseeding_resets_admin_password_and_role(): void
    {
        User::where('email', 'admin@webgis.test')->update(['role' => 'viewer']);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('admin', User::where('email', 'admin@webgis.test')->value('role'));
    }

    public function test_reseeding_overwrites_customised_threshold_value(): void
    {
        // Ghi nhận: seeder dùng updateOrCreate nên ghi đè giá trị admin đã chỉnh.
        ThresholdConfig::where('key', 'min_erosion_rate')->update(['value' => '9']);
        $this->seed(ThresholdConfigSeeder::class);
        $this->assertSame('1.0', ThresholdConfig::where('key', 'min_erosion_rate')->value('value'));
    }

    public function test_threshold_seeder_description_is_vietnamese_with_diacritics(): void
    {
        $this->assertMatchesRegularExpression('/[ốờớềạ]/u', ThresholdConfig::where('key', 'min_erosion_rate')->value('description'));
    }

    // ---- Migrations / schema ----

    public static function mysqlTables(): array
    {
        return array_map(fn ($t) => [$t], ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'threshold_configs', 'activity_logs', 'personal_access_tokens', 'migrations']);
    }

    #[DataProvider('mysqlTables')]
    public function test_mysql_table_exists(string $t): void
    {
        $this->assertTrue(Schema::connection('mysql')->hasTable($t));
    }

    public static function pgTables(): array
    {
        return array_map(fn ($t) => [$t], ['baseline', 'shorelines', 'transects', 'transect_shoreline_distances', 'shoreline_change_stats', 'erosion_hotspots', 'reference_landslide_events']);
    }

    #[DataProvider('pgTables')]
    public function test_postgis_table_exists_with_gist_index_on_geom(string $t): void
    {
        $this->assertTrue(Schema::connection('pgsql')->hasTable($t));
        if ($t === 'transect_shoreline_distances' || $t === 'shoreline_change_stats') {
            $this->assertTrue(true);

            return;
        }
        $idx = $this->pg()->select('SELECT indexdef FROM pg_indexes WHERE tablename = ?', [$t]);
        $this->assertTrue((bool) array_filter($idx, fn ($i) => stripos($i->indexdef, 'gist') !== false), "{$t} thiếu GiST");
    }

    public function test_postgis_extension_enabled(): void
    {
        $this->assertNotNull($this->pg()->selectOne("SELECT 1 x FROM pg_extension WHERE extname = 'postgis'"));
        $this->assertNotEmpty($this->pg()->selectOne('SELECT PostGIS_Version() v')->v);
    }

    public function test_pgsql_migrations_are_recorded(): void
    {
        $names = \DB::connection('mysql')->table('migrations')->pluck('migration')->all();
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_contains($n, 'create_threshold_configs_table')));
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_contains($n, 'personal_access_tokens')));
    }

    public function test_every_migration_file_has_down_method(): void
    {
        foreach (array_merge(glob(database_path('migrations/*.php')), glob(database_path('migrations/pgsql/*.php'))) as $f) {
            $this->assertStringContainsString('function down', file_get_contents($f), basename($f));
        }
    }

    public function test_every_migration_file_is_recorded_as_ran(): void
    {
        $ran = \DB::connection('mysql')->table('migrations')->pluck('migration')->all();
        foreach (glob(database_path('migrations/*.php')) as $f) {
            $this->assertContains(basename($f, '.php'), $ran);
        }
    }

    public function test_seeders_do_not_use_raw_insert_on_mysql_models(): void
    {
        foreach (['DatabaseSeeder', 'ThresholdConfigSeeder'] as $s) {
            $src = file_get_contents(database_path("seeders/{$s}.php"));
            $this->assertStringNotContainsString('->insert(', $src);
            $this->assertStringContainsString('updateOrCreate', $src);
        }
    }

    // ---- Constraints ----

    public function test_unique_transect_code(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $b = $this->pg()->table('baseline')->value('id');
        $this->pg()->statement("INSERT INTO transects (transect_code, baseline_id, geom) VALUES ('T01', ?, ST_GeomFromText('LINESTRING(0 0,1 1)',4326))", [$b]);
    }

    public function test_unique_year_water_index_for_shorelines(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->statement("INSERT INTO shorelines (year, water_index, geom) VALUES (2018, 'MNDWI', ST_Multi(ST_GeomFromText('LINESTRING(0 0,1 1)',4326)))");
    }

    public function test_geometry_type_is_enforced(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $b = $this->pg()->table('baseline')->value('id');
        $this->pg()->statement("INSERT INTO transects (transect_code, baseline_id, geom) VALUES ('BAD', ?, ST_GeomFromText('POINT(1 1)',4326))", [$b]);
    }

    public function test_srid_is_enforced(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->statement("INSERT INTO erosion_hotspots (geom) VALUES (ST_GeomFromText('POINT(1 1)', 3857))");
    }

    public function test_transect_requires_existing_baseline(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->statement("INSERT INTO transects (transect_code, baseline_id, geom) VALUES ('NB', 999999, ST_GeomFromText('LINESTRING(0 0,1 1)',4326))");
    }

    public function test_baseline_cannot_be_deleted_while_referenced(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->table('baseline')->delete();
    }

    public function test_change_class_is_required_on_stats(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->table('transects')->count();
        $id = $this->pg()->table('transects')->where('transect_code', 'T01')->value('id');
        $this->pg()->table('shoreline_change_stats')->where('transect_id', $id)->delete();
        $this->pg()->table('shoreline_change_stats')->insert(['transect_id' => $id]);
    }

    public function test_one_stats_row_per_transect(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $id = $this->pg()->table('transects')->where('transect_code', 'T01')->value('id');
        $this->pg()->table('shoreline_change_stats')->insert(['transect_id' => $id, 'change_class' => 'stable']);
    }

    public function test_mysql_unique_threshold_key(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('threshold_configs')->insert(['key' => 'min_erosion_rate', 'value' => '1']);
    }

    public function test_deleting_user_nulls_updated_by_and_log_user(): void
    {
        $t = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
        $this->withToken($t)->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2])->assertOk();
        User::where('email', 'admin@webgis.test')->delete();
        $this->assertNull(\DB::table('threshold_configs')->where('key', 'min_erosion_rate')->value('updated_by'));
        $this->assertNull(\DB::table('activity_logs')->first()->user_id);
        $this->assertSame(1, \DB::table('activity_logs')->count());
    }

    public function test_user_email_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('users')->insert(['name' => 'x', 'email' => 'admin@webgis.test', 'password' => 'x']);
    }

    public function test_user_role_defaults_to_viewer(): void
    {
        \DB::table('users')->insert(['name' => 'n', 'email' => 'n@x.test', 'password' => 'x']);
        $this->assertSame('viewer', \DB::table('users')->where('email', 'n@x.test')->value('role'));
    }

    public function test_hotspot_severity_nullable_and_geometry_generic(): void
    {
        $this->pg()->statement("INSERT INTO erosion_hotspots (geom) VALUES (ST_GeomFromText('POINT(105.2 10.6)',4326))");
        $this->assertSame(3, $this->pg()->table('erosion_hotspots')->count());
    }

    public function test_danger_level_check_constraint_on_reference(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pg()->statement("INSERT INTO reference_landslide_events (geom, danger_level) VALUES (ST_GeomFromText('POINT(1 1)',4326), 'sieu_nguy_hiem')");
    }
}
