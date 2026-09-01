@php
    $activeBlock = $this->assessmentBlocks->firstWhere('id', $activeBlockId);
    $studentPaginator = $this->getStudents();
@endphp

@if($activeBlock)
    <div wire:key="view-block-detail" class="mb-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                            {{ $activeBlock->name ?? 'Bloque #' . $activeBlock->id }}
                        </h3>
                        @if($activeBlock->internal_percentage)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ $activeBlock->internal_percentage }}%
                            </span>
                        @endif
                    </div>
                    @if($activeBlock->description)
                        <p class="text-xs text-zinc-500 mt-1">{{ $activeBlock->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @php $blockAvg = $this->getBlockAverageForDisplay($activeBlock->id); @endphp
                    @if($blockAvg !== null)
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $this->getPerformanceColor($blockAvg) }} bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                            {{ __('Promedio del bloque:') }} {{ number_format($blockAvg, 2) }}
                        </span>
                    @endif
                    @if($this->isGradingOpen())
                        <flux:button size="sm" variant="subtle" icon="pencil-square"
                                     wire:click="openEditBlock({{ $activeBlock->id }})">
                            {{ __('Editar') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" icon="trash" color="red"
                                     wire:click="deleteBlock({{ $activeBlock->id }})"
                                     wire:confirm="Eliminar este bloque y todas sus notas de actividades?">
                            {{ __('Eliminar') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if($this->isGradingOpen())
                <div class="px-5 py-3 bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-700">
                    @if(!$showInlineForm)
                        <flux:button size="sm" variant="subtle" icon="plus" wire:click="toggleInlineForm()">
                            {{ __('Agregar actividad') }}
                        </flux:button>
                    @else
                        <div class="grid grid-cols-6 gap-2 items-end">
                            <flux:input wire:model="activityForm.name" label="{{ __('Nombre') }}" size="sm" />
                            <flux:input wire:model="activityForm.topic" label="{{ __('Tema') }}" size="sm" />
                            <flux:textarea wire:model="activityForm.description" label="{{ __('Descripcion') }}" rows="1" size="sm" />
                            <flux:input wire:model="activityForm.date" type="date" label="{{ __('Fecha') }}" size="sm" min="{{ \Carbon\Carbon::today()->format('Y-m-d') }}" />
                            <flux:input wire:model="activityForm.max_score" type="number" min="0" step="0.1" label="{{ __('Nota Max') }}" size="sm" />
                            <div class="flex gap-1">
                                <flux:button size="sm" variant="primary" icon="check"
                                             wire:click="quickAddActivity({{ $activeBlock->id }})">
                                    {{ __('Guardar') }}
                                </flux:button>
                                <flux:button size="sm" variant="subtle" icon="x-mark"
                                             wire:click="toggleInlineForm()">
                                    {{ __('Cancelar') }}
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 sticky left-0 bg-zinc-50 dark:bg-zinc-800/50 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                {{ __('Estudiante') }}
                            </th>
                            @foreach($activeBlock->activities as $activity)
                                @php $actAvg = $this->getActivityAverage($activity->id); @endphp
                                <th class="px-2 py-3 text-center min-w-[100px]">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-[11px] font-bold text-zinc-900 dark:text-zinc-100" title="{{ $activity->name }}">
                                            {{ Str::limit($activity->name, 20) }}
                                        </span>
                                        @if($activity->date)
                                            <span class="text-[9px] text-zinc-400">{{ \Carbon\Carbon::parse($activity->date)->format('d/m') }}</span>
                                        @endif
                                        @if($activity->max_score)
                                            <span class="text-[9px] text-zinc-400">/{{ $activity->max_score }}</span>
                                        @endif
                                        @if($actAvg !== null)
                                            <span class="text-[9px] {{ $this->getPerformanceColor($actAvg) }}">
                                                {{ __('P:') }} {{ number_format($actAvg, 1) }}
                                            </span>
                                        @endif
                                        @if($this->isGradingOpen())
                                            <div class="flex gap-0.5">
                                                <flux:button size="xs" variant="subtle" icon="pencil-square"
                                                             wire:click="openEditActivity({{ $activeBlock->id }}, {{ $activity->id }})" />
                                                <flux:button size="xs" variant="subtle" icon="trash" color="red"
                                                             wire:click="deleteActivity({{ $activity->id }})"
                                                             wire:confirm="Eliminar esta actividad y sus notas?" />
                                            </div>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                            @if(count($activeBlock->activities) > 1)
                                <th class="px-3 py-3 text-center font-bold text-zinc-600 dark:text-zinc-400 min-w-[80px] border-l border-zinc-200 dark:border-zinc-700">
                                    {{ __('Prom.') }}
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($studentPaginator as $student)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100 text-xs sticky left-0 bg-white dark:bg-zinc-900 z-10 border-r border-zinc-200 dark:border-zinc-700">
                                    {{ $student->user?->full_name ?? $student->user?->lastname . ' ' . $student->user?->name }}
                                    <span class="block text-[10px] font-mono text-zinc-400">{{ $student->student_code }}</span>
                                </td>
                                @foreach($activeBlock->activities as $activity)
                                    @php
                                        $grade = $activity->grades->firstWhere('student_id', $student->id);
                                        $val = $grade?->grade;
                                    @endphp
                                    <td class="px-2 py-2 text-center">
                                        @if($this->isGradingOpen())
                                            <input type="number" min="0" max="10" step="0.1"
                                                   class="w-14 px-1 py-1 border border-zinc-200 dark:border-zinc-600 rounded text-xs font-mono text-center bg-white dark:bg-zinc-800 focus:outline-2 focus:outline-blue-500"
                                                   value="{{ $val ?? '' }}"
                                                   placeholder="—"
                                                   wire:change="saveGrade({{ $activity->id }}, {{ $student->id }}, $event.target.value)">
                                        @else
                                            <span class="font-mono text-xs {{ match(true) {
                                                $val !== null && $val >= 7 => 'text-emerald-700',
                                                $val !== null && $val >= 5 => 'text-amber-700',
                                                $val !== null => 'text-red-700',
                                                default => 'text-zinc-300',
                                            } }}">
                                                {{ $val ?? '—' }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                @if(count($activeBlock->activities) > 1)
                                    @php $blockAvg = $this->getStudentBlockAverage($student->id, $activeBlock->id); @endphp
                                    <td class="px-3 py-2.5 text-center font-bold border-l border-zinc-200 dark:border-zinc-700">
                                        <span class="font-mono text-xs {{ $this->getPerformanceColor($blockAvg) }}">
                                            {{ $blockAvg !== null ? number_format($blockAvg, 2) : '—' }}
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($activeBlock->activities) + 2 }}" class="px-4 py-12 text-center">
                                    <flux:icon.archive-box class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 px-4">{{ $studentPaginator->links() }}</div>
        </div>
    </div>
@else
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
        <flux:icon.document-text class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
        <p class="text-sm text-zinc-500">{{ __('Seleccione un bloque de evaluación para ver los detalles.') }}</p>
    </div>
@endif
