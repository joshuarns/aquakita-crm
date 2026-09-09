<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'active', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /** Leads asignados a este usuario como vendedor (§4.2). */
    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'vendor_id');
    }

    /** Leads capturados por este usuario (§3.3). */
    public function capturedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'captured_by');
    }

    /** Actividades realizadas por este usuario (§6). */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'performed_by');
    }
}
