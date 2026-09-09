<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Estatus comercial (§7). */
class Status extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'is_won' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
