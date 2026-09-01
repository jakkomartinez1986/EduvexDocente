{{-- Modal: Calificaciones Rapidas --}}
@if($this->showQuickGradesModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.5)" wire:click="closeQuickGradesModal">
        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col" wire:click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <flux:icon.document-check class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Registrar Calificaciones') }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->quickGradesActivityName }} · {{ count($this->quickGradesStudents) }} {{ __('estudiantes') }}</p>
                    </div>
                </div>
                <button wire:click="closeQuickGradesModal" class="w-8 h-8 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 transition">
                    <flux:icon.x-mark class="w-4 h-4" />
                </button>
            </div>

            @if(!$this->quickGradesActivityId && $this->quickGradesActivityName === 'Sin bloque configurado')
                <div class="flex-1 flex items-center justify-center text-zinc-400">
                    <div class="text-center py-8">
                        <flux:icon.exclamation-circle class="mx-auto mb-2 size-8 text-amber-300 dark:text-amber-600" />
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('No hay bloques de evaluación configurados') }}</p>
                        <p class="text-xs text-zinc-400 mt-1">{{ __('Crea bloques y actividades en el Libro de Calificaciones primero.') }}</p>
                    </div>
                </div>
            @else
                <div class="flex flex-1 min-h-0">
                    {{-- Sidebar: Control Panel --}}
                    <div class="w-44 border-r border-zinc-200 dark:border-zinc-700 flex flex-col bg-zinc-50 dark:bg-zinc-800/50">
                        {{-- Summary --}}
                        <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Resumen') }}</span>
                            @php
                                $graded = collect($this->quickGradesValues)->filter(fn ($v) => $v !== '')->count();
                                $total = count($this->quickGradesStudents);
                            @endphp
                            <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-emerald-50 dark:bg-emerald-900/20 mb-1.5">
                                <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">{{ $graded }}/{{ $total }}</span>
                                <span class="text-[10px] text-emerald-600/60">{{ __('con nota') }}</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5">
                                <div class="bg-emerald-500 h-1.5 rounded-full transition-all" style="width: {{ $total > 0 ? ($graded / $total * 100) : 0 }}%"></div>
                            </div>
                        </div>

                        {{-- Activity Selector --}}
                        @if(count($this->quickGradesActivities) > 0)
                            <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700">
                                <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Actividad') }}</span>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    @foreach($this->quickGradesActivities as $act)
                                        <button wire:click="selectQuickGradesActivity({{ $act['id'] }})"
                                            class="w-full text-left px-2 py-1.5 rounded text-[11px] transition truncate
                                                   {{ $this->quickGradesSelectedActivity === $act['id']
                                                      ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 font-bold'
                                                      : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700' }}"
                                            title="{{ $act['name'] }}{{ $act['date'] ? ' ('.$act['date'].')' : '' }}">
                                            <span class="truncate block">{{ $act['name'] }}</span>
                                            @if($act['date'])
                                                <span class="text-[9px] text-zinc-400 block">{{ $act['date'] }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Page Navigation --}}
                        <div class="px-3 py-3 flex-1 flex flex-col">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Pagina') }}</span>
                            <div class="flex flex-wrap gap-1 mb-3">
                                @php
                                    $totalPages = $this->getQuickGradesTotalPages();
                                    $currentPage = $this->quickGradesPage;
                                @endphp
                                @for($p = 0; $p < $totalPages; $p++)
                                    <button wire:click="goToQuickGradesPage({{ $p }})"
                                        class="w-7 h-7 rounded text-[11px] font-bold transition
                                               {{ $p === $currentPage ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-500 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}">
                                        {{ $p + 1 }}
                                    </button>
                                @endfor
                            </div>
                            <div class="flex gap-1.5 mt-auto">
                                <button wire:click="prevQuickGradesPage" {{ $this->quickGradesPage === 0 ? 'disabled' : '' }}
                                    class="flex-1 px-2 py-1.5 rounded text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                                    &lsaquo; {{ __('Ant') }}
                                </button>
                                <button wire:click="nextQuickGradesPage" {{ $this->quickGradesPage >= $totalPages - 1 ? 'disabled' : '' }}
                                    class="flex-1 px-2 py-1.5 rounded text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                                    {{ __('Sig') }} &rsaquo;
                                </button>
                            </div>
                            <div class="text-center mt-2">
                                <span class="text-[10px] text-zinc-400">{{ count($this->quickGradesStudents) }} {{ __('alumnos') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 flex flex-col min-h-0">
                        {{-- Content Header --}}
                        <div class="px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                            <span class="text-xs text-zinc-400">
                                {{ $this->quickGradesActivityName }}
                            </span>
                            <span class="text-[11px] text-zinc-400">
                                {{ __('Mostrando') }} {{ $this->quickGradesPage * 8 + 1 }}–{{ min(($this->quickGradesPage + 1) * 8, count($this->quickGradesStudents)) }}
                            </span>
                        </div>

                        {{-- Students Page --}}
                        <div class="flex-1 overflow-y-auto px-4 py-3">
                            @if(count($this->quickGradesStudents) > 0)
                                <div class="grid grid-cols-[1fr,80px] gap-x-3 gap-y-0 text-[10px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider px-1 mb-2">
                                    <span>{{ __('Estudiante') }}</span>
                                    <span class="text-center">{{ __('Nota (0-10)') }}</span>
                                </div>
                                @php $paginatedStudents = $this->getQuickGradesPaginatedStudents(); @endphp
                                @foreach($paginatedStudents as $student)
                                    <div class="grid grid-cols-[1fr,80px] gap-x-3 items-center py-2.5 border-b border-zinc-100 dark:border-zinc-700 last:border-b-0">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center text-[10px] font-bold text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                                                {{ strtoupper(mb_substr($student['name'], 0, 1)) }}{{ strtoupper(mb_substr(trim(explode(' ', $student['name'])[1] ?? ''), 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $student['name'] }}</div>
                                                <div class="text-[10px] font-mono text-zinc-400">{{ $student['code'] }}</div>
                                            </div>
                                        </div>
                                        <input type="number" min="0" max="10" step="0.1" placeholder="—"
                                               class="w-full text-center px-1 py-1.5 border border-zinc-200 dark:border-zinc-600 rounded-lg text-sm font-mono bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-2 focus:outline-blue-500"
                                                value="{{ $this->quickGradesValues[$student['id']] ?? '' }}"
                                               wire:model.blur="quickGradesValues.{{ $student['id'] }}">
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-8 text-zinc-400">
                                    <flux:icon.user-group class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-sm">{{ __('No hay estudiantes matriculados.') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-zinc-200 dark:border-zinc-700">
                <button wire:click="closeQuickGradesModal"
                    class="px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm font-medium transition">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="saveQuickGrades" {{ !$this->quickGradesActivityId ? 'disabled' : '' }}
                    class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <flux:icon.check class="size-4" /> {{ __('Guardar calificaciones') }}
                </button>
            </div>
        </div>
    </div>
@endif
