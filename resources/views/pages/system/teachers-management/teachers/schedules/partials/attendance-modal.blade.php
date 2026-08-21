{{-- Modal: Tomar Asistencia --}}
@if($this->showAttendanceModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.5)" wire:click="closeAttendanceModal">
        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col" wire:click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <flux:icon.clipboard-document-check class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Tomar Asistencia') }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ count($this->attendanceStudents) }} {{ __('estudiantes') }} · {{ $this->attendanceDate }}</p>
                    </div>
                </div>
                <button wire:click="closeAttendanceModal" class="w-8 h-8 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 transition">
                    <flux:icon.x-mark class="w-4 h-4" />
                </button>
            </div>

            <div class="flex flex-1 min-h-0">
                {{-- Sidebar: Control Panel --}}
                <div class="w-44 border-r border-zinc-200 dark:border-zinc-700 flex flex-col bg-zinc-50 dark:bg-zinc-800/50">
                    {{-- Status Summary --}}
                    <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Resumen') }}</span>
                        <div class="grid grid-cols-2 gap-1">
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-emerald-50 dark:bg-emerald-900/20">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'P')->count() }}</span>
                                <span class="text-[10px] text-emerald-600/60">P</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-amber-50 dark:bg-amber-900/20">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'A')->count() }}</span>
                                <span class="text-[10px] text-amber-600/60">A</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-red-50 dark:bg-red-900/20">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="text-[11px] font-bold text-red-700 dark:text-red-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'I')->count() }}</span>
                                <span class="text-[10px] text-red-600/60">I</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-blue-50 dark:bg-blue-900/20">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span class="text-[11px] font-bold text-blue-700 dark:text-blue-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'J')->count() }}</span>
                                <span class="text-[10px] text-blue-600/60">J</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-purple-50 dark:bg-purple-900/20">
                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                <span class="text-[11px] font-bold text-purple-700 dark:text-purple-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'AI')->count() }}</span>
                                <span class="text-[10px] text-purple-600/60">AI</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-zinc-100 dark:bg-zinc-700/50">
                                <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                                <span class="text-[11px] font-bold text-zinc-600 dark:text-zinc-400">{{ collect($this->attendanceStatuses)->filter(fn ($s) => $s === 'AA')->count() }}</span>
                                <span class="text-[10px] text-zinc-400">AA</span>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Action --}}
                    <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <button wire:click="markAllPresent"
                            class="w-full px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition">
                            {{ __('Marcar todos P') }}
                        </button>
                    </div>

                    {{-- Class Observation & Novedad --}}
                    <div class="px-3 py-3 border-b border-zinc-200 dark:border-zinc-700 space-y-2">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Observación de Clase') }}</span>
                        <input type="text" wire:model="attendanceClasstopic"
                            class="w-full px-2 py-1.5 text-[11px] border border-zinc-200 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-2 focus:outline-blue-500 placeholder:text-zinc-400"
                            placeholder="{{ __('Tema de clase...') }}">
                        <textarea wire:model="attendanceObservation" rows="2"
                            class="w-full px-2 py-1.5 text-[11px] border border-zinc-200 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-2 focus:outline-blue-500 placeholder:text-zinc-400 resize-none"
                            placeholder="{{ __('Observación / detalle...') }}"></textarea>
                        <div class="pt-1 border-t border-zinc-100 dark:border-zinc-700 space-y-1.5">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Novedad (opcional)') }}</span>
                            <select wire:model="attendanceNovedadType"
                                class="w-full px-2 py-1.5 text-[11px] border border-red-200 dark:border-red-800 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-2 focus:outline-red-500 placeholder:text-zinc-400">
                                <option value="">-- {{ __('Seleccionar tipo') }} --</option>
                                @foreach(\App\Models\TeacherManagement\Attendances\ClassObservation::NOVEDAD_TYPES as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                            <textarea wire:model="attendanceNovedad" rows="2"
                                class="w-full px-2 py-1.5 text-[11px] border border-red-200 dark:border-red-800 rounded-lg bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:outline-2 focus:outline-red-500 placeholder:text-zinc-400 resize-none"
                                placeholder="{{ __('Novedad escrita (opcional)...') }}"></textarea>
                        </div>
                    </div>

                    {{-- Page Navigation --}}
                    <div class="px-3 py-3 flex-1 flex flex-col">
                        <span class="text-[9px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Pagina') }}</span>
                        <div class="flex flex-wrap gap-1 mb-3">
                            @php
                                $totalPages = $this->getAttendanceTotalPages();
                                $currentPage = $this->attendancePage;
                            @endphp
                            @for($p = 0; $p < $totalPages; $p++)
                                <button wire:click="goToAttendancePage({{ $p }})"
                                    class="w-7 h-7 rounded text-[11px] font-bold transition
                                           {{ $p === $currentPage ? 'bg-blue-600 text-white shadow-sm' : 'text-zinc-500 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}">
                                    {{ $p + 1 }}
                                </button>
                            @endfor
                        </div>
                        <div class="flex gap-1.5 mt-auto">
                            <button wire:click="prevAttendancePage" {{ $this->attendancePage === 0 ? 'disabled' : '' }}
                                class="flex-1 px-2 py-1.5 rounded text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                                &lsaquo; {{ __('Ant') }}
                            </button>
                            <button wire:click="nextAttendancePage" {{ $this->attendancePage >= $totalPages - 1 ? 'disabled' : '' }}
                                class="flex-1 px-2 py-1.5 rounded text-xs font-semibold border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 disabled:opacity-30 transition">
                                {{ __('Sig') }} &rsaquo;
                            </button>
                        </div>
                        <div class="text-center mt-2">
                            <span class="text-[10px] text-zinc-400">{{ count($this->attendanceStudents) }} {{ __('alumnos') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 flex flex-col min-h-0">
                    {{-- Content Header --}}
                    <div class="px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                        <span class="text-xs text-zinc-400">
                            {{ $this->attendanceDate }} · {{ count($this->attendanceStudents) }} {{ __('estudiantes') }}
                        </span>
                        <span class="text-[11px] text-zinc-400">
                            {{ __('Mostrando') }} {{ $this->attendancePage * 8 + 1 }}–{{ min(($this->attendancePage + 1) * 8, count($this->attendanceStudents)) }}
                        </span>
                    </div>

                    {{-- Students Page --}}
                    <div class="flex-1 overflow-y-auto px-4 py-3">
                        @if(count($this->attendanceStudents) > 0)
                            @php $paginatedStudents = $this->getAttendancePaginatedStudents(); @endphp
                            @foreach($paginatedStudents as $student)
                                @php $currentStatus = $student['status']; @endphp
                                <div class="flex items-center gap-3 py-2.5 border-b border-zinc-100 dark:border-zinc-700 last:border-b-0">
                                    <div class="w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center text-xs font-bold text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                                        {{ strtoupper(mb_substr($student['name'], 0, 1)) }}{{ strtoupper(mb_substr(trim(explode(' ', $student['name'])[1] ?? ''), 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $student['name'] }}</div>
                                        <div class="text-[10px] font-mono text-zinc-400">{{ $student['code'] }}</div>
                                    </div>
                                    <div class="flex border border-zinc-200 dark:border-zinc-600 rounded-lg overflow-hidden flex-shrink-0">
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'P')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600
                                                   {{ $currentStatus === 'P' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('Presente') }}">P</button>
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'A')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600
                                                   {{ $currentStatus === 'A' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('Atraso') }}">A</button>
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'I')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600
                                                   {{ $currentStatus === 'I' ? 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('F. Injustificada') }}">I</button>
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'J')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600
                                                   {{ $currentStatus === 'J' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('F. Justificada') }}">J</button>
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'AI')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition border-r border-zinc-200 dark:border-zinc-600
                                                   {{ $currentStatus === 'AI' ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('Ab. Institucional') }}">AI</button>
                                        <button wire:click="setAttendanceStatus({{ $student['id'] }}, 'AA')"
                                            class="px-2.5 py-1.5 text-[11px] font-bold transition
                                                   {{ $currentStatus === 'AA' ? 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' : 'bg-white dark:bg-zinc-800 text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-700' }}"
                                            title="{{ __('Ab. Aula') }}">AA</button>
                                    </div>
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

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-5 py-3 border-t border-zinc-200 dark:border-zinc-700">
                <button wire:click="closeAttendanceModal"
                    class="px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm font-medium transition">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="saveAttendance" {{ count($this->attendanceStudents) === 0 ? 'disabled' : '' }}
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <flux:icon.check class="size-4" /> {{ __('Guardar asistencia') }}
                </button>
            </div>
        </div>
    </div>
@endif
