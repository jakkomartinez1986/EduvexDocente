<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\School;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar Colegio')] class extends Component {
    use WithFileUploads;

    public ?School $school = null;
    public string $name_school = '';
    public string $distrit = '';
    public string $location = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public $logo = null;
    public $reportLogo = null;

    public function mount(int $id): void
    {
        $this->school = School::findOrFail($id);

        $this->fill([
            'name_school' => $this->school->name_school,
            'distrit' => $this->school->distrit ?? '',
            'location' => $this->school->location ?? '',
            'address' => $this->school->address ?? '',
            'phone' => $this->school->phone ?? '',
            'email' => $this->school->email ?? '',
            'website' => $this->school->website ?? '',
        ]);
    }

    protected function rules(): array
    {
        return [
            'name_school' => ['required', 'string', 'max:255'],
            'distrit' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp,svg'],
            'reportLogo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp,svg'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name_school' => 'nombre del colegio',
            'distrit' => 'distrito',
            'location' => 'ubicacion',
            'address' => 'direccion',
            'phone' => 'telefono',
            'email' => 'email',
            'website' => 'sitio web',
            'logo' => 'logo principal',
            'reportLogo' => 'logo de reportes',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $data = [
            'name_school' => $this->name_school,
            'distrit' => $this->distrit ?: null,
            'location' => $this->location ?: null,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'website' => $this->website ?: null,
        ];

        if ($this->logo) {
            $data['logo_path'] = $this->storeLogo($this->logo, $this->school->logo_path);
        }

        if ($this->reportLogo) {
            $data['report_logo_path'] = $this->storeLogo($this->reportLogo, $this->school->report_logo_path);
        }

        $this->school->update($data);

        Flux::toast(variant: 'success', text: __('Colegio actualizado correctamente.'));
        $this->redirect(route('admin.schools.index'), navigate: true);
    }

    protected function storeLogo($file, ?string $oldPath): string
    {
        $path = $file->store('schools/logos', 'public');

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    public function removeLogo(string $field): void
    {
        $attribute = $field === 'report_logo_path' ? 'report_logo_path' : 'logo_path';

        if ($this->school->{$attribute}) {
            Storage::disk('public')->delete($this->school->{$attribute});
            $this->school->update([$attribute => null]);
        }

        if ($attribute === 'report_logo_path') {
            $this->reportLogo = null;
        } else {
            $this->logo = null;
        }

        Flux::toast(variant: 'success', text: __('Logo eliminado correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Colegio') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del colegio') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.schools.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.schools.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Colegios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Colegio') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="name_school" />
                    @error('name_school') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Distrito') }}</flux:label>
                    <flux:input wire:model="distrit" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono') }}</flux:label>
                    <flux:input wire:model="phone" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Sitio Web') }}</flux:label>
                    <flux:input wire:model="website" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Ubicacion') }}</flux:label>
                    <flux:input wire:model="location" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Direccion') }}</flux:label>
                <flux:input wire:model="address" />
            </flux:field>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-1">{{ __('Logos de la Institucion') }}</flux:heading>
            <flux:text variant="subtle" class="mb-4">{{ __('Actualice o modifique los logos utilizados en carnets y reportes.') }}</flux:text>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <flux:label>{{ __('Logo Principal') }}</flux:label>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="size-20 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 overflow-hidden flex items-center justify-center flex-shrink-0">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="{{ __('Logo') }}" class="w-full h-full object-cover" />
                            @elseif($this->school->logo_path)
                                <img src="{{ asset('storage/' . $this->school->logo_path) }}" alt="{{ $this->school->name_school }}" class="w-full h-full object-cover" />
                            @else
                                <flux:icon.building-library class="size-10 text-zinc-300 dark:text-zinc-600" />
                            @endif
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="file" wire:model.live="logo" accept="image/*"
                                   class="block w-full text-sm text-zinc-700 dark:text-zinc-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50" />
                            @error('logo') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                            @if($this->school->logo_path)
                                <button type="button" wire:click="removeLogo('logo_path')" wire:confirm="{{ __('Eliminar el logo principal?') }}"
                                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    {{ __('Quitar logo') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <flux:label>{{ __('Logo para Reportes') }}</flux:label>
                    <div class="mt-2 flex items-center gap-4">
                        <div class="size-20 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 overflow-hidden flex items-center justify-center flex-shrink-0">
                            @if($reportLogo)
                                <img src="{{ $reportLogo->temporaryUrl() }}" alt="{{ __('Logo') }}" class="w-full h-full object-cover" />
                            @elseif($this->school->report_logo_path)
                                <img src="{{ asset('storage/' . $this->school->report_logo_path) }}" alt="{{ $this->school->name_school }}" class="w-full h-full object-cover" />
                            @else
                                <flux:icon.document-text class="size-10 text-zinc-300 dark:text-zinc-600" />
                            @endif
                        </div>
                        <div class="flex-1 space-y-2">
                            <input type="file" wire:model.live="reportLogo" accept="image/*"
                                   class="block w-full text-sm text-zinc-700 dark:text-zinc-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50" />
                            @error('reportLogo') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                            @if($this->school->report_logo_path)
                                <button type="button" wire:click="removeLogo('report_logo_path')" wire:confirm="{{ __('Eliminar el logo de reportes?') }}"
                                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                                    {{ __('Quitar logo') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Colegio') }}</flux:button>
            <flux:button href="{{ route('admin.schools.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
