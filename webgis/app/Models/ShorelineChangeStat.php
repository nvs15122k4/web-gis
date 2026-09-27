<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kết quả tính sẵn từ pipeline Python — Laravel chỉ đọc, không tính lại. */
class ShorelineChangeStat extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'shoreline_change_stats';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_significant' => 'boolean'];
    }

    public function transect(): BelongsTo
    {
        return $this->belongsTo(Transect::class);
    }
}
