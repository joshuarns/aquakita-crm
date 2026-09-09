<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ciudad, pertenece a un país (§3.2). */
class City extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
