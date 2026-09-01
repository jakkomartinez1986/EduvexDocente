<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\User;
use App\Models\Identity\Users\Teacher;
use App\Models\Incidents\NotificationChannel;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Notifications\AcademicNotificationSent;
use App\Services\AcademicYearService;
use App\Services\Messaging\ChannelStatusService;
use App\Services\Messaging\WaMeLinkService;
use App\Jobs\SendChannelMessageJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Administración de Notificaciones')] class extends Component
{
    public ?int $yearId = null;
    public array $trimesters = [];
    public ?int $selectedTrimesterId = null;
    public ?int $selectedNivelId = null;
    public ?string $selectedGradeName = null;
    public ?int $selectedGradeId = null;
    public ?int $selectedSubjectId = null;
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
        'telegram' => 'Telegram',
        'sms' => 'SMS',
        'sistema' => 'Sistema',
        'impresa' => 'Impresa',
    ];

    #[Locked]
    public array $channelColors = [
        'email' => 'blue',
        'whatsapp' => 'green',
        'telegram' => 'sky',
        'sms' => 'violet',
        'sistema' => 'amber',
        'impresa' => 'zinc',
    ];

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->trimesters = $this->loadTrimesters();
        $this->selectedTrimesterId = $this->currentTrimesterId();
    }

    protected function loadTrimesters(): array
    {
        if (! $this->yearId) {
            return [];
        }

        $today = now()->toDateString();

        return AcademicPeriod::where('year_id', $this->yearId)
            ->where('status', 1)
            ->orderBy('start_date')
            ->get()
            ->sortByDesc(function (AcademicPeriod $period) use ($today): bool {
                return $today >= $period->start_date->toDateString()
                    && $today <= $period->end_date->toDateString();
            })
            ->values()
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

        return AcademicPeriod::where('year_id', $this->yearId)
            ->where('status', 1)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->value('id');
    }

    /**
     * Docente asociado al usuario autenticado (null si es un administrador).
     */
    #[Computed]
    public function currentTeacher(): ?Teacher
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->teacher;
    }

    /**
     * Paralelos con programación de clases del docente autenticado en el año activo.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function scheduledGradeIds(): \Illuminate\Support\Collection
    {
        if (! $this->yearId || $this->currentTeacher === null) {
            return collect();
        }

        return ClassSchedule::query()
            ->where('year_id', $this->yearId)
            ->where('teacher_id', $this->currentTeacher->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('grade_id');
    }

    /**
     * Niveles disponibles. Para un docente autenticado solo los que tiene
     * en su programación de clases; para administración, todos los activos.
     *
     * @return \Illuminate\Support\Collection<int, Nivel>
     */
    #[Computed]
    public function niveis(): \Illuminate\Support\Collection
    {
        return Nivel::query()
            ->where('status', 1)
            ->whereHas('grades', function ($query) {
                $query->where('status', 1);

                if ($this->currentTeacher !== null) {
                    $query->whereIn('id', $this->scheduledGradeIds);
                }
            })
            ->with('shift')
            ->orderBy('nivel_name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    #[Computed]
    public function grados(): \Illuminate\Support\Collection
    {
        if (! $this->selectedNivelId) {
            return collect();
        }

        return Grade::query()
            ->where('nivel_id', $this->selectedNivelId)
            ->where('status', 1)
            ->when($this->scheduledGradeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $this->scheduledGradeIds))
            ->orderBy('grade_name')
            ->distinct()
            ->pluck('grade_name');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Grade>
     */
    #[Computed]
    public function paralelos(): \Illuminate\Support\Collection
    {
        if (! $this->selectedNivelId || ! $this->selectedGradeName) {
            return collect();
        }

        return Grade::query()
            ->where('nivel_id', $this->selectedNivelId)
            ->where('grade_name', $this->selectedGradeName)
            ->where('status', 1)
            ->when($this->scheduledGradeIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $this->scheduledGradeIds))
            ->orderBy('section')
            ->get();
    }

    /**
     * Asignaturas dictadas en el paralelo seleccionado durante el año activo,
     * según la programación de clases real (class_schedules). Para un docente
     * autenticado solo las que él mismo dicta en ese paralelo.
     *
     * @return \Illuminate\Support\Collection<int, Subject>
     */
    #[Computed]
    public function asignaturas(): \Illuminate\Support\Collection
    {
        if (! $this->selectedGradeId || ! $this->yearId) {
            return collect();
        }

        $subjectIds = ClassSchedule::query()
            ->where('year_id', $this->yearId)
            ->where('grade_id', $this->selectedGradeId)
            ->when($this->currentTeacher !== null, fn ($query) => $query->where('teacher_id', $this->currentTeacher->id))
            ->where('is_active', true)
            ->distinct()
            ->pluck('subject_id');

        return Subject::query()
            ->whereIn('id', $subjectIds)
            ->orderBy('subject_name')
            ->get();
    }

    protected function isValidInContext(?int $value, string $optionsComputed): bool
    {
        if ($value === null) {
            return true;
        }

        return $this->{$optionsComputed}->contains('id', $value);
    }

    protected function resetAfterNivel(): void
    {
        $this->selectedGradeName = null;
        $this->resetAfterGrado();
    }

    protected function resetAfterGrado(): void
    {
        $this->selectedGradeId = null;
        $this->resetAfterParalelo();
    }

    protected function resetAfterParalelo(): void
    {
        $this->selectedSubjectId = null;
    }

    public function updatedSelectedTrimesterId($value): void
    {
        $this->resetAfterNivel();
    }

    public function updatedSelectedNivelId($value): void
    {
        $value = $value === '' ? null : (is_numeric($value) ? (int) $value : null);

        if (! $this->isValidInContext($value, 'niveis')) {
            $value = null;
        }

        $this->selectedNivelId = $value;
        $this->resetAfterNivel();
    }

    public function updatedSelectedGradeName($value): void
    {
        $value = $value === '' ? null : (string) $value;

        if ($value !== null && ! $this->grados->contains($value)) {
            $value = null;
        }

        $this->selectedGradeName = $value;
        $this->resetAfterGrado();
    }

    public function updatedSelectedGradeId($value): void
    {
        $value = $value === '' ? null : (is_numeric($value) ? (int) $value : null);

        if (! $this->isValidInContext($value, 'paralelos')) {
            $value = null;
        }

        $this->selectedGradeId = $value;
        $this->resetAfterParalelo();
    }

    public function updatedSelectedSubjectId($value): void
    {
        $value = $value === '' ? null : (is_numeric($value) ? (int) $value : null);

        if (! $this->isValidInContext($value, 'asignaturas')) {
            $value = null;
        }

        $this->selectedSubjectId = $value;
    }

    protected function applyAcademicFilters(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($this->selectedTrimesterId, fn ($q) => $q->where('trimester_id', $this->selectedTrimesterId))
            ->when($this->selectedNivelId, fn ($q) => $q->whereHas('grade', fn ($gq) => $gq->where('nivel_id', $this->selectedNivelId)))
            ->when($this->selectedGradeName, fn ($q) => $q->whereHas('grade', fn ($gq) => $gq->where('grade_name', $this->selectedGradeName)))
            ->when($this->selectedGradeId, fn ($q) => $q->where('grade_id', $this->selectedGradeId))
            ->when($this->selectedSubjectId, fn ($q) => $q->where('subject_id', $this->selectedSubjectId))
            ->when($this->currentTeacher?->id, fn ($q, int $teacherId) => $q->where('teacher_id', $teacherId));
    }

    public function getStudentGroupsProperty()
    {
        $query = $this->applyAcademicFilters(
            AcademicNotification::query()
                ->with(['student.user', 'subject', 'grade', 'channels', 'teacher.user'])
                ->where('year_id', $this->yearId),
        );

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
        $base = $this->applyAcademicFilters(
            AcademicNotification::query()->where('year_id', $this->yearId),
        );

        return [
            'total' => (clone $base)->count(),
            'attended' => (clone $base)->where('parent_attended', true)->count(),
            'not_attended' => (clone $base)->where('parent_attended', false)->count(),
            'printed' => (clone $base)->whereNotNull('printed_at')->count(),
        ];
    }

    #[Computed]
    public function channelStatuses(): array
    {
        return app(ChannelStatusService::class)->forChannels([
            ChannelConfiguration::CHANNEL_WHATSAPP,
            ChannelConfiguration::CHANNEL_TELEGRAM,
        ]);
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

        $sent = match ($channel) {
            'sistema' => $this->dispatchSystemNotification($notification),
            'email' => $this->sendEmailNotification($notification),
            'impresa' => $this->sendPrintedNotification($notification),
            'whatsapp' => $this->sendWhatsAppNotification($notification),
            'telegram' => $this->sendTelegramNotification($notification),
            default => false,
        };

        if (! $sent) {
            return;
        }

        $now = now();

        $channelRecord->update(['status' => 'sent', 'sent_at' => $now]);
        $notification->update(['sent_at' => $notification->sent_at ?? $now]);

        Flux::toast(variant: 'success', text: __('Notificación enviada por ') . $channel . '.');
    }

    protected function dispatchSystemNotification(AcademicNotification $notification): bool
    {
        $representative = Representative::where('student_id', $notification->student_id)->first();

        if ($representative && $representative->user) {
            Notification::send($representative->user, new AcademicNotificationSent($notification));
        }

        return true;
    }

    protected function sendEmailNotification(AcademicNotification $notification): bool
    {
        $representative = Representative::where('student_id', $notification->student_id)->with('user')->first();

        if (! $representative || ! $representative->user || ! $representative->user->email) {
            Flux::toast(variant: 'warning', text: __('El representante no tiene correo registrado.'));

            return false;
        }

        Notification::send($representative->user, new AcademicNotificationSent($notification));

        return true;
    }

    protected function sendPrintedNotification(AcademicNotification $notification): bool
    {
        $notification->update(['printed_at' => now()]);

        NotificationChannel::where('notification_id', $notification->id)
            ->where('channel', 'impresa')
            ->update(['printed_at' => now()]);

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function representativePhones(AcademicNotification $notification): array
    {
        $linkService = app(WaMeLinkService::class);

        return Representative::where('student_id', $notification->student_id)->with('user')
            ->get()
            ->map(fn ($representative) => $linkService->normalizePhone($representative->user?->cellphone ?? $representative->user?->phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function sendWhatsAppNotification(AcademicNotification $notification): bool
    {
        $phones = $this->representativePhones($notification);

        if ($phones === []) {
            Flux::toast(variant: 'warning', text: __('Ningún representante tiene celular registrado.'));

            return false;
        }

        if (app(ChannelStatusService::class)->apiAvailable(ChannelConfiguration::CHANNEL_WHATSAPP)) {
            foreach ($phones as $phone) {
                SendChannelMessageJob::dispatch(ChannelConfiguration::CHANNEL_WHATSAPP, $phone, (string) $notification->message);
            }

            return true;
        }

        $urls = app(WaMeLinkService::class)->buildLinks($phones, (string) $notification->message);

        $this->dispatch('openUrls', urls: $urls);

        return true;
    }

    protected function sendTelegramNotification(AcademicNotification $notification): bool
    {
        if (! app(ChannelStatusService::class)->apiAvailable(ChannelConfiguration::CHANNEL_TELEGRAM)) {
            Flux::toast(variant: 'warning', text: __('El canal Telegram no está habilitado o le faltan credenciales.'));

            return false;
        }

        $chatIds = Representative::where('student_id', $notification->student_id)->with('user')
            ->get()
            ->map(fn ($representative) => trim((string) $representative->user?->telegram_chat_id))
            ->filter(fn ($chatId) => $chatId !== '')
            ->unique()
            ->values();

        if ($chatIds->isEmpty()) {
            Flux::toast(variant: 'warning', text: __('Ningún representante tiene un chat de Telegram vinculado.'));

            return false;
        }

        foreach ($chatIds as $chatId) {
            SendChannelMessageJob::dispatch(ChannelConfiguration::CHANNEL_TELEGRAM, $chatId, (string) $notification->message);
        }

        return true;
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

    {{-- Filtros relacionados --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <flux:field>
            <flux:label>{{ __('Período') }}</flux:label>
            <flux:select wire:model.live="selectedTrimesterId" wire:key="periodo-select">
                <flux:select.option value="">{{ __('Todos los períodos') }}</flux:select.option>
                @foreach($trimesters as $trimester)
                    <flux:select.option value="{{ $trimester['id'] }}">
                        {{ $trimester['trimester_name'] }} ({{ $trimester['start_date'] }} - {{ $trimester['end_date'] }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Nivel') }}</flux:label>
            <flux:select wire:model.live="selectedNivelId" wire:key="nivel-select">
                <flux:select.option value="">{{ __('Seleccione nivel') }}</flux:select.option>
                @foreach($this->niveis as $nivel)
                    <flux:select.option value="{{ $nivel->id }}">
                        {{ $nivel->nivel_name }}{{ ($nivel->shift?->shift_name ?? '') !== '' ? ' - '.$nivel->shift->shift_name : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Grado') }}</flux:label>
            <flux:select
                wire:model.live="selectedGradeName"
                :disabled="! $this->selectedNivelId"
                wire:key="grado-select-{{ $this->selectedNivelId }}"
            >
                @if(! $this->selectedNivelId)
                    <flux:select.option value="">{{ __('Seleccione primero el nivel') }}</flux:select.option>
                @else
                    <flux:select.option value="">{{ __('Todos los grados') }}</flux:select.option>
                    @foreach($this->grados as $grado)
                        <flux:select.option value="{{ $grado }}">{{ $grado }}</flux:select.option>
                    @endforeach
                @endif
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Paralelo') }}</flux:label>
            <flux:select
                wire:model.live="selectedGradeId"
                :disabled="! $this->selectedGradeName"
                wire:key="paralelo-select-{{ $this->selectedNivelId }}-{{ $this->selectedGradeName }}"
            >
                @if(! $this->selectedGradeName)
                    <flux:select.option value="">{{ __('Seleccione primero el grado') }}</flux:select.option>
                @else
                    <flux:select.option value="">{{ __('Todos los paralelos') }}</flux:select.option>
                    @foreach($this->paralelos as $paralelo)
                        <flux:select.option value="{{ $paralelo->id }}">
                            {{ filled($paralelo->section) ? __('Paralelo').' '.$paralelo->section : $paralelo->grade_name }}
                        </flux:select.option>
                    @endforeach
                @endif
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Asignatura') }}</flux:label>
            <flux:select
                wire:model.live="selectedSubjectId"
                :disabled="! $this->selectedGradeId"
                wire:key="asignatura-select-{{ $this->selectedGradeId }}"
            >
                @if(! $this->selectedGradeId)
                    <flux:select.option value="">{{ __('Seleccione primero el paralelo') }}</flux:select.option>
                @else
                    <flux:select.option value="">{{ __('Todas las asignaturas') }}</flux:select.option>
                    @foreach($this->asignaturas as $asignatura)
                        <flux:select.option value="{{ $asignatura->id }}">{{ $asignatura->subject_name }}</flux:select.option>
                    @endforeach
                @endif
            </flux:select>
        </flux:field>

        <flux:field class="md:col-span-2 lg:col-span-3">
            <flux:label>{{ __('Buscar estudiante') }}</flux:label>
            <flux:input wire:model.live.debounce="300" :placeholder="__('Nombre, apellido o cédula...')" icon="magnifying-glass" />
        </flux:field>
    </div>

    {{-- Estado de canales --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs text-zinc-500">{{ __('Estado de canales:') }}</span>
        @foreach(['whatsapp', 'telegram'] as $statusChannel)
            @php($status = $this->channelStatuses[$statusChannel])
            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs {{ $status['api_available'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400' }}">
                <span class="size-1.5 rounded-full {{ $status['api_available'] ? 'bg-emerald-500' : 'bg-zinc-400 dark:bg-zinc-500' }}"></span>
                {{ ucfirst($statusChannel) }} · {{ $status['api_available'] ? __('API activa') : ($status['manual_available'] ? __('Envío manual') : __('No disponible')) }}
            </span>
        @endforeach
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
                                        <flux:button size="xs" variant="ghost" icon="bell" wire:click="sendChannel({{ $notification->id }}, 'sistema')" title="{{ __('Enviar por Sistema') }}" />
                                        <flux:button size="xs" variant="ghost" icon="chat-bubble-left" wire:click="sendChannel({{ $notification->id }}, 'whatsapp')" title="{{ __('Enviar por WhatsApp') }}" />
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="paper-airplane"
                                            wire:click="sendChannel({{ $notification->id }}, 'telegram')"
                                            :disabled="! $this->channelStatuses['telegram']['api_available']"
                                            title="{{ $this->channelStatuses['telegram']['api_available'] ? __('Enviar por Telegram') : __('Telegram no configurado') }}"
                                        />
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
            Livewire.on('openUrls', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                const urls = Array.isArray(data?.urls) ? data.urls : [data?.urls].filter(Boolean);

                urls.forEach((url) => {
                    window.open(url, '_blank');
                });
            });
        });
    </script>
</div>
