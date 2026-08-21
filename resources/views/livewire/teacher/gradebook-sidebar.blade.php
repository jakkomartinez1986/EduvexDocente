<div class="w-64 min-w-[200px] border-r border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 overflow-y-auto max-h-[700px]">
    <div class="p-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Bloques') }}</span>
        @if($selectedTrimesterId != -1)
            <button wire:click="openCreateBlock" class="text-blue-600 hover:text-blue-700 font-bold text-base leading-none" title="{{ __('Agregar bloque') }}">+</button>
        @endif
    </div>

    @forelse($this->sidebarItems as $item)
        <button wire:click="selectBlock('{{ $item->id }}')"
                wire:key="sidebar-item-{{ $item->id }}"
                class="w-full px-4 py-2.5 text-left border-l-3 transition
                       {{ $activeBlockId == $item->id
                           ? match($item->type) {
                               'block'      => 'bg-white dark:bg-zinc-900 border-l-blue-600 shadow-sm',
                               'sumativa'   => 'bg-white dark:bg-zinc-900 border-l-emerald-500 shadow-sm',
                               'supletorio' => 'bg-white dark:bg-zinc-900 border-l-red-500 shadow-sm',
                               default      => '',
                           }
                           : 'border-l-transparent hover:bg-zinc-100 dark:hover:bg-zinc-700/50' }}">

            @if($item->type === 'block')
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                    @if($item->percentage)
                        <span class="text-xs font-bold text-blue-600">{{ $item->percentage }}%</span>
                    @endif
                </div>
                <div class="flex items-center gap-2.5 mt-0.5">
                    <span class="text-xs text-zinc-400">{{ $item->act_count }} {{ __('act.') }}</span>
                    <span class="text-xs font-bold text-blue-600">{{ $item->average !== null ? number_format($item->average, 1) : '—' }}</span>
                </div>
                <div class="flex gap-1 mt-1.5">
                    <button wire:click.stop="openEditBlock({{ $item->id }})"
                            class="px-2 py-0.5 rounded text-[10px] font-semibold text-zinc-500 border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:border-blue-500 hover:text-blue-600 transition">
                        {{ __('Editar') }}
                    </button>
                    <button wire:click.stop="deleteBlock({{ $item->id }})"
                            wire:confirm="{{ __('Eliminar este bloque y todas sus notas?') }}"
                            class="px-2 py-0.5 rounded text-[10px] font-semibold text-zinc-500 border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-800 hover:border-red-500 hover:text-red-600 hover:bg-red-50 transition">
                        {{ __('Eliminar') }}
                    </button>
                </div>

            @elseif($item->type === 'sumativa')
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $item->name }}</span>
                    @if($item->status === 'Cerrado')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                            {{ __('Cerrado') }}
                        </span>
                    @elseif($item->status === 'Abierto')
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                            {{ __('Abierto') }}
                        </span>
                    @endif
                </div>

            @elseif($item->type === 'supletorio')
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-red-700 dark:text-red-400">{{ $item->name }}</span>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">
                        {{ $item->status }}
                    </span>
                </div>
            @endif
        </button>
    @empty
        <div class="px-4 py-6 text-center text-zinc-400 text-sm">
            {{ __('No hay bloques') }}<br>
            @if($selectedTrimesterId != -1)
                <button wire:click="openCreateBlock" class="mt-2 text-blue-600 font-bold text-sm hover:text-blue-700">+ {{ __('Crear primero') }}</button>
            @endif
        </div>
    @endforelse
</div>
