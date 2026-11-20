<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste global de la plataforma (clave/valor). No pertenece a ninguna empresa:
 * aplica a toda la instalación (p. ej. la marca de la pantalla de login).
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
