<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cấu hình hiển thị mặc định của các lớp chuyên đề trên bản đồ công khai (bật/tắt, độ trong suốt, thứ tự chồng lớp).
 * Chỉ ảnh hưởng cách hiển thị, không đụng dữ liệu hay ngưỡng phân loại khoa học.
 */
#[Fillable(['key', 'visible', 'opacity', 'sort_order', 'updated_by'])]
class LayerSetting extends Model
{
    /** Giá trị mặc định khi chưa có dòng nào trong bảng; thứ tự nhỏ nằm dưới, lớn nằm trên. */
    public const DEFAULTS = [
        'transects' => ['label' => 'Transect (đường cắt)', 'visible' => false, 'opacity' => 100, 'sort_order' => 1],
        'rates' => ['label' => 'Điểm tốc độ biến động', 'visible' => true, 'opacity' => 100, 'sort_order' => 2],
        'hotspots' => ['label' => 'Hotspot lùi bờ mạnh', 'visible' => true, 'opacity' => 100, 'sort_order' => 3],
        'reference' => ['label' => 'Sạt lở tham chiếu 2019–2020', 'visible' => false, 'opacity' => 100, 'sort_order' => 4],
    ];

    protected function casts(): array
    {
        return ['visible' => 'boolean'];
    }

    /** Danh sách đầy đủ (mặc định ghi đè bởi dòng đã lưu), sắp theo sort_order. */
    public static function resolved(): array
    {
        $saved = static::query()->get()->keyBy('key');
        $out = [];
        foreach (self::DEFAULTS as $key => $d) {
            $row = $saved->get($key);
            $out[] = [
                'key' => $key,
                'label' => $d['label'],
                'visible' => $row ? (bool) $row->visible : $d['visible'],
                'opacity' => $row ? (int) $row->opacity : $d['opacity'],
                'sort_order' => $row ? (int) $row->sort_order : $d['sort_order'],
            ];
        }
        usort($out, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $out;
    }
}
