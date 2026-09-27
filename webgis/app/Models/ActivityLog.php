<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'ip_address'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;
}
