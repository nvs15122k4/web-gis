<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\Concerns\HasGeoJsonGeometry;
use App\Models\ErosionHotspot;
use App\Models\ReferenceLandslideEvent;
use App\Models\Shoreline;
use App\Models\ShorelineChangeStat;
use App\Models\ThresholdConfig;
use App\Models\Transect;
use App\Models\TransectShorelineDistance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPUnit\Framework\Attributes\DataProvider;

class ModelDefinitionTest extends BaseUnitTest
{
    public static function spatialModels(): array
    {
        return [
            [ErosionHotspot::class, 'erosion_hotspots', true],
            [ReferenceLandslideEvent::class, 'reference_landslide_events', true],
            [Shoreline::class, 'shorelines', true],
            [Transect::class, 'transects', true],
            [ShorelineChangeStat::class, 'shoreline_change_stats', false],
            [TransectShorelineDistance::class, 'transect_shoreline_distances', false],
        ];
    }

    #[DataProvider('spatialModels')]
    public function test_model_khong_gian_dung_pgsql_bang_dung_va_khong_timestamps(string $class, string $table, bool $geo): void
    {
        $m = new $class;
        $this->assertSame('pgsql', $m->getConnectionName());
        $this->assertSame($table, $m->getTable());
        $this->assertFalse($m->usesTimestamps());
        $this->assertSame($geo, in_array(HasGeoJsonGeometry::class, class_uses($m), true));
    }

    public static function mysqlModels(): array
    {
        return [[ActivityLog::class, 'activity_logs'], [ThresholdConfig::class, 'threshold_configs']];
    }

    #[DataProvider('mysqlModels')]
    public function test_model_ung_dung_dung_ket_noi_mac_dinh(string $class, string $table): void
    {
        $m = new $class;
        $this->assertNull($m->getConnectionName());
        $this->assertSame($table, $m->getTable());
    }

    public function test_threshold_config_fillable(): void
    {
        $this->assertEqualsCanonicalizing(['key', 'value', 'description', 'updated_by'], (new ThresholdConfig)->getFillable());
    }

    public function test_threshold_config_bo_qua_cot_ngoai_fillable(): void
    {
        $m = new ThresholdConfig(['key' => 'k', 'id' => 5, 'created_at' => 'x']);
        $this->assertSame('k', $m->key);
        $this->assertNull($m->id);
    }

    public function test_activity_log_fillable_va_khong_co_updated_at(): void
    {
        $m = new ActivityLog;
        $this->assertEqualsCanonicalizing(['user_id', 'action', 'ip_address'], $m->getFillable());
        $this->assertNull(ActivityLog::UPDATED_AT);
        $this->assertSame('created_at', $m->getCreatedAtColumn());
        $this->assertNull($m->getUpdatedAtColumn());
        $this->assertTrue($m->usesTimestamps());
    }

    public static function boolValues(): array
    {
        return [
            'true' => [true, true], 'false' => [false, false],
            'int 1' => [1, true], 'int 0' => [0, false],
            'chuoi 1' => ['1', true], 'chuoi 0' => ['0', false],
        ];
    }

    #[DataProvider('boolValues')]
    public function test_is_significant_cast_boolean(mixed $in, bool $out): void
    {
        $m = new ShorelineChangeStat;
        $m->is_significant = $in;
        $this->assertSame($out, $m->is_significant);
    }

    public function test_quan_he_cua_transect_va_stat(): void
    {
        $t = new Transect;
        $this->assertInstanceOf(HasOne::class, $t->stats());
        $this->assertInstanceOf(HasMany::class, $t->distances());
        $this->assertInstanceOf(BelongsTo::class, (new ShorelineChangeStat)->transect());
        $this->assertSame('transect_id', $t->stats()->getForeignKeyName());
        $this->assertSame(Transect::class, (new ShorelineChangeStat)->transect()->getRelated()::class);
    }

    public function test_distances_sap_xep_theo_nam_tang_dan(): void
    {
        $orders = (new Transect)->distances()->getQuery()->getQuery()->orders;
        $this->assertSame('year', $orders[0]['column']);
        $this->assertSame('asc', $orders[0]['direction']);
    }
}
