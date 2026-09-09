<?php

use App\Models\Campaign;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Lead;
use App\Models\ProjectType;
use App\Models\Source;
use App\Models\Status;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Contacto y empresa (§3.3)
    public string $first_name = '';
    public string $last_name = '';
    public string $company = '';
    public string $email = '';
    public string $phone = '';

    // Ubicación e idioma
    public ?int $country_id = null;
    public ?int $city_id = null;
    public ?int $language_id = null;

    // Origen (§3.3)
    public ?int $source_id = null;
    public ?int $campaign_id = null;
    public string $landing = '';
    public string $keyword = '';

    // Proyecto (§3.3)
    public ?int $project_type_id = null;
    public string $budget = '';
    public string $deadline = '';
    public string $description = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'language_id' => ['nullable', 'exists:languages,id'],
            'source_id' => ['nullable', 'exists:sources,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'landing' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'project_type_id' => ['nullable', 'exists:project_types,id'],
            'budget' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Posibles duplicados por correo o teléfono (§3.3, advertencia no bloqueante).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Lead>
     */
    #[Computed]
    public function possibleDuplicates()
    {
        $email = trim($this->email);
        $phone = trim($this->phone);

        if ($email === '' && $phone === '') {
            return collect();
        }

        return Lead::query()
            ->when($email !== '', fn ($q) => $q->orWhere('email', $email))
            ->when($phone !== '', fn ($q) => $q->orWhere('phone', $phone))
            ->with(['vendor', 'status'])
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function countries()
    {
        return Country::where('active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function cities()
    {
        return City::where('active', true)
            ->when($this->country_id, fn ($q) => $q->where('country_id', $this->country_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function languages()
    {
        return Language::where('active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function sources()
    {
        return Source::where('active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function campaigns()
    {
        return Campaign::where('active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function projectTypes()
    {
        return ProjectType::where('active', true)->orderBy('name')->get();
    }

    /** Al cambiar de país, se limpia la ciudad para evitar inconsistencias. */
    public function updatedCountryId(): void
    {
        $this->city_id = null;
    }

    /**
     * Guarda el lead con estatus inicial "Nuevo" y registra la captura (§3.3, §10).
     * Fecha, hora y usuario de captura son automáticos (servidor).
     */
    public function save(): void
    {
        $data = $this->validate();

        $nuevo = Status::where('order', 1)->firstOrFail();

        $lead = Lead::create([
            ...$data,
            'status_id' => $nuevo->id,
            'captured_by' => auth()->id(),
        ]);

        $lead->recordTimeline('captured', 'Lead capturado', [
            'capturista' => auth()->user()->name,
        ]);

        session()->flash('status', "Lead «{$lead->full_name}» capturado correctamente.");

        $this->redirectRoute('leads.index', navigate: true);
    }
}; ?>

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ __('Capturar lead') }}</h2>
            <a href="{{ route('leads.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                &larr; {{ __('Volver a la bandeja') }}
            </a>
        </div>

        {{-- Advertencia de posibles duplicados (§3.3) --}}
        @if ($this->possibleDuplicates->isNotEmpty())
            <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-4">
                <p class="text-sm font-medium text-amber-800">
                    {{ __('Posibles duplicados por correo o teléfono:') }}
                </p>
                <ul class="mt-2 space-y-1 text-sm text-amber-700">
                    @foreach ($this->possibleDuplicates as $dup)
                        <li>
                            #{{ $dup->id }} — {{ $dup->full_name }}
                            @if ($dup->company) ({{ $dup->company }}) @endif
                            · {{ $dup->status?->name }}
                            @if ($dup->vendor) · {{ __('Vendedor:') }} {{ $dup->vendor->name }} @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-amber-600">
                    {{ __('Puedes continuar de todos modos si confirmas que es un prospecto distinto.') }}
                </p>
            </div>
        @endif

        <form wire:submit="save" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-8">
            {{-- Contacto y empresa --}}
            <section>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">{{ __('Contacto') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="first_name" :value="__('Nombre *')" />
                        <x-text-input wire:model="first_name" id="first_name" class="block mt-1 w-full" type="text" required autofocus />
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="last_name" :value="__('Apellido')" />
                        <x-text-input wire:model="last_name" id="last_name" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="company" :value="__('Empresa')" />
                        <x-text-input wire:model="company" id="company" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('company')" class="mt-2" />
                    </div>
                    <div></div>
                    <div>
                        <x-input-label for="email" :value="__('Correo electrónico')" />
                        <x-text-input wire:model.live.debounce.500ms="email" id="email" class="block mt-1 w-full" type="email" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="phone" :value="__('Teléfono')" />
                        <x-text-input wire:model.live.debounce.500ms="phone" id="phone" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                </div>
            </section>

            {{-- Ubicación e idioma --}}
            <section>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">{{ __('Ubicación e idioma') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="country_id" :value="__('País')" />
                        <select wire:model.live="country_id" id="country_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('country_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="city_id" :value="__('Ciudad')" />
                        <select wire:model="city_id" id="city_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('city_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="language_id" :value="__('Idioma')" />
                        <select wire:model="language_id" id="language_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->languages as $language)
                                <option value="{{ $language->id }}">{{ $language->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('language_id')" class="mt-2" />
                    </div>
                </div>
            </section>

            {{-- Origen --}}
            <section>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">{{ __('Origen') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="source_id" :value="__('Fuente de llegada')" />
                        <select wire:model="source_id" id="source_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('source_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="campaign_id" :value="__('Campaña')" />
                        <select wire:model="campaign_id" id="campaign_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('campaign_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="landing" :value="__('Landing page')" />
                        <x-text-input wire:model="landing" id="landing" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('landing')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="keyword" :value="__('Palabra clave')" />
                        <x-text-input wire:model="keyword" id="keyword" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('keyword')" class="mt-2" />
                    </div>
                </div>
            </section>

            {{-- Proyecto --}}
            <section>
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">{{ __('Proyecto') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="project_type_id" :value="__('Tipo de proyecto')" />
                        <select wire:model="project_type_id" id="project_type_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('— Selecciona —') }}</option>
                            @foreach ($this->projectTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('project_type_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="budget" :value="__('Presupuesto')" />
                        <x-text-input wire:model="budget" id="budget" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('budget')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="deadline" :value="__('Plazo')" />
                        <x-text-input wire:model="deadline" id="deadline" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('deadline')" class="mt-2" />
                    </div>
                    <div class="md:col-span-3">
                        <x-input-label for="description" :value="__('Descripción inicial')" />
                        <textarea wire:model="description" id="description" rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('leads.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancelar') }}</a>
                <x-primary-button>{{ __('Guardar lead') }}</x-primary-button>
            </div>
        </form>
    </div>
</div>
