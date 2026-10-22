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
}
