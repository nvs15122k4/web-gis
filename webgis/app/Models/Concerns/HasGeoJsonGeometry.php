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
