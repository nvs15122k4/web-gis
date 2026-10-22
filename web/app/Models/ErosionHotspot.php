<?php

namespace App\Models;

use App\Models\Concerns\HasGeoJsonGeometry;
use Illuminate\Database\Eloquent\Model;

class ErosionHotspot extends Model
{
    use HasGeoJsonGeometry;

    protected $connection = 'pgsql';

    protected $table = 'erosion_hotspots';

    public $timestamps = false;
}
