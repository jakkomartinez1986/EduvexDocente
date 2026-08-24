<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\Incidents\IncidentCommitmentLetter;
use App\Models\Incidents\IncidentIntervention;
use App\Models\Incidents\IncidentReport;
use App\Models\Incidents\NotificationChannel;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\Messaging\ChannelConfiguration;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Services\AcademicYearService;
use App\Services\Messaging\MessagingManager;
use App\Jobs\SendChannelMessageJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libro de Incidencias de Tutoría')] class extends Component
{
    public string $category = 'academicas';

    public string $tab = 'detectar';

    public string $attendanceSubTab = 'hoy';

    public string $search = '';

    public ?int $filterTrimesterId = null;

    public ?string $selectedStudentName = null;

    public ?string $selectedRepresentativeName = null;

    public ?int $selectedStudentId = null;

    public bool $showNotificationModal = false;

    public bool $showInterventionModal = false;

    public bool $showCommitmentModal = false;

    public bool $showReportModal = false;

    public ?int $editingNotificationId = null;

    public ?int $editingInterventionId = null;

    public array $notifForm = [
        'type' => 'academico',
        'message' => '',
        'motives' => [],
        'observation' => '',
        'appointment_date' => null,
        'appointment_time' => null,
        'channels' => ['email'],
    ];

    public array $interventionForm = [
        'type' => 'academico',
        'action_type' => '',
        'student_id' => null,
        'date' => null,
        'description' => '',
        'status' => 'pending',
    ];

    public array $commitmentForm = [
        'type' => 'academico',
        'student_id' => null,
        'representative_id' => null,
        'date' => null,
        'commitments' => '',
        'status' => 'draft',
    ];

    public array $reportForm = [
        'type' => 'academico',
        'student_id' => null,
        'date' => null,
        'conclusion' => '',
        'status' => 'draft',
    ];

    #[Locked]
    public array $actionTypes = [
        'academico' => [
            'Refuerzo académico',
            'Recepción de tareas atrasadas',
            'Recuperación',
            'Tutoría',
            'Refuerzo personalizado',
        ],
        'comportamental' => [
            'Llamada de atención verbal',
            'Llamada de atención escrita',
            'Suspensión de clase',
            'Citación al representante',
            'Derivación a DECE',
            'Derivación a Inspectoría',
        ],
        'asistencia' => [
            'Llamada telefónica',
            'Citación al representante',
            'Visita domiciliaria',
            'Compromiso de asistencia',
            'Derivación a DECE',
        ],
    ];

    #[Locked]
    public array $motiveOptions = [
        'academico' => [
            'Tareas incumplidas',
            'Desempeño en clases',
            'Bajo rendimiento académico',
            'Incumplimiento de actividades',
            'Falta de materiales',
        ],
        'comportamental' => [
            'Irrespeto',
            'Indisciplina',
            'Uniforme',
            'Relaciones con compañeros',
            'Uso indebido de celular',
            'Incumplimiento de normas',
        ],
        'asistencia' => [
            'Inasistencia',
            'Atraso',
            'Riesgo de abandono institucional',
        ],
    ];

    #[Locked]
    public array $channelOptions = ['email', 'whatsapp', 'sms', 'sistema', 'impresa'];

    public ?int $yearId = null;

    public array $trimesters = [];

    public array $modalStudents = [];

    protected ?array $tutorGradeCache = null;

    protected ?Collection $tutorStudentIdsCache = null;

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $currentYear = app(AcademicYearService::class)->getActiveYear();
        if ($currentYear) {
            $this->trimesters = $currentYear->academicPeriods()
                ->where('status', 1)
                ->orderBy('id')
                ->get()
                ->toArray();
        }

        if ($this->tutorGrade) {
            $this->modalStudents = Student::whereIn('id', $this->tutorStudentIds)
                ->with('user')
                ->get()
                ->sortBy(fn ($s) => $s->user?->fullname)
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->user?->fullname ?? ('#'.$s->id)])
                ->values()
                ->all();
        }
    }

    public function getTutorGradeProperty(): ?array
    {
        if ($this->tutorGradeCache !== null) {
            return $this->tutorGradeCache ?: null;
        }

        $tutorSchedule = ClassSchedule::where('teacher_id', auth()->user()->teacher?->id)
            ->where('year_id', $this->yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (! $tutorSchedule) {
            $this->tutorGradeCache = [];

            return null;
        }

        return $this->tutorGradeCache = [
            'id' => $tutorSchedule->grade_id,
            'name' => trim(($tutorSchedule->grade->grade_name ?? '').' '.($tutorSchedule->grade->section ?? '')),
        ];
    }

    public function getTutorStudentIdsProperty(): Collection
    {
        if ($this->tutorStudentIdsCache !== null) {
            return $this->tutorStudentIdsCache;
        }

        if (! $this->tutorGrade) {
            return $this->tutorStudentIdsCache = collect();
        }

        return $this->tutorStudentIdsCache = StudentEnrollment::where('grade_id', $this->tutorGrade['id'])
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->pluck('student_id');
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
        $this->notifForm['type'] = $category === 'academicas' ? 'academico' : ($category === 'comportamentales' ? 'comportamental' : 'asistencia');
        $this->search = '';

        if ($category === 'asistencia') {
            $this->tab = 'asistencia_list';
            if (! $this->filterTrimesterId) {
                $this->filterTrimesterId = $this->getCurrentTrimesterId();
            }
        } else {
            $this->tab = 'detectar';
        }
    }

    public function setAttendanceSubTab(string $subTab): void
    {
        $this->attendanceSubTab = $subTab;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function resetFilters(): void
    {
        $this->filterTrimesterId = null;
        $this->search = '';
    }

    protected function filterBySearch(Collection $rows): Collection
    {
        if (! $this->search || trim($this->search) === '') {
            return $rows;
        }

        $term = strtolower(trim($this->search));

        return $rows->filter(fn ($r) => str_contains(strtolower($r->student?->user?->fullname ?? ''), $term) ||
            str_contains(strtolower($r->student?->student_code ?? ''), $term)
        )->values();
    }

    // ── Notificaciones ──

    public function openNotificationModal(int $studentId): void
    {
        $this->selectedStudentId = $studentId;
        $this->editingNotificationId = null;

        $student = Student::with(['user', 'representatives.user'])->find($studentId);
        $this->selectedStudentName = $student?->user?->fullname;
        $this->selectedRepresentativeName = $student?->representatives->first()?->user?->fullname;

        $nextBusinessDay = $this->getNextBusinessDay();

        $message = match ($this->category) {
            'comportamentales' => $this->buildBehavioralAutoMessage($studentId),
            'asistencia' => $this->buildAttendanceAutoMessage($studentId),
            default => $this->buildAcademicAutoMessage($studentId),
        };

        $motives = $this->category === 'asistencia' ? ['Inasistencia'] : [];

        $this->notifForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'message' => $message,
            'motives' => $motives,
            'observation' => '',
            'appointment_date' => $nextBusinessDay,
            'appointment_time' => null,
            'channels' => ['email', 'sistema', 'impresa'],
        ];
        $this->showNotificationModal = true;
    }

    public function getNextBusinessDay(): string
    {
        $date = now()->addDay();
        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date->format('Y-m-d');
    }

    protected function buildAcademicAutoMessage(int $studentId): string
    {
        $lines = [];
        $student = Student::with('user')->find($studentId);
        $studentName = $student?->user?->fullname ?? '';

        if ($studentName) {
            $lines[] = 'Estimado(a) representante del estudiante '.$studentName.',';
            $lines[] = '';
        }

        $homeworks = HomeworkPending::with('activity')
            ->where('student_id', $studentId)
            ->where('year_id', $this->yearId)
            ->where('status', 'not_submitted')
            ->where('due_date', '<=', now()->toDateString())
            ->orderBy('due_date')
            ->get();

        if ($homeworks->isNotEmpty()) {
            $subjectNames = Subject::whereIn('id', $homeworks->pluck('subject_id')->unique())
                ->pluck('subject_name', 'id');

            $lines[] = 'TAREAS PENDIENTES (vencidas o por vencer):';
            foreach ($homeworks->groupBy('subject_id') as $subjectId => $group) {
                $lines[] = '  - '.$subjectNames->get($subjectId, 'General').':';
                foreach ($group as $hw) {
                    $dueDate = $hw->due_date ? Carbon::parse($hw->due_date)->format('d/m/Y') : 'sin fecha';
                    $activityName = $hw->activity?->name ?? $hw->description;
                    $topic = $hw->topic ?? $hw->activity?->topic;
                    $line = '      • '.$activityName;
                    if ($topic) {
                        $line .= ' (Tema: '.$topic.')';
                    }
                    $line .= ' - Fecha limite: '.$dueDate;
                    $lines[] = $line;
                }
            }
            $lines[] = '';
            $lines[] = 'Solicitamos su apoyo para que el estudiante ponga al día sus tareas.';
            $lines[] = '';
        } else {
            $lines[] = 'Le informamos sobre la situación académica del estudiante en el curso '.($this->tutorGrade['name'] ?? '').'.';
            $lines[] = '';
        }

        $lines[] = 'Curso: '.($this->tutorGrade['name'] ?? '');
        $lines[] = 'Fecha: '.now()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }

    protected function buildBehavioralAutoMessage(int $studentId): string
    {
        $lines = [];
        $student = Student::with('user')->find($studentId);
        $studentName = $student?->user?->fullname ?? '';

        if ($studentName) {
            $lines[] = 'Estimado(a) representante del estudiante '.$studentName.',';
            $lines[] = '';
        }

        $lines[] = 'Se reporta la siguiente situación de comportamiento del estudiante en el curso '.($this->tutorGrade['name'] ?? '').':';
        $lines[] = '';
        $lines[] = 'El estudiante ha presentado conductas que requieren la atención del representante. A continuación se detallan los motivos seleccionados:';
        $lines[] = '';
        $lines[] = 'Curso: '.($this->tutorGrade['name'] ?? '');
        $lines[] = 'Fecha: '.now()->format('d/m/Y H:i');
        $lines[] = '';
        $lines[] = 'Solicitamos su apoyo para reforzar el comportamiento del estudiante en el ámbito escolar.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function buildAttendanceAutoMessage(int $studentId): string
    {
        $student = Student::with(['user', 'representatives.user'])->find($studentId);
        $studentName = $student?->user?->fullname ?? '';
        $currentTrimesterId = $this->filterTrimesterId ?? $this->getCurrentTrimesterId();
        $currentPeriod = $currentTrimesterId ? AcademicPeriod::find($currentTrimesterId) : null;

        $lines = [];

        if ($studentName) {
            $lines[] = 'Estimado(a) representante del estudiante '.$studentName.',';
            $lines[] = '';
        }

        if (! $currentPeriod) {
            return implode("\n", $lines);
        }

        $absences = Attendance::where('student_id', $studentId)
            ->where('year_id', $this->yearId)
            ->where('status', 'I')
            ->whereBetween('date', [$currentPeriod->start_date, $currentPeriod->end_date])
            ->with('classSchedule.subject')
            ->orderBy('date')
            ->get();

        if ($absences->isEmpty()) {
            $lines[] = 'Le informamos que no se registran inasistencias injustificadas en el periodo '.$currentPeriod->trimester_name.'.';
            $lines[] = '';

            return implode("\n", $lines);
        }

        $lines[] = 'Le informamos sobre las inasistencias del estudiante en el periodo '.$currentPeriod->trimester_name.':';
        $lines[] = '';
        $lines[] = 'Total de inasistencias: '.$absences->count();
        $lines[] = '';

        $lines[] = 'Detalle por día y asignatura:';
        foreach ($absences->groupBy(fn ($a) => $a->date->toDateString()) as $date => $dayAbsences) {
            $weekday = ucfirst(Carbon::parse($date)->isoFormat('dddd DD/MM/YYYY'));
            $lines[] = '  - '.$weekday.':';
            $bySubject = $dayAbsences->groupBy(fn ($a) => $a->classSchedule?->subject?->subject_name ?? 'General')
                ->map->count()
                ->sortKeys();
            foreach ($bySubject as $subjectName => $count) {
                $lines[] = '      • '.$subjectName.' ('.$count.')';
            }
        }
        $lines[] = '';

        $consecutiveDates = $this->getConsecutiveDates($absences->pluck('date')->map(fn ($d) => $d->toDateString())->sort()->values());
        if (count($consecutiveDates) >= 2) {
            $lines[] = 'Inasistencias consecutivas detectadas: '.count($consecutiveDates).' dias';
            foreach ($consecutiveDates as $cd) {
                $lines[] = '  - '.Carbon::parse($cd)->isoFormat('dddd DD/MM/YYYY');
            }
            $lines[] = '';
        }

        $lines[] = 'Solicitamos amablemente justifique las inasistencias del estudiante.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    protected function getConsecutiveDates(Collection $sortedDates): array
    {
        $consecutiveDates = [];
        $prevDate = null;
        $currentConsecutive = [];

        foreach ($sortedDates as $d) {
            $carbon = Carbon::parse($d);
            if ($prevDate && $carbon->diffInDays(Carbon::parse($prevDate)) === 1) {
                $currentConsecutive[] = $d;
            } else {
                if (count($currentConsecutive) > count($consecutiveDates)) {
                    $consecutiveDates = $currentConsecutive;
                }
                $currentConsecutive = [$d];
            }
            $prevDate = $d;
        }
        if (count($currentConsecutive) > count($consecutiveDates)) {
            $consecutiveDates = $currentConsecutive;
        }

        return $consecutiveDates;
    }

    public function saveNotification(): void
    {
        $this->validate([
            'notifForm.message' => 'required|string|max:1000',
            'notifForm.motives' => 'required|array|min:1',
            'notifForm.channels' => 'required|array|min:1',
            'notifForm.appointment_date' => [
                'required', 'date', 'after:today',
                function ($attribute, $value, $fail) {
                    $date = Carbon::parse($value);
                    if ($date->isWeekend()) {
                        $fail('La fecha de citacion debe ser un dia laborable (lunes a viernes).');
                    }
                },
            ],
        ]);

        $teacher = auth()->user()->teacher;
        $yearId = $this->yearId;

        $lastNotif = AcademicNotification::where('year_id', $yearId)
            ->where('teacher_id', $teacher?->id)
            ->orderByDesc('notification_number')
            ->first();

        $seqNumber = ($lastNotif?->notification_number ?? 0) + 1;
        $code = 'NOT-'.now()->format('Ymd').'-'.str_pad((string) $seqNumber, 4, '0', STR_PAD_LEFT);

        $notification = AcademicNotification::create([
            'code' => $code,
            'notification_number' => $seqNumber,
            'type' => $this->notifForm['type'],
            'channel' => $this->notifForm['channels'][0] ?? 'email',
            'student_id' => $this->selectedStudentId,
            'grade_id' => $this->tutorGrade['id'] ?? null,
            'subject_id' => null,
            'teacher_id' => $teacher?->id,
            'year_id' => $yearId,
            'trimester_id' => $this->trimesters[0]['id'] ?? null,
            'message' => $this->notifForm['message'],
            'motives' => $this->notifForm['motives'],
            'observation' => $this->notifForm['observation'],
            'appointment_date' => $this->notifForm['appointment_date'],
            'appointment_time' => $this->notifForm['appointment_time'],
            'generated_date' => now()->format('Y-m-d'),
        ]);

        foreach ($this->notifForm['channels'] as $channel) {
            NotificationChannel::create([
                'notification_id' => $notification->id,
                'channel' => $channel,
                'status' => 'pending',
            ]);
        }

        Flux::toast(variant: 'success', text: __('Notificación generada correctamente. Código: ').$code);
        $this->showNotificationModal = false;
    }

    public function saveNotificationThenWhatsApp(): void
    {
        $this->saveNotification();

        $lastNotif = AcademicNotification::where('teacher_id', auth()->user()->teacher?->id)
            ->latest()
            ->first();

        if ($lastNotif) {
            $this->generateWhatsAppPdf($lastNotif->id);
        }
    }

    public function generateWhatsAppPdf(int $notificationId): void
    {
        $notification = AcademicNotification::with(['student.user', 'student.representatives.user', 'teacher.user', 'grade', 'subject'])->findOrFail($notificationId);

        $school = School::where('status', 1)->first();

        $pdf = Pdf::loadView('pdf.incidents.notification', [
            'notification' => $notification,
            'school' => $school,
            'channels' => $notification->channels ?? collect(),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);

        $fileName = 'notificacion-'.$notification->code.'.pdf';
        $path = storage_path('app/public/whatsapp-pdfs/'.$fileName);

        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $pdf->output());

        $pdfUrl = url('storage/whatsapp-pdfs/'.$fileName);

        $payload = $this->resolveWhatsAppPayload($notification);

        if ($payload === null) {
            Flux::toast(variant: 'warning', text: __('El representante no tiene celular registrado. Se descargó el PDF para enviarlo manualmente.'));

            return;
        }

        if (app(MessagingManager::class)->isEnabled(ChannelConfiguration::CHANNEL_WHATSAPP)) {
            $channelRow = NotificationChannel::where('notification_id', $notification->id)
                ->where('channel', 'whatsapp')
                ->first();

            SendChannelMessageJob::dispatch(
                ChannelConfiguration::CHANNEL_WHATSAPP,
                $payload['phone'],
                $payload['message'],
                $path,
                $fileName,
                $channelRow?->id,
            );

            $notification->update(['sent_at' => $notification->sent_at ?? now()]);

            Flux::toast(variant: 'success', text: __('PDF generado. Notificación en cola de envío por WhatsApp.'));

            return;
        }

        NotificationChannel::where('notification_id', $notification->id)
            ->where('channel', 'whatsapp')
            ->update(['status' => 'sent', 'sent_at' => now()]);
        $notification->update(['sent_at' => $notification->sent_at ?? now()]);

        $this->dispatch('whatsapp-send', wa: $payload['url'], pdf: $pdfUrl, name: $fileName);
    }

    /**
     * @return array{phone: string, message: string, url: string}|null
     */
    protected function resolveWhatsAppPayload(AcademicNotification $notification): ?array
    {
        $representative = $notification->student?->representatives->first();
        $phone = $this->normalizeWhatsAppPhone($representative?->user?->cellphone ?? $representative?->user?->phone);

        if (! $phone) {
            return null;
        }

        $message = $this->buildWhatsAppMessage($notification, $representative);

        return [
            'phone' => $phone,
            'message' => $message,
            'url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message),
        ];
    }

    protected function buildWhatsAppUrl(AcademicNotification $notification): string
    {
        return $this->resolveWhatsAppPayload($notification)['url'] ?? '';
    }

    protected function normalizeWhatsAppPhone(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '593')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '593'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '593'.$digits;
        }

        return $digits;
    }

    protected function buildWhatsAppMessage(AcademicNotification $notification, ?Representative $representative): string
    {
        $representativeName = $representative?->user?->full_name;

        $lines[] = ($representativeName ? 'Estimado(a) '.$representativeName : 'Estimado(a) representante').':';
        $lines[] = '';
        $lines[] = 'Le compartimos la notificación '.$notification->code.' correspondiente al estudiante '.($notification->student?->user?->fullname ?? '-').'.';
        $lines[] = '';
        $lines[] = 'Adjunto encontrará el documento PDF con el detalle.';

        return implode("\n", $lines);
    }

    // ── Intervenciones ──

    public function openInterventionModal(?int $studentId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->editingInterventionId = null;
        $this->interventionForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'action_type' => '',
            'student_id' => $studentId,
            'date' => now()->format('Y-m-d'),
            'description' => '',
            'status' => 'pending',
        ];
        $this->showInterventionModal = true;
    }

    public function editIntervention(int $id): void
    {
        $intervention = IncidentIntervention::findOrFail($id);
        $this->editingInterventionId = $id;
        $this->interventionForm = [
            'type' => $intervention->type,
            'action_type' => $intervention->action_type,
            'student_id' => $intervention->student_id,
            'date' => $intervention->date?->format('Y-m-d'),
            'description' => $intervention->description,
            'status' => $intervention->status,
        ];
        $this->showInterventionModal = true;
    }

    public function saveIntervention(): void
    {
        $this->validate([
            'interventionForm.student_id' => 'required|integer|exists:students,id',
            'interventionForm.action_type' => 'required|string|max:255',
            'interventionForm.date' => 'required|date',
            'interventionForm.description' => 'nullable|string',
        ]);

        $data = [
            'type' => $this->interventionForm['type'],
            'action_type' => $this->interventionForm['action_type'],
            'student_id' => $this->interventionForm['student_id'],
            'teacher_id' => auth()->user()->teacher?->id,
            'grade_id' => $this->tutorGrade['id'] ?? null,
            'subject_id' => null,
            'year_id' => $this->yearId,
            'date' => $this->interventionForm['date'],
            'description' => $this->interventionForm['description'],
            'status' => $this->interventionForm['status'],
        ];

        if ($this->editingInterventionId) {
            IncidentIntervention::findOrFail($this->editingInterventionId)->update($data);
            Flux::toast(variant: 'success', text: __('Intervención actualizada correctamente.'));
        } else {
            IncidentIntervention::create($data);
            Flux::toast(variant: 'success', text: __('Intervención registrada correctamente.'));
        }

        $this->showInterventionModal = false;
    }

    public function deleteIntervention(int $id): void
    {
        IncidentIntervention::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Intervención eliminada correctamente.'));
    }

    // ── Actas de compromiso ──

    public function openCommitmentModal(?int $studentId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->commitmentForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'student_id' => $studentId,
            'representative_id' => null,
            'date' => now()->format('Y-m-d'),
            'commitments' => '',
            'status' => 'draft',
        ];

        if ($studentId) {
            $rep = Representative::where('student_id', $studentId)->first();
            $this->commitmentForm['representative_id'] = $rep?->id;
        }

        $this->showCommitmentModal = true;
    }

    public function saveCommitment(): void
    {
        $this->validate([
            'commitmentForm.student_id' => 'required|integer|exists:students,id',
            'commitmentForm.commitments' => 'required|string|min:10',
            'commitmentForm.date' => [
                'required', 'date', 'after:today',
                function ($attribute, $value, $fail) {
                    $date = Carbon::parse($value);
                    if ($date->isWeekend()) {
                        $fail('La fecha debe ser un dia laborable (lunes a viernes).');
                    }
                },
            ],
        ]);

        $studentId = (int) $this->commitmentForm['student_id'];
        $rep = Representative::where('student_id', $studentId)->first();

        $teacher = auth()->user()->teacher;
        $last = IncidentCommitmentLetter::where('year_id', $this->yearId)
            ->orderByDesc('sequential_number')
            ->first();
        $seq = ($last?->sequential_number ?? 0) + 1;
        $code = 'ACT-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        IncidentCommitmentLetter::create([
            'code' => $code,
            'sequential_number' => $seq,
            'type' => $this->commitmentForm['type'],
            'student_id' => $studentId,
            'grade_id' => $this->tutorGrade['id'] ?? null,
            'subject_id' => null,
            'teacher_id' => $teacher?->id,
            'representative_id' => $rep?->id,
            'year_id' => $this->yearId,
            'date' => $this->commitmentForm['date'],
            'commitments' => $this->commitmentForm['commitments'],
            'status' => $this->commitmentForm['status'],
        ]);

        Flux::toast(variant: 'success', text: __('Acta de compromiso generada correctamente. Código: ').$code);
        $this->showCommitmentModal = false;
    }

    // ── Informes ──

    public function openReportModal(?int $studentId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->reportForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'student_id' => $studentId,
            'date' => now()->format('Y-m-d'),
            'conclusion' => '',
            'status' => 'draft',
        ];
        $this->showReportModal = true;
    }

    public function saveReport(): void
    {
        $this->validate([
            'reportForm.student_id' => 'required|integer|exists:students,id',
            'reportForm.conclusion' => 'required|string|min:10',
        ]);

        $teacher = auth()->user()->teacher;
        $last = IncidentReport::where('year_id', $this->yearId)
            ->orderByDesc('sequential_number')
            ->first();
        $seq = ($last?->sequential_number ?? 0) + 1;
        $code = 'INF-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        IncidentReport::create([
            'code' => $code,
            'sequential_number' => $seq,
            'type' => $this->reportForm['type'],
            'student_id' => (int) $this->reportForm['student_id'],
            'grade_id' => $this->tutorGrade['id'] ?? null,
            'subject_id' => null,
            'teacher_id' => $teacher?->id,
            'tutor_id' => $teacher?->id,
            'year_id' => $this->yearId,
            'date' => $this->reportForm['date'],
            'conclusion' => $this->reportForm['conclusion'],
            'status' => $this->reportForm['status'],
        ]);

        Flux::toast(variant: 'success', text: __('Informe generado correctamente. Código: ').$code);
        $this->showReportModal = false;
    }

    // ── Computeds ──

    public function getDetectStudentsProperty(): Collection
    {
        if (! $this->tutorGrade || $this->tutorStudentIds->isEmpty()) {
            return collect();
        }

        $students = Student::whereIn('id', $this->tutorStudentIds)
            ->with(['user', 'representatives.user'])
            ->get()
            ->sortBy(fn ($s) => $s->user?->fullname)
            ->keyBy('id');

        $notifType = $this->category === 'comportamentales' ? 'comportamental' : 'academico';

        $notifCounts = AcademicNotification::whereIn('student_id', $this->tutorStudentIds)
            ->where('type', $notifType)
            ->where('year_id', $this->yearId)
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $lastNotifIds = AcademicNotification::whereIn('student_id', $this->tutorStudentIds)
            ->where('type', $notifType)
            ->where('year_id', $this->yearId)
            ->selectRaw('max(id) as last_id')
            ->groupBy('student_id')
            ->pluck('last_id');

        $lastNotifs = AcademicNotification::whereIn('id', $lastNotifIds)
            ->get()
            ->keyBy('student_id');

        $homeworkTotals = collect();
        if ($this->category === 'academicas') {
            $homeworkTotals = HomeworkPending::whereIn('student_id', $this->tutorStudentIds)
                ->where('year_id', $this->yearId)
                ->where('status', 'not_submitted')
                ->where('due_date', '<=', now()->toDateString())
                ->selectRaw('student_id, count(*) as total')
                ->groupBy('student_id')
                ->pluck('total', 'student_id');
        }

        $results = collect();
        foreach ($students as $sid => $student) {
            $results->push((object) [
                'student' => $student,
                'incidentCount' => (int) ($notifCounts->get($sid) ?? 0),
                'lastNotif' => $lastNotifs->get($sid)?->generated_date?->format('d/m/Y') ?? '-',
                'homeworkPending' => (int) ($homeworkTotals->get($sid) ?? 0),
            ]);
        }

        return $this->filterBySearch($results);
    }

    public function getAttendanceRowsProperty(): Collection
    {
        if (! $this->tutorGrade || $this->tutorStudentIds->isEmpty()) {
            return collect();
        }

        $periodId = $this->filterTrimesterId ?? $this->getCurrentTrimesterId();
        $period = $periodId ? AcademicPeriod::find($periodId) : null;
        if (! $period) {
            return collect();
        }

        $students = Student::whereIn('id', $this->tutorStudentIds)
            ->with(['user', 'representatives.user'])
            ->get()
            ->sortBy(fn ($s) => $s->user?->fullname)
            ->keyBy('id');

        $absences = Attendance::whereIn('student_id', $this->tutorStudentIds)
            ->where('year_id', $this->yearId)
            ->where('status', 'I')
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->with('classSchedule.subject')
            ->orderBy('date')
            ->get()
            ->groupBy('student_id');

        $today = now()->toDateString();
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = now()->endOfWeek(Carbon::FRIDAY)->toDateString();

        $results = collect();

        foreach ($students as $sid => $student) {
            $items = $absences->get($sid) ?? collect();

            $todayCounts = $items->filter(fn ($a) => $a->date->toDateString() === $today)
                ->groupBy(fn ($a) => $a->classSchedule?->subject?->subject_name ?? 'General')
                ->map->count()
                ->sortKeys();
            $weekCounts = $items->filter(fn ($a) => $a->date->toDateString() >= $weekStart && $a->date->toDateString() <= $weekEnd)
                ->groupBy(fn ($a) => $a->classSchedule?->subject?->subject_name ?? 'General')
                ->map->count()
                ->sortKeys();

            $results->push((object) [
                'student' => $student,
                'todayCounts' => $todayCounts->toArray(),
                'weekCounts' => $weekCounts->toArray(),
            ]);
        }

        return $this->filterBySearch($results);
    }

    protected function registroScope($query)
    {
        return $query->whereIn('student_id', $this->tutorStudentIds);
    }

    protected function currentRegistroType(): string
    {
        return match ($this->category) {
            'comportamentales' => 'comportamental',
            'asistencia' => 'asistencia',
            default => 'academico',
        };
    }

    public function getInterventionsProperty(): Collection
    {
        if (! $this->tutorGrade) {
            return collect();
        }

        return $this->registroScope(IncidentIntervention::query())
            ->where('type', $this->currentRegistroType())
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getCommitmentLettersProperty(): Collection
    {
        if (! $this->tutorGrade) {
            return collect();
        }

        return $this->registroScope(IncidentCommitmentLetter::query())
            ->where('type', $this->currentRegistroType())
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getReportsProperty(): Collection
    {
        if (! $this->tutorGrade) {
            return collect();
        }

        return $this->registroScope(IncidentReport::query())
            ->where('type', $this->currentRegistroType())
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getInterventionStatsProperty(): array
    {
        if (! $this->tutorGrade) {
            return ['month' => 0, 'refuerzo' => 0, 'tutoria' => 0, 'recuperacion' => 0];
        }

        $all = $this->registroScope(IncidentIntervention::query())
            ->where('type', $this->currentRegistroType())
            ->where('year_id', $this->yearId);

        $month = (clone $all)->whereMonth('date', now()->month)->count();
        $refuerzo = (clone $all)->where('action_type', 'like', '%Refuerzo%')->count();
        $tutoria = (clone $all)->where('action_type', 'like', '%Tutoría%')->count();
        $recuperacion = (clone $all)->where('action_type', 'like', '%Recuperación%')->count();

        return compact('month', 'refuerzo', 'tutoria', 'recuperacion');
    }

    public function getCurrentSchoolProperty(): ?School
    {
        return School::where('status', 1)->first();
    }

    protected function getCurrentTrimesterId(): ?int
    {
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
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Libro de Incidencias de Tutoría') }}</flux:heading>
            @if($this->tutorGrade)
                <flux:text variant="subtle" class="mt-1">
                    {{ __('Estudiantes de tutoría: :grado', ['grado' => $this->tutorGrade['name']]) }}
                </flux:text>
            @else
                <flux:text variant="subtle" class="mt-1">{{ __('Seguimiento integral de incidencias de su curso de tutoría') }}</flux:text>
            @endif
        </div>
        @if($this->tutorGrade)
            <flux:badge color="fuchsia" icon="academic-cap" size="lg">{{ __('Modo Tutoría') }}</flux:badge>
        @endif
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Incidencias de Tutoría') }}</span>
    </nav>

    @if(! $this->tutorGrade)
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.exclamation-triangle class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No tiene asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró un horario de Acompañamiento integral a su nombre en el año lectivo activo.') }}</p>
        </div>
    @else
        {{-- Category Selector --}}
        <div class="flex items-center gap-2 mb-4">
            <flux:button wire:click="setCategory('academicas')" :variant="$this->category === 'academicas' ? 'primary' : 'outline'" class="gap-2">
                <flux:icon.academic-cap class="size-4" />
                {{ __('Académicas') }}
            </flux:button>
            <flux:button wire:click="setCategory('comportamentales')" :variant="$this->category === 'comportamentales' ? 'primary' : 'outline'" class="gap-2">
                <flux:icon.user-group class="size-4" />
                {{ __('Comportamentales') }}
            </flux:button>
            <flux:button wire:click="setCategory('asistencia')" :variant="$this->category === 'asistencia' ? 'primary' : 'outline'" class="gap-2">
                <flux:icon.clipboard-document-list class="size-4" />
                {{ __('Asistencia') }}
            </flux:button>
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-0 border-b border-zinc-200 dark:border-zinc-700 mb-6 overflow-x-auto">
            @if($this->category !== 'asistencia')
                <flux:button wire:click="setTab('detectar')" variant="ghost" class="rounded-none border-b-2 {{ $this->tab === 'detectar' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-zinc-500' }}">
                    <flux:icon.magnifying-glass class="size-4" /> {{ __('Detectar') }}
                </flux:button>
            @endif
            <flux:button wire:click="setTab('intervenir')" variant="ghost" class="rounded-none border-b-2 {{ $this->tab === 'intervenir' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-zinc-500' }}">
                <flux:icon.chat-bubble-left-right class="size-4" /> {{ __('Intervenir') }}
            </flux:button>
            <flux:button wire:click="setTab('evidenciar')" variant="ghost" class="rounded-none border-b-2 {{ $this->tab === 'evidenciar' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-zinc-500' }}">
                <flux:icon.document-text class="size-4" /> {{ __('Evidenciar') }}
            </flux:button>
            <flux:button wire:click="setTab('informar')" variant="ghost" class="rounded-none border-b-2 {{ $this->tab === 'informar' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-zinc-500' }}">
                <flux:icon.document-chart-bar class="size-4" /> {{ __('Informar') }}
            </flux:button>
        </div>

        {{-- ==================== ASISTENCIA: LISTA DE INASISTENCIAS POR ASIGNATURA ==================== --}}
        @if($this->category === 'asistencia' && $this->tab === 'asistencia_list')
            <div>
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    <div class="w-full sm:w-72">
                        <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar estudiante...')" icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:label>{{ __('Trimestre') }}</flux:label>
                        <flux:select wire:model.live="filterTrimesterId" placeholder="{{ __('Todos') }}">
                            @foreach($trimesters as $tri)
                                <flux:select.option value="{{ $tri['id'] }}">{{ $tri['trimester_name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                {{-- Sub-tabs: Hoy (por dia) / Semana --}}
                <div class="flex items-center gap-0 border-b border-zinc-200 dark:border-zinc-700 mb-4">
                    <flux:button wire:click="setAttendanceSubTab('hoy')" variant="ghost" class="rounded-none border-b-2 {{ $this->attendanceSubTab === 'hoy' ? 'border-amber-600 text-amber-700 dark:text-amber-300' : 'border-transparent text-zinc-500' }}">
                        <flux:icon.calendar class="size-4" /> {{ __('Hoy') }}
                    </flux:button>
                    <flux:button wire:click="setAttendanceSubTab('semana')" variant="ghost" class="rounded-none border-b-2 {{ $this->attendanceSubTab === 'semana' ? 'border-amber-600 text-amber-700 dark:text-amber-300' : 'border-transparent text-zinc-500' }}">
                        <flux:icon.calendar-days class="size-4" /> {{ __('Semana') }}
                    </flux:button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 w-1/3">{{ __('Estudiante') }}</th>
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Inasistencias') }} <span class="font-normal">({{ $this->attendanceSubTab === 'hoy' ? __('hoy') : __('semana') }})</span></th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($this->attendanceRows as $item)
                                @php $counts = $this->attendanceSubTab === 'hoy' ? $item->todayCounts : $item->weekCounts; @endphp
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition align-top">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar :name="$item->student?->user?->fullname ?? ''" size="sm" />
                                            <div>
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->student?->user?->fullname ?? '-' }}</div>
                                                <div class="text-xs text-zinc-500 font-mono">{{ $item->student?->student_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @forelse($counts as $subjectName => $count)
                                            <div class="flex items-center justify-between gap-6 py-0.5 max-w-xs border-b border-dashed border-zinc-100 dark:border-zinc-800 last:border-0">
                                                <span class="text-zinc-700 dark:text-zinc-300 truncate">{{ $subjectName }}</span>
                                                <flux:badge :color="$count >= 3 ? 'red' : ($count >= 2 ? 'yellow' : 'zinc')" size="sm">{{ $count }}</flux:badge>
                                            </div>
                                        @empty
                                            <span class="text-zinc-400">{{ __('Sin datos') }}</span>
                                        @endforelse
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:dropdown>
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }})" icon="bell">{{ __('Notificar') }}</flux:menu.item>
                                                <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }})" icon="chat-bubble-left-right">{{ __('Intervenir') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-16 text-center">
                                        <flux:icon.clipboard-document-check class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text variant="subtle" class="text-sm">{{ __('No hay estudiantes matriculados en su curso de tutoría.') }}</flux:text>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        {{-- ==================== TAB: DETECTAR ==================== --}}
        @elseif($this->tab === 'detectar')
            <div>
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    <div class="w-full sm:w-72">
                        <flux:input wire:model.live.debounce="400ms" :placeholder="__('Buscar estudiante...')" icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:label>{{ __('Trimestre') }}</flux:label>
                        <flux:select wire:model="filterTrimesterId" placeholder="{{ __('Todos') }}">
                            @foreach($trimesters as $tri)
                                <flux:select.option value="{{ $tri['id'] }}">{{ $tri['trimester_name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if($filterTrimesterId || $search)
                        <flux:button wire:click="resetFilters" size="sm" variant="ghost" icon="x-mark">{{ __('Limpiar') }}</flux:button>
                    @endif
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Curso') }}</th>
                                    @if($this->category === 'academicas')
                                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Deberes Pend.') }}</th>
                                    @endif
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Incidencias') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Última Notif.') }}</th>
                                    <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse($this->detectStudents as $item)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <flux:avatar :name="$item->student?->user?->fullname ?? ''" size="sm" />
                                                <div>
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->student?->user?->fullname ?? '-' }}</div>
                                                    <div class="text-xs text-zinc-500 font-mono">{{ $item->student?->student_code }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $this->tutorGrade['name'] }}</td>
                                        @if($this->category === 'academicas')
                                            <td class="px-4 py-3 text-center">
                                                <flux:badge :color="$item->homeworkPending > 0 ? 'red' : 'zinc'">{{ $item->homeworkPending }}</flux:badge>
                                            </td>
                                        @endif
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->incidentCount > 0 ? 'red' : 'zinc'">{{ $item->incidentCount }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $item->lastNotif }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                                <flux:menu>
                                                    <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }})" icon="bell">{{ __('Notificar') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }})" icon="chat-bubble-left-right">{{ __('Intervenir') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $this->category === 'academicas' ? 6 : 5 }}" class="px-4 py-16 text-center">
                                            <flux:icon.user-group class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                            <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
            </div>

        {{-- ==================== TAB: INTERVENIR ==================== --}}
        @elseif($this->tab === 'intervenir')
            <div>
                @php $stats = $this->interventionStats; @endphp
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                        <div class="text-xs text-zinc-500 mb-1">{{ __('Intervenciones este mes') }}</div>
                        <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $stats['month'] }}</div>
                    </div>
                    <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                        <div class="text-xs text-zinc-500 mb-1">{{ __('Refuerzos académicos') }}</div>
                        <div class="text-2xl font-bold text-emerald-600">{{ $stats['refuerzo'] }}</div>
                    </div>
                    <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                        <div class="text-xs text-zinc-500 mb-1">{{ __('Tutorías') }}</div>
                        <div class="text-2xl font-bold text-amber-600">{{ $stats['tutoria'] }}</div>
                    </div>
                    <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                        <div class="text-xs text-zinc-500 mb-1">{{ __('Recuperaciones') }}</div>
                        <div class="text-2xl font-bold text-blue-600">{{ $stats['recuperacion'] }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <div></div>
                    <flux:button wire:click="openInterventionModal" variant="primary" icon="plus">
                        {{ __('Nueva intervención') }}
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($this->interventions as $intervention)
                        <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <h4 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">{{ $intervention->action_type }}</h4>
                            <div class="text-xs text-zinc-500 mt-1">
                                {{ $intervention->student?->user?->fullname ?? '-' }}
                                &middot; {{ $intervention->teacher?->user?->fullname ?? '-' }}
                                &middot; {{ $intervention->date?->format('d/m/Y') }}
                            </div>
                            <div class="flex items-center gap-2 mt-3">
                                <flux:badge :color="match($intervention->status) { 'completed' => 'green', 'programmed' => 'blue', default => 'yellow' }">
                                    {{ $intervention->status }}
                                </flux:badge>
                            </div>
                            @if($intervention->description)
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-2 line-clamp-2">{{ $intervention->description }}</p>
                            @endif
                            <div class="flex gap-2 mt-3">
                                <flux:button size="xs" variant="ghost" wire:click="editIntervention({{ $intervention->id }})" icon="pencil">{{ __('Editar') }}</flux:button>
                                <flux:button size="xs" variant="danger" wire:click="deleteIntervention({{ $intervention->id }})" wire:confirm="{{ __('¿Eliminar esta intervención?') }}" icon="trash">{{ __('Eliminar') }}</flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-zinc-400 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <flux:icon.chat-bubble-left-right class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text variant="subtle">{{ __('No hay intervenciones registradas para su curso de tutoría.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

        {{-- ==================== TAB: EVIDENCIAR ==================== --}}
        @elseif($this->tab === 'evidenciar')
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div></div>
                    <flux:button wire:click="openCommitmentModal" variant="primary" icon="plus">
                        {{ __('Generar acta') }}
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @forelse($this->commitmentLetters as $letter)
                        <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">
                                    {{ $letter->code }} &mdash; {{ __('Acta de compromiso') }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    {{ $letter->student?->user?->fullname ?? '-' }}
                                    &middot; {{ $letter->date?->format('d/m/Y') }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="match($letter->status) { 'signed' => 'green', 'closed' => 'blue', default => 'yellow' }">
                                    {{ $letter->status }}
                                </flux:badge>
                                <flux:button size="xs" variant="ghost" icon="eye" href="{{ route('admin.teacher.incidents.pdf.commitment-letter', $letter->id) }}">
                                    {{ __('PDF') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-zinc-400 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <flux:icon.document-text class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text variant="subtle">{{ __('No hay actas de compromiso generadas para su curso de tutoría.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

        {{-- ==================== TAB: INFORMAR ==================== --}}
        @elseif($this->tab === 'informar')
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div></div>
                    <flux:button wire:click="openReportModal" variant="primary" icon="plus">
                        {{ __('Generar informe') }}
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @forelse($this->reports as $report)
                        <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm">
                                    {{ $report->code }} &mdash; {{ __('Informe al docente tutor') }}
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    {{ $report->student?->user?->fullname ?? '-' }}
                                    &middot; {{ $report->date?->format('d/m/Y') }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="match($report->status) { 'sent' => 'green', 'archived' => 'blue', default => 'yellow' }">
                                    {{ $report->status }}
                                </flux:badge>
                                <flux:button size="xs" variant="ghost" icon="eye" href="{{ route('admin.teacher.incidents.pdf.report', $report->id) }}">
                                    {{ __('PDF') }}
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-zinc-400 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <flux:icon.document-chart-bar class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                            <flux:text variant="subtle">{{ __('No hay informes generados para su curso de tutoría.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    @endif

    {{-- ==================== MODAL: NOTIFICACIÓN ==================== --}}
    <flux:modal wire:model="showNotificationModal" class="max-w-4xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Generar notificación') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('La notificación será dirigida al representante del estudiante') }}</flux:text>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Motivos') }}</flux:label>
                        <div class="space-y-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 max-h-48 overflow-y-auto">
                            @foreach($this->motiveOptions[$this->notifForm['type']] as $motive)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <flux:checkbox value="{{ $motive }}" wire:model="notifForm.motives" />
                                    <span>{{ $motive }}</span>
                                </label>
                            @endforeach
                        </div>
                        <flux:error name="notifForm.motives" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Mensaje') }} <span class="text-red-500">*</span></flux:label>
                        <flux:textarea wire:model="notifForm.message" rows="10" :placeholder="__('El mensaje se genera automáticamente con la información del estudiante...')" />
                        <flux:text variant="subtle" class="text-xs mt-1">{{ __('El mensaje se genera automáticamente. Puede editarlo antes de enviar.') }}</flux:text>
                        <flux:error name="notifForm.message" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Observación') }}</flux:label>
                        <flux:textarea wire:model="notifForm.observation" rows="2" :placeholder="__('Observación adicional...')" />
                    </flux:field>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>{{ __('Fecha de citacion') }}</flux:label>
                            <flux:input type="date" wire:model="notifForm.appointment_date" min="{{ $this->getNextBusinessDay() }}" />
                            @error('notifForm.appointment_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Hora') }}</flux:label>
                            <flux:input type="time" wire:model="notifForm.appointment_time" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Canales de envío') }}</flux:label>
                        <div class="space-y-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                            @foreach($this->channelOptions as $channel)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <flux:checkbox value="{{ $channel }}" wire:model="notifForm.channels" />
                                    <span>{{ __(ucfirst($channel)) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <flux:error name="notifForm.channels" />
                    </flux:field>

                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <div class="text-xs text-zinc-500 mb-2">{{ __('Destinatario') }}</div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $this->selectedRepresentativeName ?? $this->selectedStudentName ?? '-' }}
                        </p>
                        <p class="text-xs text-zinc-500">
                            {{ $this->selectedRepresentativeName ? __('Representante de: ') . $this->selectedStudentName : '' }}
                        </p>
                        <p class="text-xs text-zinc-500">{{ $this->tutorGrade['name'] ?? '' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showNotificationModal', false)">{{ __('Cancelar') }}</flux:button>
                @if(in_array('whatsapp', $this->notifForm['channels']))
                    <flux:button variant="subtle" icon="document-text" wire:click="saveNotificationThenWhatsApp">
                        {{ __('Guardar + WhatsApp') }}
                    </flux:button>
                @endif
                <flux:button variant="primary" wire:click="saveNotification">{{ __('Generar notificación') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ==================== MODAL: INTERVENCIÓN ==================== --}}
    <flux:modal wire:model="showInterventionModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $this->editingInterventionId ? __('Editar intervención') : __('Nueva intervención') }}</flux:heading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Estudiante') }}</flux:label>
                    <flux:select wire:model="interventionForm.student_id" :placeholder="__('Seleccione...')">
                        @foreach($modalStudents as $s)
                            <flux:select.option value="{{ $s['id'] }}">{{ $s['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="interventionForm.student_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tipo de acción') }}</flux:label>
                    <flux:select wire:model="interventionForm.action_type" :placeholder="__('Seleccione...')">
                        @foreach($this->actionTypes[$this->interventionForm['type']] as $action)
                            <flux:select.option value="{{ $action }}">{{ $action }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="interventionForm.action_type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha') }}</flux:label>
                    <flux:input type="date" wire:model="interventionForm.date" />
                    <flux:error name="interventionForm.date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Descripción') }}</flux:label>
                    <flux:textarea wire:model="interventionForm.description" rows="3" :placeholder="__('Detalle de la intervención...')" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Estado') }}</flux:label>
                    <flux:select wire:model="interventionForm.status">
                        <flux:select.option value="pending">{{ __('Pendiente') }}</flux:select.option>
                        <flux:select.option value="completed">{{ __('Completado') }}</flux:select.option>
                        <flux:select.option value="programmed">{{ __('Programado') }}</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showInterventionModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveIntervention">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ==================== MODAL: ACTA DE COMPROMISO ==================== --}}
    <flux:modal wire:model="showCommitmentModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Generar Acta de Compromiso') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('El documento se generará automáticamente con los datos proporcionados') }}</flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Estudiante') }}</flux:label>
                    <flux:select wire:model="commitmentForm.student_id" :placeholder="__('Seleccione...')">
                        @foreach($modalStudents as $s)
                            <flux:select.option value="{{ $s['id'] }}">{{ $s['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="commitmentForm.student_id" />
                </flux:field>

                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>{{ __('Fecha') }}</flux:label>
                        <flux:input type="date" wire:model="commitmentForm.date" min="{{ $this->getNextBusinessDay() }}" />
                        @error('commitmentForm.date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Estado') }}</flux:label>
                        <flux:select wire:model="commitmentForm.status">
                            <flux:select.option value="draft">{{ __('Borrador') }}</flux:select.option>
                            <flux:select.option value="signed">{{ __('Firmada') }}</flux:select.option>
                            <flux:select.option value="closed">{{ __('Cerrada') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Compromisos') }}</flux:label>
                    <flux:textarea wire:model="commitmentForm.commitments" rows="6" :placeholder="__('Detalle los compromisos adquiridos...')" />
                    <flux:error name="commitmentForm.commitments" />
                </flux:field>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <div class="text-xs text-zinc-500 mb-1">{{ __('Se incluirán automáticamente') }}</div>
                    <ul class="text-xs text-zinc-600 dark:text-zinc-400 space-y-1">
                        <li>• Institución: {{ $this->currentSchool?->name_school ?? '—' }}</li>
                        <li>• Código único y número secuencial</li>
                        <li>• Datos del estudiante, representante y curso de tutoría</li>
                        <li>• Espacios para firmas</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCommitmentModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveCommitment">{{ __('Generar acta') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ==================== MODAL: INFORME ==================== --}}
    <flux:modal wire:model="showReportModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Generar Informe al Tutor') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('El informe consolidará todas las notificaciones y actas generadas') }}</flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Estudiante') }}</flux:label>
                    <flux:select wire:model="reportForm.student_id" :placeholder="__('Seleccione...')">
                        @foreach($modalStudents as $s)
                            <flux:select.option value="{{ $s['id'] }}">{{ $s['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="reportForm.student_id" />
                </flux:field>

                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>{{ __('Fecha') }}</flux:label>
                        <flux:input type="date" wire:model="reportForm.date" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Estado') }}</flux:label>
                        <flux:select wire:model="reportForm.status">
                            <flux:select.option value="draft">{{ __('Borrador') }}</flux:select.option>
                            <flux:select.option value="sent">{{ __('Enviado') }}</flux:select.option>
                            <flux:select.option value="archived">{{ __('Archivado') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Conclusión') }}</flux:label>
                    <flux:textarea wire:model="reportForm.conclusion" rows="4" :placeholder="__('Conclusión del informe...')" />
                    <flux:error name="reportForm.conclusion" />
                </flux:field>

                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <div class="text-xs text-zinc-500 mb-1">{{ __('Se incluirán automáticamente') }}</div>
                    <ul class="text-xs text-zinc-600 dark:text-zinc-400 space-y-1">
                        <li>• Número y código de informe</li>
                        <li>• Todas las notificaciones generadas con fechas y canales</li>
                        <li>• Asistencia del representante</li>
                        <li>• Actas de compromiso generadas</li>
                        <li>• Conclusión del caso</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showReportModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveReport">{{ __('Generar informe') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('whatsapp-send', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                if (data.wa) {
                    window.open(data.wa, '_blank');
                }

                if (data.pdf) {
                    const link = document.createElement('a');
                    link.href = data.pdf;
                    link.download = data.name || 'notificacion.pdf';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }
            });
        });
    </script>
</div>
