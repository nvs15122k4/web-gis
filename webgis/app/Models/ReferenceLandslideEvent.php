<?php

namespace App\Models;

use App\Models\Concerns\HasGeoJsonGeometry;
use Illuminate\Database\Eloquent\Model;

class ReferenceLandslideEvent extends Model
{
    use HasGeoJsonGeometry;

    protected $connection = 'pgsql';

    protected $table = 'reference_landslide_events';

    public $timestamps = false;
}
