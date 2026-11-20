<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de la instalación (plataforma): logo, nombre y lema que se muestran
 * en la pantalla de login y en el panel del super-admin. Es configurable por
 * el super-admin y, si no se define, cae a los valores por defecto (Aquakita).
 */
class Branding
{
    /**
     * Caché por request de los ajustes de marca.
     *
     * @var array<string, ?string>|null
     */
    private static ?array $cache = null;

    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Tolerante antes de migrar o en consola sin tabla.
        if (! Schema::hasTable('app_settings')) {
            return self::$cache = [];
        }

        return self::$cache = AppSetting::query()
            ->whereIn('key', ['brand_logo', 'brand_name', 'brand_tagline'])
            ->pluck('value', 'key')
            ->all();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function get(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }

    public static function set(string $key, ?string $value): void
    {
        AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        self::flush();
    }

    public static function hasLogo(): bool
    {
        return ! empty(self::get('brand_logo'));
    }

    /**
     * Bytes del logo de la plataforma. Si no se configuró ninguno, devuelve un
     * placeholder neutro de «sin imagen» (nunca el logo de otra marca).
     */
    public static function logoBytes(): string
    {
        $logo = self::get('brand_logo');

        return $logo ? base64_decode($logo) : self::PLACEHOLDER_SVG;
    }

    /**
     * Tipo de contenido para servir el logo: el subido (PNG) o el placeholder (SVG).
     */
    public static function logoContentType(): string
    {
        return self::hasLogo() ? 'image/png' : 'image/svg+xml';
    }

    /**
     * SVG del placeholder neutro «sin imagen», reutilizable (p. ej. empresas sin logo).
     */
    public static function placeholderSvg(): string
    {
        return self::PLACEHOLDER_SVG;
    }

    /**
     * Placeholder neutro «sin imagen» (icono gris sobre fondo claro).
     */
    private const PLACEHOLDER_SVG = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 24 24" fill="none" role="img" aria-label="Sin logo">
            <rect width="24" height="24" rx="5" fill="#e2e8f0"/>
            <path d="M3.5 16.5l4.2-4.2a1 1 0 0 1 1.4 0l2.6 2.6 3.2-3.8a1 1 0 0 1 1.5 0l4.1 4.6" stroke="#94a3b8" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="8.6" cy="8.4" r="1.7" fill="#94a3b8"/>
        </svg>
        SVG;

    public static function name(): string
    {
        return self::get('brand_name') ?: (string) config('app.name');
    }

    public static function tagline(): string
    {
        return self::get('brand_tagline') ?: 'Sistema comercial '.config('app.name');
    }
}
