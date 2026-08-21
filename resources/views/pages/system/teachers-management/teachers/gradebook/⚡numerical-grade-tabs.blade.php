@php
    $studentPaginator = $this->getStudents();
@endphp

<div class="mb-4">
    <div class="flex items-center gap-1 overflow-x-auto pb-1 scrollbar-hide">
        @foreach($assessmentBlocks as $block)
            @php
                $isActive = $activeTab === 'block_' . $block->id;
                $avg = $this->getBlockAverageForDisplay($block->id);
            @endphp
            <button wire:click="selectBlock('{{ $block->id }}')"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition
                           {{ $isActive
                               ? 'bg-blue-600 text-white shadow-md'
                               : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-blue-50 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700' }}">
                <span>{{ $block->name ?? 'Bloque #' . $block->id }}</span>
                @if($block->internal_percentage)
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold
                        {{ $isActive ? 'bg-blue-500 text-blue-100' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-500' }}">
                        {{ $block->internal_percentage }}%
                    </span>
                @endif
                @if($avg !== null)
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold
                        {{ $isActive ? 'bg-white/20 text-white' : ($avg >= 7 ? 'bg-emerald-50 text-emerald-600' : ($avg >= 5 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600')) }}">
                        {{ number_format($avg, 1) }}
                    </span>
                @endif
            </button>
        @endforeach

        <button wire:click="$set('activeTab', 'sumativa')"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition
                       {{ $activeTab === 'sumativa'
                           ? 'bg-emerald-600 text-white shadow-md'
                           : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-emerald-50 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700' }}">
            <span>{{ __('Sumativas') }}</span>
        </button>
        {{-- <button wire:click="$set('activeTab', 'supletorios')"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition
                       {{ $activeTab === 'supletorios'
                           ? 'bg-red-600 text-white shadow-md'
                           : 'bg-white dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-red-50 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700' }}">
            <span>{{ __('Supletorio') }}</span>
        </button> --}}

        @if($this->isGradingOpen())
            <button wire:click="openCreateBlock()"
                    class="flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-semibold bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition border border-dashed border-blue-300 dark:border-blue-700">
                <flux:icon.plus class="size-3" />
                <span>{{ __('Nuevo Bloque') }}</span>
            </button>
        @endif
    </div>
</div>
