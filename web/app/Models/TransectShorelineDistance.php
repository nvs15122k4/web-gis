<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransectShorelineDistance extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'transect_shoreline_distances';

    public $timestamps = false;
}
