<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Formulario web embebible que captura leads desde sitios externos.
 */
class LeadForm extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'allowed_domains' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Campos disponibles y su configuración por defecto.
     *
     * @return array<string, array{label: string, type: string, catalog?: class-string, locked?: bool}>
     */
    public static function availableFields(): array
    {
        return [
            'first_name' => ['label' => 'Nombre', 'type' => 'text', 'locked' => true],
            'last_name' => ['label' => 'Apellido', 'type' => 'text'],
            'company' => ['label' => 'Empresa', 'type' => 'text'],
            'email' => ['label' => 'Correo electrónico', 'type' => 'email'],
            'phone' => ['label' => 'Teléfono', 'type' => 'tel'],
            'project_type_id' => ['label' => 'Tipo de proyecto', 'type' => 'select', 'catalog' => ProjectType::class],
            'country_id' => ['label' => 'País', 'type' => 'select', 'catalog' => Country::class],
            'description' => ['label' => 'Mensaje', 'type' => 'textarea'],
        ];
    }

    /**
     * Configuración por defecto de campos para un formulario nuevo.
     *
     * @return array<int, array{key: string, enabled: bool, required: bool, label: string}>
     */
    public static function defaultFields(): array
    {
        $defaults = [
            'first_name' => ['enabled' => true, 'required' => true],
            'last_name' => ['enabled' => true, 'required' => false],
            'email' => ['enabled' => true, 'required' => true],
            'phone' => ['enabled' => true, 'required' => false],
            'company' => ['enabled' => false, 'required' => false],
            'project_type_id' => ['enabled' => false, 'required' => false],
            'country_id' => ['enabled' => false, 'required' => false],
            'description' => ['enabled' => true, 'required' => false],
        ];

        $fields = [];
        foreach (static::availableFields() as $key => $meta) {
            $config = $defaults[$key] ?? ['enabled' => false, 'required' => false];
            $fields[] = [
                'key' => $key,
                'enabled' => $config['enabled'],
                'required' => $config['required'],
                'label' => $meta['label'],
            ];
        }

        return $fields;
    }

    /**
     * Campos activos del formulario, con su metadata resuelta.
     *
     * @return array<int, array{key: string, required: bool, label: string, type: string, catalog?: class-string}>
     */
    public function enabledFields(): array
    {
        $available = static::availableFields();
        $configured = $this->fields ?: static::defaultFields();

        $result = [];
        foreach ($configured as $field) {
            $key = $field['key'] ?? null;
            if ($key === null || ! isset($available[$key]) || empty($field['enabled'])) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'required' => (bool) ($field['required'] ?? false),
                'label' => $field['label'] ?: $available[$key]['label'],
                'type' => $available[$key]['type'],
                'catalog' => $available[$key]['catalog'] ?? null,
            ];
        }

        return $result;
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
