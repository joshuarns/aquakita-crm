<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hace que un modelo pertenezca a una empresa (tenant):
 * - filtra automáticamente por la empresa activa (scope global),
 * - rellena company_id al crear si hay empresa activa.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $tenancy = app(Tenancy::class);
            if ($tenancy->check()) {
                $builder->where($builder->getModel()->getTable().'.company_id', $tenancy->id());
            }
        });

        static::creating(function ($model) {
            $tenancy = app(Tenancy::class);
            if ($tenancy->check() && empty($model->company_id)) {
                $model->company_id = $tenancy->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
