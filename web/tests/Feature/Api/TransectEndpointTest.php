<?php

namespace Tests\Feature\Api;

use App\Models\Transect;
use Tests\TestCase;

class TransectEndpointTest extends TestCase
{
    public static function classCounts(): array
    {
        return [
            'accretion' => ['accretion', 2],
            'stable' => ['stable', 3],
            'mild_erosion' => ['mild_erosion', 2],
            'severe_erosion' => ['severe_erosion', 3],
            'không tồn tại' => ['khong_co', 0],
            'sai hoa thường' => ['STABLE', 0],
            'SQL injection' => ["' OR '1'='1", 0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('classCounts')]
    public function test_filter_by_change_class(string $class, int $count): void
    {
        $this->getJson('/api/transects?change_class='.urlencode($class))->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')->assertJsonCount($count, 'features');
    }

    public static function bboxes(): array
    {
        return [
            'toàn vùng' => ['105.19,10.55,105.23,10.61', 10],
            'T01-T02' => ['105.19,10.558,105.23,10.566', 2],
            'một transect' => ['105.19,10.559,105.23,10.561', 1],
            'ngoài vùng' => ['106,11,106.1,11.1', 0],
            'phía đông xa' => ['105.5,10.55,105.6,10.61', 0],
            'bbox thiếu phần tử bị bỏ qua' => ['105.19,10.55,105.23', 10],
            'bbox nhiều phần tử bị bỏ qua' => ['1,2,3,4,5', 10],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bboxes')]
    public function test_filter_by_bbox(string $bbox, int $count): void
    {
        $this->getJson('/api/transects?bbox='.$bbox)->assertOk()->assertJsonCount($count, 'features');
    }

    public function test_bbox_and_class_combine(): void
    {
        $this->getJson('/api/transects?bbox=105.19,10.55,105.23,10.61&change_class=accretion')->assertOk()->assertJsonCount(2, 'features');
        $this->getJson('/api/transects?bbox=105.19,10.558,105.23,10.566&change_class=severe_erosion')->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_non_numeric_bbox_does_not_crash(): void
    {
        $this->getJson('/api/transects?bbox=a,b,c,d')->assertOk()->assertJsonPath('type', 'FeatureCollection');
    }

    public function test_feature_properties_contain_stats_columns(): void
    {
        $f = $this->getJson('/api/transects?change_class=accretion')->json('features.0');
        $this->assertSame('Feature', $f['type']);
        $this->assertSame('LineString', $f['geometry']['type']);
        foreach (['transect_code', 'change_class', 'epr_m_per_year', 'lrr_m_per_year'] as $k) {
            $this->assertArrayHasKey($k, $f['properties']);
        }
        $this->assertArrayNotHasKey('geom', $f['properties']);
    }

    public static function codes(): array
    {
        return array_map(fn ($i) => [sprintf('T%02d', $i)], range(1, 10));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('codes')]
    public function test_detail_and_history_for_every_transect(string $code): void
    {
        $id = Transect::where('transect_code', $code)->value('id');
        $this->getJson("/api/transects/{$id}")->assertOk()->assertJsonPath('transect_code', $code)->assertJsonPath('id', $id)
            ->assertJsonStructure(['stats' => ['change_class', 'lrr_m_per_year']]);
        $h = $this->getJson("/api/transects/{$id}/history")->assertOk();
        $this->assertGreaterThanOrEqual(6, count($h->json()));
        foreach ($h->json() as $row) {
            $this->assertSame(['year', 'distance_m'], array_keys($row));
        }
    }

    public static function badIds(): array
    {
        return [['999999'], ['0'], ['abc'], ['-1'], ['1.5']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badIds')]
    public function test_detail_and_history_404_for_bad_id(string $id): void
    {
        $this->getJson("/api/transects/{$id}")->assertNotFound();
        $this->getJson("/api/transects/{$id}/history")->assertNotFound();
    }

    public function test_history_is_ascending_by_year(): void
    {
        $id = Transect::where('transect_code', 'T03')->value('id');
        $years = array_column($this->getJson("/api/transects/{$id}/history")->json(), 'year');
        $s = $years;
        sort($s);
        $this->assertSame($s, $years);
    }

    public function test_history_never_includes_2022(): void
    {
        foreach (Transect::pluck('id') as $id) {
            $this->assertNotContains(2022, array_column($this->getJson("/api/transects/{$id}/history")->json(), 'year'));
        }
    }

    public function test_write_methods_not_allowed(): void
    {
        $id = Transect::value('id');
        $this->postJson('/api/transects')->assertStatus(405);
        $this->deleteJson("/api/transects/{$id}")->assertStatus(405);
    }
}
