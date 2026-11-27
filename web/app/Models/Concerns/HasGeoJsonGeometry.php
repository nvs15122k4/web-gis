<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Eloquent không hiểu kiểu geometry, nên đọc toạ độ qua ST_AsGeoJSON() và
 * đóng gói thành GeoJSON FeatureCollection (EPSG:4326) cho API.
 */
trait HasGeoJsonGeometry
{
    /** Chọn thêm cột `geojson` (chuỗi JSON) từ cột geom. */
    public function scopeWithGeoJson(Builder $query, string $column = 'geom'): Builder
    {
        return $query
            ->addSelect($query->getModel()->qualifyColumn('*'))
            ->selectRaw("ST_AsGeoJSON({$this->getTable()}.{$column}, 6) AS geojson");
    }

    /**
     * Như withGeoJson() nhưng KHÔNG kéo cột geom nguyên bản (EWKB hex, nặng gấp 2–3 lần GeoJSON và bị bỏ đi ngay
     * trong toFeatureCollection). Cột thuộc tính lấy từ geoPropertyColumns(); model không khai báo thì giữ `bảng.*`.
     */
    public function scopeWithGeoJsonLean(Builder $query, string $column = 'geom', ?float $tolerance = null): Builder
    {
        $model = $query->getModel();
        $cols = $model->geoPropertyColumns();
        $query->addSelect($cols ? array_map(fn ($c) => $model->qualifyColumn($c), $cols) : $model->qualifyColumn('*'));

        $geom = "{$model->getTable()}.{$column}";
        if ($tolerance !== null && $tolerance > 0) {
            // Giản lược hình học (độ, WGS84) nhưng giữ topology; chỉ chạy khi client yêu cầu rõ ràng.
            return $query->selectRaw("ST_AsGeoJSON(ST_SimplifyPreserveTopology({$geom}, ?), 6) AS geojson", [$tolerance]);
        }

        return $query->selectRaw("ST_AsGeoJSON({$geom}, 6) AS geojson");
    }

    /** Danh sách cột thuộc tính (không gồm geom); null = chọn tất cả. Model ghi đè khi muốn bỏ cột geom. @return list<string>|null */
    public function geoPropertyColumns(): ?array
    {
        return null;
    }

    /** @param Collection<int, static> $models */
    public static function toFeatureCollection(Collection $models): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $models->map(function ($model) {
                $props = collect($model->getAttributes())->except(['geom', 'geojson'])->all();

                return [
                    'type' => 'Feature',
                    'id' => $model->getKey(),
                    'geometry' => json_decode($model->getAttribute('geojson'), true),
                    'properties' => $props,
                ];
            })->values()->all(),
        ];
    }
}
