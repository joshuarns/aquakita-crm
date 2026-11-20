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
     * Bytes del logo de la plataforma (el configurado o el de Aquakita por defecto).
     */
    public static function logoBytes(): string
    {
        $logo = self::get('brand_logo');

        return $logo ? base64_decode($logo) : BrandLogo::bytes();
    }

    public static function name(): string
    {
        return self::get('brand_name') ?: (string) config('app.name');
    }

    public static function tagline(): string
    {
        return self::get('brand_tagline') ?: 'Sistema comercial '.config('app.name');
    }
}
