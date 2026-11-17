<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un correo del hilo de conversación con un lead: saliente (CRM -> lead) o
 * entrante (respuesta del lead capturada por IMAP).
 */
class LeadMessage extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'out';
    }
}
