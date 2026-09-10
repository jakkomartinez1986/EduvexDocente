{{-- Modal: Fecha de Evaluacion --}}
@if($this->showEvaluationModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.5)" wire:click="closeEvaluationModal">
        <div class="bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl w-full max-w-lg" wire:click.stop>
            <div class="flex items-center justify-between p-5 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                        <flux:icon.calendar class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Fecha de Evaluacion') }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Selecciona un dia del calendario academico') }}</p>
                    </div>
                </div>
                <button wire:click="closeEvaluationModal"
                    class="w-8 h-8 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center justify-center text-zinc-400 hover:text-zinc-600 transition">
                    <flux:icon.x-mark class="w-4 h-4" />
                </button>
            </div>

            <div class="p-5 space-y-4">
                @error('evaluationDate')
                    <div class="flex items-center gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-400">
                        <flux:icon.exclamation-circle class="size-4" /> {{ $message }}
                    </div>
                @enderror

                @if(count($this->availableCalendarDays) > 0)
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">
                            {{ __('Dias disponibles desde hoy') }}
                        </label>
                        <div class="max-h-72 overflow-y-auto space-y-2 pr-1">
                            @foreach($this->availableCalendarDays as $cd)
                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition
                                    {{ $this->evaluationDate === $cd['date']
                                        ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/20 dark:border-purple-600'
                                        : 'border-zinc-200 dark:border-zinc-600 hover:border-purple-300 hover:bg-purple-50/50 dark:hover:bg-purple-900/10' }}">
                                    <input type="radio"
                                        wire:model="evaluationDate"
                                        value="{{ $cd['date'] }}"
                                        class="text-purple-600 focus:ring-purple-500">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $cd['label'] }}
                                        </div>
                                        @if($cd['activity'] !== 'Dia lectivo')
                                            <div class="text-xs text-orange-600 dark:text-orange-400 mt-0.5 truncate">
                                                {{ $cd['activity'] }}
                                            </div>
                                        @else
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                                                Dia lectivo disponible
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400 shrink-0">
                                        {{ $cd['period'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl text-sm text-amber-700 dark:text-amber-400 mb-2">
                        {{ __('No hay dias registrados en el calendario academico para este trimestre. Ingresa la fecha manualmente.') }}
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            {{ __('Fecha de evaluación') }}
                        </label>
                        <input type="date"
                            wire:model="evaluationDate"
                            min="{{ $this->evaluationDateMin }}"
                            class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-purple-400">
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 p-5 border-t border-zinc-200 dark:border-zinc-700">
                <button wire:click="closeEvaluationModal"
                    class="px-4 py-2 rounded-lg border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-sm font-medium transition">
                    {{ __('Cancelar') }}
                </button>
                <button wire:click="saveEvaluationDate"
                    class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium transition flex items-center gap-2">
                    <flux:icon.check class="size-4" /> {{ __('Guardar fecha') }}
                </button>
            </div>
        </div>
    </div>
@endif
