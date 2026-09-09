<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Actividad / seguimiento (§6).
 */
class Activity extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = ['id'];

    // Tipos de actividad (§6)
    public const TYPES = [
        'llamada' => 'Llamada telefónica',
        'whatsapp' => 'WhatsApp',
        'correo' => 'Correo electrónico',
        'videollamada' => 'Videollamada o reunión',
        'nota' => 'Nota interna',
        'informacion' => 'Solicitud o envío de información',
        'cotizacion' => 'Envío y seguimiento de cotización',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
            'rescheduled' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** Seguimientos vencidos (§8 KPI). */
    public function scopeOverdue($query)
    {
        return $query->where('completed', false)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<', now());
    }
}
