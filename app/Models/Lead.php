<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Lead / prospecto (§3.3, §5).
 * Implementa Auditable para conservar usuario, fecha y hora de cada cambio (§10).
 */
class Lead extends Model implements Auditable
{
    use AuditableTrait, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
            'sale_confirmed' => 'boolean',
            'next_follow_up_at' => 'datetime',
            'assigned_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'first_contact_at' => 'datetime',
        ];
    }

    // ---- Relaciones de catálogo ----
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function discardReason(): BelongsTo
    {
        return $this->belongsTo(DiscardReason::class);
    }

    // ---- Relaciones de usuario ----
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    // ---- Historial y actividad ----
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(LeadNotification::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** Nombre completo para listados. */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ---- Scopes de acceso (§2 separación estricta) ----
    /** El vendedor solo ve sus leads asignados. */
    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Registra un evento inmutable en la línea de tiempo de la ficha (§5).
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTimeline(string $type, ?string $description = null, array $data = [], ?int $userId = null): TimelineEvent
    {
        return $this->timeline()->create([
            'type' => $type,
            'description' => $description,
            'data' => $data ?: null,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
