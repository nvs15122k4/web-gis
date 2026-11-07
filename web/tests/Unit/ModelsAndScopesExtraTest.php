<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\ErosionHotspot;
use App\Models\ReferenceLandslideEvent;
use App\Models\Shoreline;
use App\Models\ShorelineChangeStat;
use App\Models\ThresholdConfig;
use App\Models\Transect;
use App\Models\TransectShorelineDistance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPUnit\Framework\Attributes\DataProvider;

class ModelsAndScopesExtraTest extends BaseUnitTest
{
    public static function geoModels(): array
    {
        return [
            [Shoreline::class, 'shorelines'], [Transect::class, 'transects'],
            [ErosionHotspot::class, 'erosion_hotspots'], [ReferenceLandslideEvent::class, 'reference_landslide_events'],
        ];
    }

    #[DataProvider('geoModels')]
    public function test_with_geojson_sql_chinh_xac(string $class, string $table): void
    {
        $sql = $class::withGeoJson()->toSql();
        $this->assertSame("select \"{$table}\".*, ST_AsGeoJSON({$table}.geom, 6) AS geojson from \"{$table}\"", $sql);
    }

    #[DataProvider('geoModels')]
    public function test_with_geojson_cot_tuy_chon(string $class, string $table): void
    {
        $this->assertStringContainsString("ST_AsGeoJSON({$table}.centroid, 6)", $class::withGeoJson('centroid')->toSql());
    }

    #[DataProvider('geoModels')]
    public function test_with_geojson_ket_hop_where_va_khong_mat_select(string $class, string $table): void
    {
        $sql = $class::withGeoJson()->where('id', 3)->toSql();
        $this->assertStringContainsString('where "id" = ?', $sql);
        $this->assertStringStartsWith("select \"{$table}\".*", $sql);
    }

    #[DataProvider('geoModels')]
    public function test_chi_mot_cot_geojson_va_khong_dung_chung_bang_khac(string $class, string $table): void
    {
        $this->assertSame(1, substr_count($class::withGeoJson()->toSql(), 'AS geojson'));
        $this->assertSame('pgsql', (new $class)->getConnectionName());
    }

    // ---- toFeatureCollection ----

    private function make(string $class, array $attrs): object
    {
        return (new $class)->forceFill($attrs);
    }

    public function test_feature_id_null_khi_model_chua_co_khoa(): void
    {
        $m = $this->make(Shoreline::class, ['geojson' => '{"type":"Point","coordinates":[0,0]}', 'year' => 2020]);
        $f = Shoreline::toFeatureCollection(new Collection([$m]))['features'][0];
        $this->assertNull($f['id']);
        $this->assertSame(['year' => 2020], $f['properties']);
    }

    public function test_feature_geometry_null_khi_thieu_hoac_geojson_rong_hoac_hong(): void
    {
        foreach ([[], ['geojson' => null], ['geojson' => ''], ['geojson' => '{hong']] as $attrs) {
            $m = $this->make(Shoreline::class, ['id' => 1] + $attrs);
            $this->assertNull(Shoreline::toFeatureCollection(new Collection([$m]))['features'][0]['geometry'], json_encode($attrs));
        }
    }

    public function test_properties_giu_nguyen_kieu_va_thu_tu_cot(): void
    {
        $m = $this->make(ErosionHotspot::class, ['id' => 9, 'severity' => 2.5, 'notes' => null, 'geom' => '0101', 'geojson' => '{}']);
        $f = ErosionHotspot::toFeatureCollection(new Collection([$m]))['features'][0];
        $this->assertSame(['id' => 9, 'severity' => 2.5, 'notes' => null], $f['properties']);
        $this->assertSame(['id', 'severity', 'notes'], array_keys($f['properties']));
    }

    public function test_id_la_khoa_chinh_cua_model_dung_kieu(): void
    {
        $m = $this->make(Transect::class, ['id' => 77, 'geojson' => '{}']);
        $this->assertSame(77, Transect::toFeatureCollection(new Collection([$m]))['features'][0]['id']);
    }

    public function test_feature_collection_nhieu_phan_tu_giu_thu_tu(): void
    {
        $models = new Collection(array_map(fn ($i) => $this->make(Shoreline::class, ['id' => $i, 'geojson' => '{}']), [5, 3, 8]));
        $this->assertSame([5, 3, 8], array_column(Shoreline::toFeatureCollection($models)['features'], 'id'));
    }

    public function test_feature_collection_tu_collection_co_key_roi_van_la_danh_sach(): void
    {
        $c = (new Collection([10 => $this->make(Shoreline::class, ['id' => 1, 'geojson' => '{}']), 20 => $this->make(Shoreline::class, ['id' => 2, 'geojson' => '{}'])]));
        $this->assertTrue(array_is_list(Shoreline::toFeatureCollection($c)['features']));
    }

    // ---- quan hệ / cấu hình ----

    public function test_transect_relations_kieu_va_khoa_ngoai(): void
    {
        $t = new Transect;
        $this->assertInstanceOf(HasOne::class, $t->stats());
        $this->assertSame('shoreline_change_stats.transect_id', $t->stats()->getQualifiedForeignKeyName());
        $this->assertInstanceOf(HasMany::class, $t->distances());
        $this->assertSame('transect_shoreline_distances.transect_id', $t->distances()->getQualifiedForeignKeyName());
        $this->assertSame([['column' => 'year', 'direction' => 'asc']], $t->distances()->getQuery()->getQuery()->orders);
    }

    public function test_stat_belongs_to_transect(): void
    {
        $r = (new ShorelineChangeStat)->transect();
        $this->assertInstanceOf(BelongsTo::class, $r);
        $this->assertSame('transect_id', $r->getForeignKeyName());
        $this->assertSame(Transect::class, get_class($r->getRelated()));
    }

    public static function boolCasts(): array
    {
        return [[1, true], [0, false], ['1', true], ['0', false], [true, true], [false, false], [null, null]];
    }

    #[DataProvider('boolCasts')]
    public function test_stat_is_significant_cast(mixed $in, ?bool $out): void
    {
        $this->assertSame($out, (new ShorelineChangeStat)->forceFill(['is_significant' => $in])->is_significant);
    }

    public function test_stat_cac_cot_khac_khong_bi_cast(): void
    {
        $s = (new ShorelineChangeStat)->forceFill(['lrr_m_per_year' => '-1.50', 'change_class' => 'stable']);
        $this->assertSame('-1.50', $s->lrr_m_per_year);
        $this->assertSame(['is_significant' => 'boolean'], (fn () => $this->casts())->call($s));
    }

    public function test_distance_model_dinh_nghia(): void
    {
        $d = new TransectShorelineDistance;
        $this->assertSame('transect_shoreline_distances', $d->getTable());
        $this->assertFalse($d->usesTimestamps());
        $this->assertSame('pgsql', $d->getConnectionName());
    }

    public function test_threshold_config_dinh_nghia(): void
    {
        $m = new ThresholdConfig;
        $this->assertSame('threshold_configs', $m->getTable());
        $this->assertNull($m->getConnectionName());
        $this->assertTrue($m->usesTimestamps());
        $this->assertSame(['key', 'value', 'description', 'updated_by'], $m->getFillable());
    }

    public function test_threshold_config_fill_chi_nhan_cot_cho_phep(): void
    {
        $m = (new ThresholdConfig)->fill(['key' => 'k', 'value' => '1', 'id' => 99, 'created_at' => 'x', 'admin' => true]);
        $this->assertSame(['key' => 'k', 'value' => '1'], $m->getAttributes());
    }

    public function test_activity_log_chi_co_created_at(): void
    {
        $m = new ActivityLog;
        $this->assertSame('created_at', $m->getCreatedAtColumn());
        $this->assertNull($m->getUpdatedAtColumn());
        $this->assertSame('activity_logs', $m->getTable());
    }

    public function test_activity_log_fill_loai_cot_la(): void
    {
        $m = (new ActivityLog)->fill(['user_id' => 1, 'action' => 'a', 'ip_address' => '1.1.1.1', 'id' => 5, 'extra' => 1]);
        $this->assertSame(['user_id' => 1, 'action' => 'a', 'ip_address' => '1.1.1.1'], $m->getAttributes());
    }

    public static function modelsWithoutTimestamps(): array
    {
        return array_merge(self::geoModels(), [[ShorelineChangeStat::class, ''], [TransectShorelineDistance::class, '']]);
    }

    #[DataProvider('modelsWithoutTimestamps')]
    public function test_model_khong_gian_khong_timestamps_khoa_chinh_id(string $class, string $_): void
    {
        $m = new $class;
        $this->assertFalse($m->usesTimestamps());
        $this->assertSame('id', $m->getKeyName());
        $this->assertTrue($m->getIncrementing());
    }

    public function test_user_phan_loai_vai_tro_khong_phan_biet_chuoi_trong(): void
    {
        foreach ([['ADMIN', false], ['Admin', false], [' admin', false], ['admin', true], ['', false]] as [$role, $exp]) {
            $this->assertSame($exp, (new User)->forceFill(['role' => $role])->isAdmin(), "role=[$role]");
        }
    }
}
