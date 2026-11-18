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
            'conversations_enabled' => 'boolean',
            'imap_validate_cert' => 'boolean',
            'imap_port' => 'integer',
            'imap_password' => 'encrypted', // cifrada en reposo (APP_KEY)
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

    /**
     * Dirección desde la que se responde a los leads (buzón de conversación).
     * Propia de la empresa; si no, el global; si no, el remitente de la empresa.
     */
    public function replyFromAddress(): string
    {
        return $this->reply_from_email
            ?: (config('services.leads_mailbox.address') ?: $this->mailFromAddress());
    }

    /**
     * ¿La empresa tiene lista la lectura de respuestas (IMAP)?
     */
    public function conversationsReady(): bool
    {
        return $this->conversations_enabled
            && filled($this->imap_host)
            && filled($this->imap_username)
            && filled($this->imap_password);
    }

    /**
     * Config IMAP para webklex, a partir de los datos de la empresa.
     *
     * @return array<string, mixed>
     */
    public function imapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port ?: 993,
            'encryption' => $this->imap_encryption ?: 'ssl',
            'validate_cert' => (bool) $this->imap_validate_cert,
            'username' => $this->imap_username,
            'password' => $this->imap_password,
            'protocol' => 'imap',
        ];
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
