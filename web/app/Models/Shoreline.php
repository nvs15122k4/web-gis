<?php

namespace App\Models;

use App\Models\Concerns\HasGeoJsonGeometry;
use Illuminate\Database\Eloquent\Model;

class Shoreline extends Model
{
    use HasGeoJsonGeometry;

    protected $connection = 'pgsql';

    protected $table = 'shorelines';

    public $timestamps = false;

    /** Nguồn dữ liệu: 'main' là bản chính; các nguồn còn lại là bản đối chứng độc lập. */
    public const MAIN = 'main';

    /** Bản đối chứng độc lập: khoá => [nhãn, màu]. Bản chính dùng màu xanh dương trong khung so sánh. */
    public const ALT_SOURCES = [
        's2c1' => ['label' => 'Sentinel-2 Collection 1', 'color' => '#00c853'],
        'landsat' => ['label' => 'Landsat 8/9', 'color' => '#ff6d00'],
    ];

    public const MAIN_COLOR = '#1565c0';

    /** Chỉ đường bờ bản chính: mọi chỗ cũ (năm có dữ liệu, tổng quan, tải về) phải dùng scope này để không lẫn bản đối chứng. */
    public function scopeMain($query)
    {
        return $query->whereRaw($this->qualifyColumn('source')." = '".self::MAIN."'");
    }
}
