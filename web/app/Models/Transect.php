<?php

namespace App\Models;

use App\Models\Concerns\HasGeoJsonGeometry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transect extends Model
{
    use HasGeoJsonGeometry;

    protected $connection = 'pgsql';

    protected $table = 'transects';

    public $timestamps = false;

    public function stats(): HasOne
    {
        return $this->hasOne(ShorelineChangeStat::class);
    }

    public function distances(): HasMany
    {
        return $this->hasMany(TransectShorelineDistance::class)->orderBy('year');
    }
}
