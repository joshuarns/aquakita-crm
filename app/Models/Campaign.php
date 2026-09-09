<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catálogo de configuración (§3.2). */
class Campaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
