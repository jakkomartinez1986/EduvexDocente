<div>
    <flux:modal wire:model.live="show" class="max-w-md">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon.exclamation-triangle class="size-6 text-amber-600 dark:text-amber-400" />
                </span>
                <div>
                    <flux:heading size="lg">{{ __('Confirmar accion') }}</flux:heading>
                    <flux:subheading>{{ $message }}</flux:subheading>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancel" variant="filled">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button wire:click="confirm" variant="primary">
                    {{ __('Confirmar') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
