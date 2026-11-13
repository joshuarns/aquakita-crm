<?php

namespace App\Models;

use App\Support\BrandLogo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa (tenant). Agrupa y aísla todos los datos vía company_id.
 */
class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Bytes del logo (el propio, o el de Aquakita por defecto).
     */
    public function logoBytes(): string
    {
        return $this->logo ? base64_decode($this->logo) : BrandLogo::bytes();
    }

    public function logoUrl(): string
    {
        return route('company.logo', ['company' => $this->id, 'v' => $this->updated_at?->timestamp]);
    }

    public function brandColor(): string
    {
        return $this->brand_color ?: '#0e7490';
    }

    /**
     * Dirección "De" del correo saliente de la empresa.
     * Usa el correo propio si está configurado; si no, el global de la plataforma.
     */
    public function mailFromAddress(): string
    {
        return $this->from_email ?: (string) config('mail.from.address');
    }

    /**
     * Nombre "De" visible: el de la empresa.
     */
    public function mailFromName(): string
    {
        return $this->name ?: (string) config('mail.from.name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
