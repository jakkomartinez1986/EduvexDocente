<?php

declare(strict_types=1);

use App\Exports\CalendarDaysTemplateExport;
use App\Imports\CalendarDaysImport;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Importar Calendario')] class extends Component {
    use WithFileUploads;

    public ?int $yearId = null;
    public ?array $year = null;
    public $file = null;
    public string $storedPath = '';
    public array $previewData = [];
    public int $totalRows = 0;
    public int $validRows = 0;
    public int $errorRows = 0;
    public bool $showPreview = false;
    public bool $importing = false;
    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'file' => 'archivo Excel',
        ];
    }

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();

        $year = ScolarYear::find($this->yearId);
        if (!$year) {
            abort(404, 'No hay ano escolar activo.');
        }

        $this->year = [
            'id' => $year->id,
            'name' => $year->year_name,
            'start' => $year->start_date?->format('d/m/Y') ?? '',
            'end' => $year->end_date?->format('d/m/Y') ?? '',
        ];
    }

    public function processFile(): void
    {
        $this->errorMessage = '';
        $this->validate();

        try {
            $this->storedPath = $this->file->store('imports', 'local');
            $fullPath = storage_path('app/private/' . $this->storedPath);

            $import = new CalendarDaysImport(previewOnly: true, yearId: $this->yearId);
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
            $import = new CalendarDaysImport(previewOnly: false, yearId: $this->yearId);
            Excel::import($import, $fullPath);

            @unlink($fullPath);

            Flux::toast(
                variant: 'success',
                text: "Importacion completada. Registros procesados: {$this->totalRows}, validos: {$this->validRows}, con errores: {$this->errorRows}."
            );
            $this->redirect(route('admin.settings.calendar-scolars.index'), navigate: true);
        } catch (\Exception $e) {
            $this->importing = false;
            $this->errorMessage = 'Error al importar: ' . $e->getMessage();
        }
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\Response
    {
        $fileName = 'plantilla_calendario_' . date('Y-m-d') . '.xlsx';
        $fullPath = storage_path('app/private/' . $fileName);

        if (!file_exists($fullPath)) {
            Excel::store(new CalendarDaysTemplateExport, $fileName, 'private');
        }

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        return response()->download($fullPath, $fileName, $headers);
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
            <flux:heading size="xl">{{ __('Importar Calendario Escolar') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Cargar dias del calendario desde un archivo Excel') }}</flux:text>
            @if($this->year)
                <div class="flex items-center gap-2 mt-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-sm font-medium border border-blue-200 dark:border-blue-800">
                        <flux:icon.calendar class="size-3.5" />
                        {{ $this->year['name'] }}
                    </span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->year['start'] }} — {{ $this->year['end'] }}</span>
                </div>
            @endif
        </div>
        <flux:button href="{{ route('admin.settings.calendar-scolars.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.calendar-scolars.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Calendario') }}</a>
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
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 max-w-2xl">
            <div class="mb-4">
                <flux:heading size="md" class="mb-2">{{ __('Subir Archivo') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Seleccione el archivo Excel con los dias del calendario escolar a importar.') }}</flux:text>
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
                    <button type="button" wire:click="downloadTemplate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 rounded-lg hover:bg-zinc-200 dark:hover:bg-zinc-700 transition">
                        <flux:icon.arrow-down-tray /> {{ __('Descargar Plantilla') }}
                    </button>
                </div>
            </form>

            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
                <div class="flex gap-3">
                    <flux:icon.information-circle class="text-blue-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-medium mb-1">{{ __('Formato de la plantilla:') }}</p>
                        <p>{{ __('El archivo debe contener las columnas:') }} <strong>FECHA, ACTIVIDAD, FERIADO, TRIMESTRE</strong></p>
                        <p class="mt-1">{{ __('FECHA:') }} {{ __('Formato YYYY-MM-DD (ej: 2025-09-01).') }}</p>
                        <p class="mt-1">{{ __('ACTIVIDAD:') }} {{ __('Descripcion de la actividad del dia (opcional).') }}</p>
                        <p class="mt-1">{{ __('FERIADO:') }} {{ __('SI o NO. Los sabados y domingos se marcan automaticamente como feriados.') }}</p>
                        <p class="mt-1">{{ __('TRIMESTRE:') }} {{ __('Opcional. El trimestre se asigna automaticamente segun la fecha.') }}</p>
                        <p class="mt-1">{{ __('Si la fecha ya existe, se actualizara el registro.') }}</p>
                    </div>
                </div>
            </div>
        </div>
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
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Fecha') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Dia') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Mes') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actividad') }}</th>
                            <th class="px-3 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Feriado') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Trimestre') }}</th>
                            <th class="px-3 py-2 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($previewData as $index => $row)
                            <tr class="{{ isset($row['errors']) ? 'bg-red-50 dark:bg-red-900/10' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                                <td class="px-3 py-2 text-zinc-500">{{ $index + 1 }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300 font-mono text-xs">{{ $row['date'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['day_name'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['month_name'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300">{{ $row['activity'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if(($row['is_holiday'] ?? false))
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">SI</span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">NO</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-zinc-700 dark:text-zinc-300 text-xs">{{ $row['period'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if (isset($row['errors']))
                                        <span class="text-red-600 dark:text-red-400 text-xs">{{ $row['errors'] }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                            {{ ($row['status'] ?? '') === 'actualizar' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ ($row['status'] ?? '') === 'actualizar' ? __('Actualizar') : __('Nuevo') }}
                                        </span>
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
