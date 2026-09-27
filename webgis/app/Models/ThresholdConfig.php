<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Ngưỡng lọc hiển thị ở UI — không đụng tới ngưỡng phân loại khoa học (Mục 2.5 đề cương). */
#[Fillable(['key', 'value', 'description', 'updated_by'])]
class ThresholdConfig extends Model
{
}
