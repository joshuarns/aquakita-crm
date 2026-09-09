<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Archivo adjunto de la ficha (§5). */
class Attachment extends Model
{
    protected $guarded = ['id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
