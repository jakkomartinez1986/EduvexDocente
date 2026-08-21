<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\User;
use App\Models\Incidents\NotificationChannel;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Notifications\AcademicNotificationSent;
use App\Services\AcademicYearService;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Administración de Notificaciones')] class extends Component
{
    public ?int $yearId = null;
    public array $trimesters = [];
    public ?int $selectedTrimesterId = null;
    public string $search = '';

    public bool $showAttendanceModal = false;
    public ?int $editingNotificationId = null;

    public array $attendanceForm = [
        'parent_attended' => '',
        'attended_date' => null,
        'attended_time' => null,
    ];

    #[Locked]
    public array $channelLabels = [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'sistema' => 'Sistema',
        'impresa' => 'Impresa',
    ];

    #[Locked]
    public array $channelColors = [
        'email' => 'blue',
        'whatsapp' => 'green',
        'sms' => 'violet',
        'sistema' => 'amber',
        'impresa' => 'zinc',
    ];

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->trimesters = $this->loadTrimesters();
        $this->selectedTrimesterId = $this->currentTrimesterId() ?? ($this->trimesters[0]['id'] ?? null);
    }

    protected function loadTrimesters(): array
    {
        if (! $this->yearId) {
            return [];
        }

        return AcademicPeriod::where('year_id', $this->yearId)
            ->where('status', 1)
            ->orderBy('start_date')
            ->get()
            ->map(fn ($period) => [
                'id' => $period->id,
                'trimester_name' => $period->trimester_name,
                'start_date' => $period->start_date?->format('d/m/Y'),
                'end_date' => $period->end_date?->format('d/m/Y'),
            ])
            ->toArray();
    }

    protected function currentTrimesterId(): ?int
    {
        if (! $this->yearId) {
            return null;
        }

        $now = now()->toDateString();
        $periods = AcademicPeriod::where('year_id', $this->yearId)
            ->where('status', 1)
            ->get();

        foreach ($periods as $period) {
            if ($now >= $period->start_date->toDateString() && $now <= $period->end_date->toDateString()) {
                return $period->id;
            }
        }

        return $periods->first()?->id;
    }

    public function getStudentGroupsProperty()
    {
        $query = AcademicNotification::query()
            ->with(['student.user', 'subject', 'grade', 'channels', 'teacher.user'])
            ->where('year_id', $this->yearId);

        if ($this->selectedTrimesterId) {
            $query->where('trimester_id', $this->selectedTrimesterId);
        }

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->whereHas('student.user', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('lastname', 'like', '%'.$search.'%')
                        ->orWhere('dni', 'like', '%'.$search.'%');
                });
            });
        }

        $notifications = $query->latest('generated_date')->get();

        return $notifications
            ->groupBy(fn ($n) => $n->student_id)
            ->map(fn ($items) => (object) [
                'student' => $items->first()?->student,
                'grade' => $items->first()?->grade,
                'notifications' => $items->sortByDesc(fn ($n) => $n->generated_date),
            ])
            ->values();
    }

    public function getStatsProperty()
    {
        $query = AcademicNotification::query()->where('year_id', $this->yearId);

        if ($this->selectedTrimesterId) {
            $query->where('trimester_id', $this->selectedTrimesterId);
        }

        $notifications = $query->get();

        return [
            'total' => $notifications->count(),
            'attended' => $notifications->where('parent_attended', true)->count(),
            'not_attended' => $notifications->where('parent_attended', false)->count(),
            'printed' => $notifications->whereNotNull('printed_at')->count(),
        ];
    }

    public function resendNotification(int $id): void
    {
        $notification = AcademicNotification::with(['channels', 'student.representatives.user', 'teacher.user'])->findOrFail($id);

        $now = now();
        $notification->update(['sent_at' => $now]);

        foreach ($notification->channels as $channel) {
            $channel->update(['status' => 'sent', 'sent_at' => $now]);
        }

        $this->dispatchSystemNotification($notification);

        Flux::toast(variant: 'success', text: __('Notificación reenviada correctamente. Código: ').$notification->code);
    }

    public function sendChannel(int $id, string $channel): void
    {
        $notification = AcademicNotification::with(['channels', 'student.representatives.user', 'teacher.user'])->findOrFail($id);

        $channelRecord = $notification->channels->firstWhere('channel', $channel);

        if (!$channelRecord) {
            Flux::toast(variant: 'danger', text: __('Canal no encontrado.'));
            return;
        }

        $now = now();

        match ($channel) {
            'sistema' => $this->dispatchSystemNotification($notification),
            'email' => $this->sendEmailNotification($notification),
            'impresa' => $this->sendPrintedNotification($notification),
            'whatsapp' => $this->sendWhatsAppNotification($notification),
            default => null,
        };

        $channelRecord->update(['status' => 'sent', 'sent_at' => $now]);
        $notification->update(['sent_at' => $notification->sent_at ?? $now]);

        Flux::toast(variant: 'success', text: __('Notificación enviada por ') . $channel . '.');
    }

    protected function dispatchSystemNotification(AcademicNotification $notification): void
    {
        $representative = Representative::where('student_id', $notification->student_id)->first();

        if ($representative && $representative->user) {
            Notification::send($representative->user, new AcademicNotificationSent($notification));
        }
    }

    protected function sendEmailNotification(AcademicNotification $notification): void
    {
        $representative = Representative::where('student_id', $notification->student_id)->with('user')->first();

        if ($representative && $representative->user && $representative->user->email) {
            Notification::send($representative->user, new AcademicNotificationSent($notification));
        }
    }

    protected function sendPrintedNotification(AcademicNotification $notification): void
    {
        $notification->update(['printed_at' => now()]);

        NotificationChannel::where('notification_id', $notification->id)
            ->where('channel', 'impresa')
            ->update(['printed_at' => now()]);
    }

    protected function sendWhatsAppNotification(AcademicNotification $notification): void
    {
        $representative = Representative::where('student_id', $notification->student_id)->with('user')->first();
        $phone = $representative?->phone ?? $representative?->user?->phone ?? '';

        if ($phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $message = urlencode($notification->message);
            $url = "https://wa.me/{$cleanPhone}?text={$message}";
            $this->dispatch('openUrl', $url);
        }
    }

    public function openAttendanceModal(int $id): void
    {
        $notification = AcademicNotification::findOrFail($id);

        $this->editingNotificationId = $id;
        $this->attendanceForm = [
            'parent_attended' => $notification->parent_attended === true ? 'si' : ($notification->parent_attended === false ? 'no' : ''),
            'attended_date' => $notification->summoned_at?->format('Y-m-d'),
            'attended_time' => $notification->summoned_at?->format('H:i'),
        ];

        $this->showAttendanceModal = true;
    }

    public function saveAttendance(): void
    {
        $this->validate([
            'attendanceForm.parent_attended' => 'required|in:si,no',
            'attendanceForm.attended_date' => 'required_if:attendanceForm.parent_attended,si|date|nullable',
            'attendanceForm.attended_time' => 'required_if:attendanceForm.parent_attended,si|date_format:H:i|nullable',
        ]);

        $notification = AcademicNotification::findOrFail($this->editingNotificationId);

        $attended = $this->attendanceForm['parent_attended'] === 'si';
        $attendedAt = null;

        if ($attended && $this->attendanceForm['attended_date'] && $this->attendanceForm['attended_time']) {
            $attendedAt = $this->attendanceForm['attended_date'].' '.$this->attendanceForm['attended_time'].':00';
        }

        $notification->update([
            'parent_attended' => $attended,
            'summoned_at' => $attendedAt,
        ]);

        $this->showAttendanceModal = false;

        Flux::toast(variant: 'success', text: __('Asistencia del representante actualizada correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Administración de Notificaciones') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestione las notificaciones enviadas a los representantes por trimestre') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Notificaciones') }}</span>
    </nav>

    {{-- Filtros --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <flux:field>
            <flux:label>{{ __('Trimestre') }}</flux:label>
            <flux:select wire:model.live="selectedTrimesterId">
                <flux:select.option value="">{{ __('Todos los trimestres') }}</flux:select.option>
                @foreach($trimesters as $trimester)
                    <flux:select.option value="{{ $trimester['id'] }}">
                        {{ $trimester['trimester_name'] }} ({{ $trimester['start_date'] }} - {{ $trimester['end_date'] }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field class="lg:col-span-2">
            <flux:label>{{ __('Buscar estudiante') }}</flux:label>
            <flux:input wire:model.live.debounce="300" :placeholder="__('Nombre, apellido o cédula...')" icon="magnifying-glass" />
        </flux:field>
    </div>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            <div class="text-xs text-zinc-500 mb-1">{{ __('Total notificaciones') }}</div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->stats['total'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            <div class="text-xs text-zinc-500 mb-1">{{ __('Representante asistió') }}</div>
            <div class="text-2xl font-bold text-emerald-600">{{ $this->stats['attended'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            <div class="text-xs text-zinc-500 mb-1">{{ __('No asistió') }}</div>
            <div class="text-2xl font-bold text-red-600">{{ $this->stats['not_attended'] }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            <div class="text-xs text-zinc-500 mb-1">{{ __('Impresas') }}</div>
            <div class="text-2xl font-bold text-blue-600">{{ $this->stats['printed'] }}</div>
        </div>
    </div>

    {{-- Estudiantes por trimestre --}}
    @forelse($this->studentGroups as $group)
        <div class="mb-6">
            <div class="flex items-center gap-3 px-4 py-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-t-xl">
                <flux:avatar :name="$group->student?->user?->fullname ?? ''" size="sm" />
                <div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $group->student?->user?->fullname ?? '-' }}</div>
                    <div class="text-xs text-zinc-500 font-mono">{{ $group->student?->student_code }} &middot; {{ ($group->grade?->grade_name ?? '') . ' ' . ($group->grade?->section ?? '') }}</div>
                </div>
                <span class="ml-auto text-xs text-zinc-500">{{ $group->notifications->count() }} {{ __('notificaciones') }}</span>
            </div>
            <div class="overflow-x-auto border-x border-b border-zinc-200 dark:border-zinc-700 rounded-b-xl">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Código') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Tipo') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Materia') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Canales') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Generada') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Enviada') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Impresión') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Asistencia') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($group->notifications as $notification)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-3 font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $notification->code }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge :color="match($notification->type) { 'asistencia' => 'amber', 'comportamental' => 'red', default => 'blue' }">
                                        {{ __(ucfirst($notification->type)) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $notification->subject?->subject_name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1 justify-center">
                                        @forelse($notification->channels as $channel)
                                            <flux:badge :color="$this->channelColors[$channel->channel] ?? 'zinc'" size="sm">
                                                {{ $this->channelLabels[$channel->channel] ?? ucfirst($channel->channel) }}
                                            </flux:badge>
                                        @empty
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $notification->generated_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $notification->sent_at?->format('d/m/Y H:i') ?? __('No enviada') }}</td>
                                <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $notification->printed_at?->format('d/m/Y H:i') ?? __('No impresa') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <flux:badge :color="match(true) { $notification->parent_attended === true => 'green', $notification->parent_attended === false => 'red', default => 'zinc' }">
                                        {{ $notification->parent_attended === true ? __('Sí') : ($notification->parent_attended === false ? __('No') : __('Pendiente')) }}
                                    </flux:badge>
                                    @if($notification->summoned_at)
                                        <div class="text-[11px] text-zinc-500 mt-0.5">{{ $notification->summoned_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button size="xs" variant="ghost" icon="printer" href="{{ route('admin.teacher.incidents.pdf.notification', $notification->id) }}" title="{{ __('Imprimir PDF') }}" />
                                        <flux:button size="xs" variant="ghost" icon="envelope" wire:click="sendChannel({{ $notification->id }}, 'email')" title="{{ __('Enviar por Email') }}" />
                                        <flux:button size="xs" variant="ghost" icon="paper-airplane" wire:click="sendChannel({{ $notification->id }}, 'sistema')" title="{{ __('Enviar por Sistema') }}" />
                                        <flux:button size="xs" variant="ghost" icon="chat-bubble-left" wire:click="sendChannel({{ $notification->id }}, 'whatsapp')" title="{{ __('Enviar por WhatsApp') }}" />
                                        <flux:button size="xs" variant="ghost" icon="user-check" wire:click="openAttendanceModal({{ $notification->id }})" title="{{ __('Asistencia del representante') }}" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.bell class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:text variant="subtle">{{ __('No hay notificaciones para los filtros seleccionados.') }}</flux:text>
        </div>
    @endforelse

    {{-- ==================== MODAL: ASISTENCIA DEL REPRESENTANTE ==================== --}}
    <flux:modal wire:model="showAttendanceModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Asistencia del representante') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('Registre si el representante asistió a la citación y en qué fecha y hora') }}</flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('¿El representante asistió?') }}</flux:label>
                    <flux:select wire:model="attendanceForm.parent_attended" :placeholder="__('Seleccione...')">
                        <flux:select.option value="si">{{ __('Sí') }}</flux:select.option>
                        <flux:select.option value="no">{{ __('No') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="attendanceForm.parent_attended" />
                </flux:field>

                @if(($attendanceForm['parent_attended'] ?? '') === 'si')
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>{{ __('Fecha de asistencia') }}</flux:label>
                            <flux:input type="date" wire:model="attendanceForm.attended_date" />
                            <flux:error name="attendanceForm.attended_date" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Hora de asistencia') }}</flux:label>
                            <flux:input type="time" wire:model="attendanceForm.attended_time" />
                            <flux:error name="attendanceForm.attended_time" />
                        </flux:field>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showAttendanceModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveAttendance">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('openUrl', (url) => {
                window.open(url, '_blank');
            });
        });
    </script>
</div>
