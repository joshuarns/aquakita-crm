<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cambio de estatus (§5, §7). Registro inmutable. */
class LeadStatusHistory extends Model
{
    protected $guarded = ['id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
