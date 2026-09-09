<?php

use App\Models\Campaign;
use App\Models\Country;
use App\Models\DiscardReason;
use App\Models\Language;
use App\Models\ProjectType;
use App\Models\Source;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $type = '';

    /** @var array<string, string> */
    public array $form = [];

    public ?int $editingId = null;

    /**
     * Configuración de cada catálogo simple (§3.2).
     *
     * @return array<string, array{model: class-string, label: string, singular: string, fields: array<string, string>}>
     */
    public function config(): array
    {
        return [
            'sources' => ['model' => Source::class, 'label' => 'Fuentes de llegada', 'singular' => 'Fuente', 'fields' => ['name' => 'Nombre']],
            'campaigns' => ['model' => Campaign::class, 'label' => 'Campañas', 'singular' => 'Campaña', 'fields' => ['name' => 'Nombre']],
            'project_types' => ['model' => ProjectType::class, 'label' => 'Tipos de proyecto', 'singular' => 'Tipo de proyecto', 'fields' => ['name' => 'Nombre']],
            'discard_reasons' => ['model' => DiscardReason::class, 'label' => 'Motivos de descarte', 'singular' => 'Motivo', 'fields' => ['name' => 'Nombre']],
            'languages' => ['model' => Language::class, 'label' => 'Idiomas', 'singular' => 'Idioma', 'fields' => ['code' => 'Código', 'name' => 'Nombre']],
            'countries' => ['model' => Country::class, 'label' => 'Países', 'singular' => 'País', 'fields' => ['name' => 'Nombre']],
        ];
    }

    public function mount(string $type): void
    {
        abort_unless(array_key_exists($type, $this->config()), 404);
        $this->type = $type;
        $this->resetForm();
    }

    /** @return array{model: class-string, label: string, singular: string, fields: array<string, string>} */
    private function meta(): array
    {
        return $this->config()[$this->type];
    }

    public function fields(): array
    {
        return $this->meta()['fields'];
    }

    public function label(): string
    {
        return $this->meta()['label'];
    }

    #[Computed]
    public function items()
    {
        $model = $this->meta()['model'];

        return $model::orderBy(array_key_first($this->fields()))->get();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = array_fill_keys(array_keys($this->fields()), '');
    }

    public function edit(int $id): void
    {
        $item = $this->meta()['model']::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->fields()) as $field) {
            $this->form[$field] = (string) $item->{$field};
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        $table = (new ($this->meta()['model']))->getTable();
        $rules = [];

        foreach (array_keys($this->fields()) as $field) {
            $rule = ['required', 'string', 'max:255'];
            if ($field === 'code') {
                $rule[] = Rule::unique($table, 'code')->ignore($this->editingId);
            }
            $rules["form.$field"] = $rule;
        }

        return $rules;
    }

    public function save(): void
    {
        $this->authorize('catalogs.manage');
        $this->validate($this->rules());

        $model = $this->meta()['model'];
        $data = collect($this->fields())->keys()
            ->mapWithKeys(fn ($f) => [$f => $this->form[$f]])->all();

        if ($this->editingId) {
            $model::findOrFail($this->editingId)->update($data);
            $message = 'Registro actualizado.';
        } else {
            $model::create($data + ['active' => true]);
            $message = 'Registro agregado.';
        }

        $this->resetForm();
        session()->flash('catalog_status', $message);
    }

    public function toggle(int $id): void
    {
        $this->authorize('catalogs.manage');
        $item = $this->meta()['model']::findOrFail($id);
        $item->update(['active' => ! $item->active]);
    }
}; ?>

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ $this->label() }}</h2>
            <a href="{{ route('catalogs.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                &larr; {{ __('Configuración') }}
            </a>
        </div>

        @if (session('catalog_status'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('catalog_status') }}</div>
        @endif

        {{-- Formulario alta/edición --}}
        <form wire:submit="save" class="bg-white shadow-sm sm:rounded-lg p-5 mb-6">
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                @foreach ($this->fields() as $field => $flabel)
                    <div class="flex-1 w-full">
                        <x-input-label :value="$flabel" />
                        <x-text-input wire:model="form.{{ $field }}" class="block mt-1 w-full text-sm" type="text" />
                        <x-input-error :messages="$errors->get('form.'.$field)" class="mt-1" />
                    </div>
                @endforeach
                <div class="flex gap-2">
                    <x-primary-button>{{ $editingId ? __('Guardar') : __('Agregar') }}</x-primary-button>
                    @if ($editingId)
                        <button type="button" wire:click="cancel" class="text-sm text-gray-600 hover:text-gray-900 px-3">{{ __('Cancelar') }}</button>
                    @endif
                </div>
            </div>
        </form>

        {{-- Listado --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        @foreach ($this->fields() as $flabel)
                            <th class="px-4 py-3">{{ $flabel }}</th>
                        @endforeach
                        <th class="px-4 py-3">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->items as $item)
                        <tr class="hover:bg-gray-50">
                            @foreach (array_keys($this->fields()) as $field)
                                <td class="px-4 py-3 text-gray-900">{{ $item->{$field} }}</td>
                            @endforeach
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $item->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $item->active ? __('Activo') : __('Inactivo') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $item->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">{{ __('Editar') }}</button>
                                <button wire:click="toggle({{ $item->id }})" class="text-gray-600 hover:text-gray-900 text-xs ml-3">
                                    {{ $item->active ? __('Desactivar') : __('Activar') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('Sin registros.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
