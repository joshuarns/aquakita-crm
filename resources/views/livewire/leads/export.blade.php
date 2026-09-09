<?php

use App\Models\Campaign;
use App\Models\Country;
use App\Models\Language;
use App\Models\ProjectType;
use App\Exports\LeadsSheet;
use App\Models\Source;
use App\Models\Status;
use App\Models\User;
use App\Support\LeadExport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $country_id = null;
    public ?int $language_id = null;
    public ?int $source_id = null;
    public ?int $campaign_id = null;
    public ?int $project_type_id = null;
    public ?int $status_id = null;
    public ?int $vendor_id = null;

    public string $format = 'csv';

    /**
     * @return array<string, int|null>
     */
    private function filters(): array
    {
        return [
            'country_id' => $this->country_id,
            'language_id' => $this->language_id,
            'source_id' => $this->source_id,
            'campaign_id' => $this->campaign_id,
            'project_type_id' => $this->project_type_id,
            'status_id' => $this->status_id,
            'vendor_id' => $this->vendor_id,
        ];
    }

    /** Vista previa del número de contactos exportables (§9). */
    #[Computed]
    public function preview(): int
    {
        return (new LeadExport($this->filters()))->count();
    }

    #[Computed]
    public function countries()
    {
        return Country::orderBy('name')->get();
    }

    #[Computed]
    public function languages()
    {
        return Language::orderBy('name')->get();
    }

    #[Computed]
    public function sources()
    {
        return Source::orderBy('name')->get();
    }

    #[Computed]
    public function campaigns()
    {
        return Campaign::orderBy('name')->get();
    }

    #[Computed]
    public function projectTypes()
    {
        return ProjectType::orderBy('name')->get();
    }

    #[Computed]
    public function statuses()
    {
        return Status::orderBy('order')->get();
    }

    #[Computed]
    public function vendors()
    {
        return User::role('vendedor')->orderBy('name')->get();
    }

    /**
     * Descarga la base limpia en CSV o Excel para cargarla en Mailchimp (§9).
     */
    public function download(): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('leads.export'); // permiso del administrador (§2)

        $export = new LeadExport($this->filters());

        if ($this->format === 'xlsx') {
            return Excel::download(new LeadsSheet($export), 'contactos-aquakita-'.now()->format('Y-m-d').'.xlsx');
        }

        return response()->streamDownload(
            fn () => print ($export->toCsv()),
            $export->filename(),
            ['Content-Type' => 'text/csv'],
        );
    }
}; ?>

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-2">{{ __('Exportar contactos') }}</h2>
        <p class="text-sm text-gray-500 mb-6">
            {{ __('Prepara una base limpia en CSV para cargarla en Mailchimp u otra plataforma de correo masivo. La exportación no envía correos.') }}
        </p>

        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label :value="__('País')" />
                    <select wire:model.live="country_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($this->countries as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Idioma')" />
                    <select wire:model.live="language_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($this->languages as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Fuente')" />
                    <select wire:model.live="source_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach ($this->sources as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Campaña')" />
                    <select wire:model.live="campaign_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todas') }}</option>
                        @foreach ($this->campaigns as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Tipo de proyecto')" />
                    <select wire:model.live="project_type_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($this->projectTypes as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Estatus')" />
                    <select wire:model.live="status_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($this->statuses as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Vendedor')" />
                    <select wire:model.live="vendor_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach ($this->vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="rounded-md bg-gray-50 border border-gray-200 p-4 text-sm text-gray-600">
                <p class="font-medium text-gray-800">{{ __('Se exportarán') }}: {{ $this->preview }} {{ __('contactos') }}</p>
                <p class="mt-1 text-xs">
                    {{ __('Excluidos automáticamente (§9): sin correo, duplicados, marcados «No enviar publicidad» y quienes solicitaron darse de baja.') }}
                </p>
            </div>

            <div class="flex items-center justify-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Formato') }}</label>
                    <select wire:model="format" class="mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="csv">CSV</option>
                        <option value="xlsx">Excel (.xlsx)</option>
                    </select>
                </div>
                <x-primary-button wire:click="download" :disabled="$this->preview === 0" class="self-end">
                    {{ __('Descargar') }}
                </x-primary-button>
            </div>
        </div>
    </div>
</div>
