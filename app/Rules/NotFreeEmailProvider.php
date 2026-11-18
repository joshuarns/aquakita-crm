<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rechaza direcciones de proveedores de correo gratuitos (gmail, hotmail, etc.)
 * como remitente. Esos dominios publican DMARC en "reject": si envías un correo
 * que dice venir de "@gmail.com" desde otro servidor, Gmail y los demás lo
 * rebotan. El "From" debe ser un dominio propio de la empresa.
 */
class NotFreeEmailProvider implements ValidationRule
{
    /**
     * @var list<string>
     */
    private const FREE_PROVIDERS = [
        'gmail.com',
        'googlemail.com',
        'hotmail.com',
        'hotmail.es',
        'outlook.com',
        'outlook.es',
        'live.com',
        'live.com.mx',
        'msn.com',
        'yahoo.com',
        'yahoo.com.mx',
        'yahoo.es',
        'ymail.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'gmx.com',
        'proton.me',
        'protonmail.com',
        'zoho.com',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $domain = strtolower(trim(substr(strrchr($value, '@') ?: '', 1)));

        if ($domain !== '' && in_array($domain, self::FREE_PROVIDERS, true)) {
            $fail("No puedes usar una dirección de {$domain} como remitente: esos proveedores rechazan (DMARC) el correo que sale desde otro servidor y rebotaría. Usa un correo de tu propio dominio, p. ej. info@tuempresa.com.");
        }
    }
}
