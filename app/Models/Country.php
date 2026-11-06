<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** País (§3.2). */
class Country extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
