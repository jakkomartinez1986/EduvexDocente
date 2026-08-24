<?php

declare(strict_types=1);

use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Services\Messaging\MessagingManager;
use App\Services\Messaging\SendResult;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Canales de Mensajería')] class extends Component
{
    public array $forms = [];

    public string $testing = '';

    public function mount(): void
    {
        foreach (ChannelConfiguration::channels() as $channel) {
            $configuration = ChannelConfiguration::firstOrCreate(
                ['channel' => $channel],
                ['provider' => $this->defaultProvider($channel), 'enabled' => false, 'test_status' => 'pending'],
            );

            $this->forms[$channel] = [
                'enabled' => (bool) $configuration->enabled,
                'credentials' => $configuration->credentials ?? [],
                'sender_name' => $configuration->sender_name,
                'test_destination' => $configuration->test_destination,
            ];
        }
    }

    public function save(string $channel): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }

        $rules = [
            "forms.$channel.enabled" => 'boolean',
            "forms.$channel.test_destination" => 'nullable|string|max:255',
            "forms.$channel.sender_name" => 'nullable|string|max:255',
        ];

        foreach ($this->credentialFields($channel) as $field) {
            $rules["forms.$channel.credentials.$field"] = 'nullable|string|max:512';
        }

        $this->validate($rules);

        $configuration = ChannelConfiguration::query()->where('channel', $channel)->firstOrFail();

        $credentials = $configuration->credentials ?? [];

        foreach (array_keys($this->credentialFields($channel)) as $field) {
            $value = trim((string) ($this->forms[$channel]['credentials'][$field] ?? ''));

            if ($value !== '') {
                $credentials[$field] = $value;
            } elseif (! array_key_exists($field, $this->forms[$channel]['credentials'] ?? [])) {
                unset($credentials[$field]);
            }
        }

        $wasEnabled = $configuration->enabled;

        $configuration->update([
            'enabled' => (bool) $this->forms[$channel]['enabled'],
            'credentials' => $credentials,
            'sender_name' => $this->forms[$channel]['sender_name'] ?: null,
            'test_destination' => $this->forms[$channel]['test_destination'] ?: null,
        ]);

        if ($wasEnabled && ! $configuration->enabled) {
            $configuration->update(['test_status' => 'pending', 'tested_at' => null, 'last_error' => null]);
        }

        Flux::toast(variant: 'success', text: __('Canal guardado.'));
    }

    public function testChannel(string $channel): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }

        $this->testing = $channel;

        $configuration = ChannelConfiguration::query()->where('channel', $channel)->firstOrFail();

        $credentialsOverride = [];

        foreach (array_keys($this->credentialFields($channel)) as $field) {
            $value = trim((string) ($this->forms[$channel]['credentials'][$field] ?? ''));

            if ($value !== '') {
                $credentialsOverride[$field] = $value;
            }
        }

        $manager = app(MessagingManager::class);

        $result = $manager->verify($channel, $credentialsOverride !== [] ? $credentialsOverride : null);

        $destination = trim((string) ($this->forms[$channel]['test_destination'] ?? ''));

        if ($result->success && $destination !== '') {
            $result = $this->sendTestMessage($manager, $channel, $destination, $credentialsOverride);
        }

        $configuration->update([
            'test_status' => $result->success ? 'ok' : 'failed',
            'tested_at' => now(),
            'last_error' => $result->success ? null : mb_substr((string) $result->error, 0, 1000),
        ]);

        Flux::toast(variant: $result->success ? 'success' : 'danger', text: $result->success ? __('Canal probado con éxito.') : __('Fallo de canal: ').$result->error);

        $this->testing = '';
    }

    private function sendTestMessage(MessagingManager $manager, string $channel, string $destination, array $credentialsOverride): SendResult
    {
        return $manager->send($channel, $destination, __('Mensaje de prueba del sistema EduVex Docente.'), credentialsOverride: $credentialsOverride !== [] ? $credentialsOverride : null);
    }

    /**
     * @return array<string, string>
     */
    public function credentialFields(string $channel): array
    {
        return MessagingManager::providerFields()[$channel]['fields'] ?? [];
    }

    public function channelLabel(string $channel): string
    {
        return match ($channel) {
            ChannelConfiguration::CHANNEL_WHATSAPP => 'WhatsApp',
            ChannelConfiguration::CHANNEL_TELEGRAM => 'Telegram',
            default => 'Email',
        };
    }

    private function defaultProvider(string $channel): string
    {
        return match ($channel) {
            ChannelConfiguration::CHANNEL_WHATSAPP => 'meta_cloud',
            ChannelConfiguration::CHANNEL_TELEGRAM => 'telegram_bot',
            default => 'smtp',
        };
    }

    private function isAdmin(): bool
    {
        return auth()->user()?->hasRole(['SUPER-ADMIN', 'ADMIN']) ?? false;
    }
}; ?>
<div>
    <flux:heading size="text-xl" level="1">{{ __('Canales de Mensajería') }}</flux:heading>
    <flux:subheading>{{ __('Configura y prueba los canales de envío de notificaciones.') }}</flux:subheading>

    @if (!auth()->user()->hasRole(['SUPER-ADMIN', 'ADMIN']))
        <flux:callout variant="warning" class="mt-6">
            <flux:callout.heading>{{ __('Acceso restringido') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Solo SUPER-ADMIN y ADMIN pueden gestionar los canales.') }}</flux:callout.text>
        </flux:callout>

        @return
    @endif

    <div class="mt-6 space-y-6">
        @foreach (ChannelConfiguration::channels() as $channel)
            @php($configuration = \App\Models\Setting\Messaging\ChannelConfiguration::query()->where('channel', $channel)->first())
            <flux:card>
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <flux:icon
                            :icon="match ($channel) { 'whatsapp' => 'chat-bubble-left-right', 'telegram' => 'paper-airplane', default => 'envelope' }"
                            variant="outline" class="size-5"
                        />
                        <flux:heading size="text-lg">{{ $this->channelLabel($channel) }}</flux:heading>
                        @if ($configuration?->test_status === 'ok')
                            <flux:badge color="green" icon="check-circle">{{ __('Probado') }}</flux:badge>
                        @elseif ($configuration?->test_status === 'failed')
                            <flux:badge color="red" icon="x-circle">{{ __('Falló') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" icon="clock">{{ __('Sin probar') }}</flux:badge>
                        @endif
                    </div>

                    <flux:switch
                        wire:model.live="forms.{{ $channel }}.enabled"
                        :label="__('Habilitado')"
                    />
                </div>

                @if ($configuration?->last_error)
                    <p class="mt-2 text-xs text-red-500 dark:text-red-400">{{ $configuration->last_error }}</p>
                @endif

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($this->credentialFields($channel) as $field => $label)
                        <flux:field>
                            <flux:input
                                type="password"
                                wire:model="forms.{{ $channel }}.credentials.{{ $field }}"
                                :label="__($label)"
                                :placeholder="$field === 'token' || $field === 'bot_token' ? '••••••••' : ''"
                            />
                        </flux:field>
                    @endforeach

                    <flux:field>
                        <flux:input
                            wire:model="forms.{{ $channel }}.test_destination"
                            :label="__('Destino de prueba').($channel === 'email' ? ' (email)' : ' (teléfono/chat)')"
                            placeholder="{{ $channel === 'email' ? 'destino@correo.com' : '593991234567 / chat_id' }}"
                        />
                    </flux:field>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <flux:button variant="primary" wire:click="save('{{ $channel }}')">
                        {{ __('Guardar') }}
                    </flux:button>
                    <flux:button
                        variant="outline"
                        wire:click="testChannel('{{ $channel }}')"
                        wire:loading.attr="disabled"
                        wire:target="testChannel('{{ $channel }}')"
                        :disabled="!$forms[$channel]['enabled'] && !collect($this->credentialFields($channel))->count()"
                    >
                        <span wire:loading wire:target="testChannel('{{ $channel }}')">{{ __('Probando…') }}</span>
                        <span wire:loading.remove wire:target="testChannel('{{ $channel }}')">{{ __('Probar conexión') }}</span>
                    </flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
