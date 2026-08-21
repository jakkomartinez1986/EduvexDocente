{{-- Stats Panel: Resumen General (4 stat boxes) --}}
<div class="lg:col-span-1">
    <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-200 mb-4">{{ __('Resumen General') }}</h3>
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center mb-3">
                <flux:icon.user-group class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="text-xl font-bold text-zinc-900 dark:text-white font-mono">{{ $stats['total_students'] }}</div>
            <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">{{ __('Estudiantes') }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center mb-3">
                <flux:icon.book-open class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="text-xl font-bold text-zinc-900 dark:text-white font-mono">{{ $stats['total_subjects'] }}</div>
            <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">{{ __('Asignaturas') }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center mb-3">
                <flux:icon.rectangle-stack class="w-4 h-4 text-amber-600 dark:text-amber-400" />
            </div>
            <div class="text-xl font-bold text-zinc-900 dark:text-white font-mono">{{ $stats['total_grades'] }}</div>
            <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">{{ __('Cursos') }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4">
            <div class="w-8 h-8 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center mb-3">
                <flux:icon.clock class="w-4 h-4 text-purple-600 dark:text-purple-400" />
            </div>
            <div class="text-xl font-bold text-zinc-900 dark:text-white font-mono">{{ $stats['total_hours'] }}</div>
            <div class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">{{ __('Horas/Sem') }}</div>
        </div>
    </div>
</div>
