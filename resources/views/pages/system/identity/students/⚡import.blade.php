<?php

declare(strict_types=1);

use App\Imports\StudentsImport;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Importar Estudiantes')] class extends Component {
    use WithFileUploads;

    public ?int $gradeId = null;
    public ?int $nivelId = null;
    public ?int $shiftId = null;
    public ?array $grade = null;
    public $file = null;
    public string $storedPath = '';
    public array $previewData = [];
    public int $totalRows = 0;
    public int $validRows = 0;
    public int $errorRows = 0;
    public bool $showPreview = false;
    public bool $importing = false;
    public string $errorMessage = '';
    public bool $isAdmin = false;

    protected function rules(): array
    {
        return [
            'gradeId' => ['required', 'exists:grades,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'gradeId' => 'grado',
            'file' => 'archivo Excel',
        ];
    }

    public function mount(?int $gradeId = null): void
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['DOCENTE', 'TUTOR', 'ADMIN', 'SUPER-ADMIN'])) {
            abort(403, 'No tiene permisos para importar estudiantes.');
        }

        $this->isAdmin = $user->hasAnyRole(['ADMIN', 'SUPER-ADMIN']);
        $this->gradeId = $gradeId;
        $this->loadGrade();
    }

    public function getJornadasProperty()
    {
        return Shift::where('status', 1)
            ->orderBy('shift_name')
            ->get();
    }

    public function getNivelesProperty()
    {
        return Nivel::with('shift')
            ->where('status', 1)
            ->when($this->shiftId, fn ($q) => $q->where('shift_id', $this->shiftId))
            ->orderBy('nivel_name')
            ->get();
    }

    public function getGradesProperty()
    {
        if (!$this->nivelId) {
            return collect();
        }

        return Grade::with('nivel.shift')
            ->where('nivel_id', $this->nivelId)
            ->where('status', 1)
            ->orderBy('grade_name')
            ->get();
    }

    public function updatedShiftId(): void
    {
        $this->nivelId = null;
        $this->gradeId = null;
        $this->grade = null;
    }

    public function updatedNivelId(): void
    {
        $this->gradeId = null;
        $this->grade = null;
    }

    public function updatedGradeId(): void
    {
        $this->loadGrade();
    }

    protected function loadGrade(): void
    {
        if (!$this->gradeId) {
            $this->grade = null;
            return;
        }

        $grade = Grade::with('nivel.shift')->find($this->gradeId);
        if (!$grade) {
            $this->gradeId = null;
            $this->grade = null;
            return;
        }

        $this->nivelId = $grade->nivel_id;
        $this->shiftId = $grade->nivel->shift_id ?? null;
        $this->grade = [
            'id' => $grade->id,
            'name' => $grade->grade_name . ' ' . ($grade->section ?? ''),
            'nivel' => $grade->nivel->nivel_name ?? '',
            'shift' => $grade->nivel->shift->shift_name ?? '',
        ];
    }

    public function processFile(): void
    {
        $this->errorMessage = '';
        $this->validate();

        try {
            $this->storedPath = $this->file->store('imports', 'local');
            $fullPath = storage_path('app/private/' . $this->storedPath);

            $import = new StudentsImport(previewOnly: true, gradeId: $this->gradeId);
            Excel::import($import, $fullPath);

            $this->previewData = $import->getRows();
            $this->totalRows = $import->getTotalRows();
            $this->validRows = $import->getValidRows();
            $this->errorRows = $import->getErrorRows();
            $this->showPreview = true;
        } catch (\Exception $e) {
            $this->errorMessage = 'Error al procesar el archivo: ' . $e->getMessage();
        }
    }

    public function confirmImport(): void
    {
        $this->errorMessage = '';
        $this->importing = true;

        try {
            $fullPath = storage_path('app/private/' . $this->storedPath);
            $import = new StudentsImport(previewOnly: false, gradeId: $this->gradeId);
            Excel::import($import, $fullPath);

            @unlink($fullPath);

            Flux::toast(
                variant: 'success',
                text: "Importacion completada. Registros procesados: {$this->totalRows}, validos: {$this->validRows}, con errores: {$this->errorRows}."
            );
            $this->redirect(route('system.identity.students.index'), navigate: true);
        } catch (\Exception $e) {
            $this->importing = false;
            $this->errorMessage = 'Error al importar: ' . $e->getMessage();
        }
    }

    public function resetImport(): void
    {
        if ($this->storedPath && file_exists(storage_path('app/private/' . $this->storedPath))) {
            @unlink(storage_path('app/private/' . $this->storedPath));
        }
        $this->reset(['file', 'storedPath', 'previewData', 'totalRows', 'validRows', 'errorRows', 'showPreview', 'importing', 'errorMessage']);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Importar Estudiantes') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Cargar estudiantes desde un archivo Excel') }}</flux:text>
            @if($this->grade)
                <div class="flex items-center gap-2 mt-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-sm font-medium border border-blue-200 dark:border-blue-800">
                        <flux:icon.academic-cap class="size-3.5" />
                        {{ $grade['name'] }}
                    </span>
                    @if($grade['nivel'])
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $grade['nivel'] }}</span>
                    @endif
                    @if($grade['shift'])
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ $grade['shift'] }}</span>
                    @endif
                </div>
            @endif
        </div>
        <flux:button href="{{ route('system.identity.students.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.students.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Estudiantes') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Importar') }}</span>
    </nav>

    @if (!$this->showPreview)
        @if ($this->errorMessage)
            <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 mb-6 max-w-2xl">
                <div class="flex gap-3">
                    <flux:icon.exclamation-circle class="text-red-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-red-700 dark:text-red-300">
                        <p class="font-medium">{{ __('Error') }}</p>
                        <p>{{ $this->errorMessage }}</p>
                    </div>
                </div>
            </div>
        @endif
        @if ($this->isAdmin)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 max-w-2xl mb-6">
            <div class="mb-4">
                <flux:heading size="md" class="mb-2">{{ __('Seleccionar Grado') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Seleccione el nivel y grado al que pertenecen los estudiantes a importar.') }}</flux:text>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>{{ __('Jornada') }} *</flux:label>
                    <flux:select wire:model.live="shiftId">
                        <option value="">-- {{ __('Seleccionar jornada') }} --</option>
                        @foreach($this->jornadas as $jornada)
                            <option value="{{ $jornada->id }}" @selected($this->shiftId === $jornada->id)>
                                {{ $jornada->shift_name }}
                            </option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Nivel') }} *</flux:label>
                    <flux:select wire:model.live="nivelId">
                        <option value="">-- {{ __('Seleccionar nivel') }} --</option>
                        @foreach($this->niveles as $nivel)
                            <option value="{{ $nivel->id }}" @selected($this->nivelId === $nivel->id)>
                                {{ $nivel->nivel_name }}@if($nivel->shift) - {{ $nivel->shift->shift_name }}@endif
                            </option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Grado') }} *</flux:label>
                    <flux:select wire:model.live="gradeId">
                        <option value="">-- {{ __('Seleccionar grado') }} --</option>
                        @foreach($this->grades as $grade)
                            <option value="{{ $grade->id }}" @selected($this->gradeId === $grade->id)>
                                {{ $grade->grade_name }} {{ $grade->section ? '/ '.$grade->section : '' }}
                            </option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        </div>
        @endif

        @if($this->grade)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 max-w-2xl">
            <div class="mb-4">
                <flux:heading size="md" class="mb-2">{{ __('Subir Archivo') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Seleccione el archivo Excel con los datos de los estudiantes a importar.') }}</flux:text>
            </div>

            <form wire:submit="processFile">
                <div class="mb-4">
                    <flux:field>
                        <flux:label>{{ __('Archivo Excel') }} *</flux:label>
                        <input type="file" wire:model="file" name="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-zinc-700 dark:text-zinc-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/30 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50" />
                        @error('file') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                    </flux:field>
                </div>

                <div class="flex items-center gap-3 mb-4">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <flux:icon.arrow-up-tray /> {{ __('Previsualizar') }}
                    </flux:button>
                    <a href="{{ route('system.identity.templates.download', 'estudiantes') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 rounded-lg hover:bg-zinc-200 dark:hover:bg-zinc-700 transition">
                        <flux:icon.arrow-down-tray /> {{ __('Descargar Plantilla') }}
                    </a>
                </div>
            </form>

            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
                <div class="flex gap-3">
                    <flux:icon.information-circle class="text-blue-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium mb-1">{{ __('Formato de la plantilla:') }}</p>
                        <p>{{ __('El archivo debe contener las columnas:') }} <strong>NOMBRES,APELLIDOS,DNI,EMAIL,TELEFONO,CELULAR,DIRECCION, FECHA_NACIMIENTO, TIPO_SANGRE, CONTACTO_EMERGENCIA, INFO_MEDICA</strong></p>
                        <p class="mt-1">{{ __('El email es opcional. Si se omite, se creara uno automaticamente (nombre.apellido.CODIGO@educaplus.edu.ec).') }}</p>
                        <p class="mt-1">{{ __('Los codigos de estudiante se generaran automaticamente. Si el DNI ya existe, se actualizara el registro.') }}</p>
                        <p class="mt-1">{{ __('La contrasena inicial sera: DNI + "passsword"') }}</p>
                    </div>
                </div>
            </div>
        </div>
        @else
            <flux:callout variant="info" icon="information-circle">
                {{ __('Seleccione un grado para habilitar la carga del archivo Excel.') }}
            </flux:callout>
        @endif
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                <flux:heading size="md">{{ __('Vista Previa') }}</flux:heading>
                <div class="flex gap-2">
                    @if ($this->errorRows === 0)
                        <form wire:submit="confirmImport">
                                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                                    <flux:icon.check /> {{ __('Confirmar Importacion') }} ({{ $this->validRows }} {{ __('registros') }})
                            </flux:button>
                        </form>
                    @else
                        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-0">
                            {{ __('Hay') }} {{ $this->errorRows }} {{ __('registro(s) con errores. Corrija el archivo e intente de nuevo.') }}
                        </flux:callout>
                    @endif
                    <form wire:submit="resetImport">
                        <flux:button type="submit" variant="ghost">{{ __('Subir otro archivo') }}</flux:button>
                    </form>
                </div>
            </div>

            <div class="flex gap-4 mb-4">
                <flux:badge color="blue">{{ __('Total') }}: {{ $this->totalRows }}</flux:badge>
                <flux:badge color="green">{{ __('Validos') }}: {{ $this->validRows }}</flux:badge>
                @if ($this->errorRows > 0)
                    <flux:badge color="red">{{ __('Errores') }}: {{ $this->errorRows }}</flux:badge>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">#</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Apellido') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('DNI') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Telefono') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Celular') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Fecha Nac.') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($previewData as $index => $row)
                            <tr class="{{ isset($row['errors']) ? 'bg-red-50 dark:bg-red-900/10' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                                <td class="px-3 py-2 text-zinc-500">{{ $index + 1 }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['name'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['lastname'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['dni'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['phone'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['cellphone'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['birth_date'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    @if (isset($row['errors']))
                                        <span class="text-red-600 dark:text-red-400 text-xs">{{ $row['errors'] }}</span>
                                    @else
                                        <span class="text-emerald-600 dark:text-emerald-400 text-xs">{{ __('Valido') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
