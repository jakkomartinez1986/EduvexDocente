<div>
    <flux:modal wire:model.live="showBlockModal" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">{{ $this->editingBlockId ? __('Editar Bloque') : __('Nuevo Bloque') }}</flux:heading>

            <div class="mt-6 space-y-4">
                <flux:input wire:model="blockForm.name" label="{{ __('Nombre') }}" />
                @error('blockForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                <flux:textarea wire:model="blockForm.description" label="{{ __('Descripcion') }}" rows="3" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="blockForm.internal_percentage"
                               type="number" min="0" max="100" step="0.01"
                               label="{{ __('Porcentaje interno') }} (%)" />
                    <flux:input wire:model="blockForm.order"
                               type="number" min="1"
                               label="{{ __('Orden') }}" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="subtle" wire:click="$set('showBlockModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveBlock()">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.live="showActivityModal" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">{{ $this->editingActivityId ? __('Editar Actividad') : __('Nueva Actividad') }}</flux:heading>

            <div class="mt-6 space-y-4">
                <flux:input wire:model="activityForm.name" label="{{ __('Nombre') }}" />
                @error('activityForm.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                <flux:input wire:model="activityForm.topic" label="{{ __('Tema') }}" />
                @error('activityForm.topic') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                <flux:textarea wire:model="activityForm.description" label="{{ __('Descripcion') }}" rows="3" />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:input wire:model="activityForm.date" type="date" label="{{ __('Fecha') }}" min="{{ \Carbon\Carbon::today()->format('Y-m-d') }}" />
                        @error('activityForm.date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <flux:input wire:model="activityForm.max_score" type="number" min="0" step="0.1" label="{{ __('Nota Maxima') }}" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="subtle" wire:click="$set('showActivityModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveActivity()">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
