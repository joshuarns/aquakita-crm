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
            'notify_emails' => 'array',
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
            'project_type_id' => ['label' => 'Tipo de proyecto', 'type' => 'text', 'resolve' => ProjectType::class],
            'country_id' => ['label' => 'País', 'type' => 'text', 'resolve' => Country::class],
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
                'resolve' => $available[$key]['resolve'] ?? null,
            ];
        }

        return $result;
    }

    public function isFloating(): bool
    {
        return $this->display_mode === 'floating';
    }

    /**
     * Idiomas disponibles para los textos fijos del formulario/widget.
     *
     * @return array<string, string>
     */
    public static function availableLocales(): array
    {
        return ['es' => 'Español', 'en' => 'English', 'pt' => 'Português'];
    }

    /**
     * Textos fijos del formulario/widget para un idioma.
     *
     * @return array<string, string>
     */
    public static function strings(?string $locale): array
    {
        $all = [
            'es' => [
                'select' => 'Selecciona…',
                'honeypot' => 'No llenar este campo',
                'submit' => 'Enviar',
                'sending' => 'Enviando…',
                'thanks' => '¡Gracias!',
                'success_default' => 'Hemos recibido tus datos. Te contactaremos pronto.',
                'whatsapp' => 'Continuar por WhatsApp',
                'form_title' => 'Formulario de contacto',
                'online' => 'En línea · normalmente responde en minutos',
                'close' => 'Cerrar',
                'greeting_default' => '¿En qué podemos ayudarte?',
                'error_rate' => 'Demasiados envíos. Intenta de nuevo en un momento.',
                'error_nonce' => 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.',
                'error_domain' => 'Este formulario no está autorizado para este dominio.',
                'wa_greeting' => 'Hola, soy :name.',
                'wa_phone' => 'Tel',
                'wa_email' => 'Correo',
            ],
            'en' => [
                'select' => 'Select…',
                'honeypot' => 'Do not fill this field',
                'submit' => 'Send',
                'sending' => 'Sending…',
                'thanks' => 'Thank you!',
                'success_default' => 'We received your information. We will contact you soon.',
                'whatsapp' => 'Continue on WhatsApp',
                'form_title' => 'Contact form',
                'online' => 'Online · usually replies within minutes',
                'close' => 'Close',
                'greeting_default' => 'How can we help you?',
                'error_rate' => 'Too many submissions. Please try again shortly.',
                'error_nonce' => 'The form session expired. Please reload the page and try again.',
                'error_domain' => 'This form is not authorized for this domain.',
                'wa_greeting' => 'Hi, I am :name.',
                'wa_phone' => 'Phone',
                'wa_email' => 'Email',
            ],
            'pt' => [
                'select' => 'Selecione…',
                'honeypot' => 'Não preencha este campo',
                'submit' => 'Enviar',
                'sending' => 'Enviando…',
                'thanks' => 'Obrigado!',
                'success_default' => 'Recebemos seus dados. Entraremos em contato em breve.',
                'whatsapp' => 'Continuar no WhatsApp',
                'form_title' => 'Formulário de contato',
                'online' => 'Online · normalmente responde em minutos',
                'close' => 'Fechar',
                'greeting_default' => 'Como podemos ajudar?',
                'error_rate' => 'Muitos envios. Tente novamente em instantes.',
                'error_nonce' => 'A sessão do formulário expirou. Recarregue a página e tente novamente.',
                'error_domain' => 'Este formulário não está autorizado para este domínio.',
                'wa_greeting' => 'Olá, sou :name.',
                'wa_phone' => 'Tel',
                'wa_email' => 'E-mail',
            ],
        ];

        return $all[$locale] ?? $all['es'];
    }

    /**
     * @return array<string, string>
     */
    public function uiStrings(): array
    {
        return static::strings($this->locale);
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
