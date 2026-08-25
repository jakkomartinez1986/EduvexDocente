<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
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
use App\Models\TeacherManagement\Attendances\AttendanceSummary;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Services\AcademicYearService;
use App\Services\Messaging\ChannelStatusService;
use App\Services\Messaging\NotificationMessageBuilder;
use App\Services\Messaging\WaMeLinkService;
use App\Jobs\SendChannelMessageJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Libro de Incidencias')] class extends Component
{
    public string $category = 'academicas';

    public string $tab = 'detectar';

    public string $attendanceSubTab = 'hoy';

    public string $search = '';

    public ?int $filterSubjectId = null;

    public ?int $filterTrimesterId = null;

    public ?int $filterGradeId = null;
    public bool $searched = false;
    public ?string $selectedStudentName = null;
    public ?string $selectedRepresentativeName = null;

    public ?int $selectedStudentId = null;

    public ?int $selectedSubjectId = null;

    public ?int $selectedGradeId = null;

    public ?string $selectedCourseName = null;

    public bool $showNotificationModal = false;

    public bool $showInterventionModal = false;

    public bool $showCommitmentModal = false;

    public bool $showReportModal = false;

    public bool $showClassObservationModal = false;

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
        'subject_id' => null,
        'grade_id' => null,
        'date' => null,
        'description' => '',
        'status' => 'pending',
    ];

    public array $commitmentForm = [
        'type' => 'academico',
        'student_id' => null,
        'grade_id' => null,
        'subject_id' => null,
        'representative_id' => null,
        'date' => null,
        'commitments' => '',
        'status' => 'draft',
    ];

    public array $reportForm = [
        'type' => 'academico',
        'student_id' => null,
        'subject_id' => null,
        'grade_id' => null,
        'date' => null,
        'conclusion' => '',
        'status' => 'draft',
    ];

    public array $classObservationForm = [
        'student_id' => null,
        'classtopic' => '',
        'observation' => '',
        'class_observation' => '',
        'novedad' => '',
        'observation_date' => null,
    ];

    public string $reportContent = '';

    public ?int $yearId = null;

    public array $trimesters = [];

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
    public array $channelOptions = ['email', 'whatsapp', 'telegram', 'sms', 'sistema', 'impresa'];

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
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
        $this->notifForm['type'] = $category === 'academicas' ? 'academico' : ($category === 'comportamentales' ? 'comportamental' : 'asistencia');
        $this->search = '';

        if ($category === 'asistencia') {
            $this->tab = 'asistencia_list';
            $this->filterGradeId = null;
            $this->filterSubjectId = null;
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

    public function openNotificationModal(int $studentId, ?int $subjectId = null, ?int $gradeId = null, ?string $courseName = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->selectedSubjectId = $subjectId;
        $this->selectedGradeId = $gradeId;
        $this->selectedCourseName = $courseName;
        $this->editingNotificationId = null;

        $student = Student::with(['user', 'representatives.user'])->find($studentId);
        $this->selectedStudentName = $student?->user?->fullname;
        $this->selectedRepresentativeName = $student?->representatives->first()?->user?->fullname;

        $nextBusinessDay = $this->getNextBusinessDay();

        $message = $this->category === 'asistencia'
            ? $this->buildAttendanceAutoMessage($studentId)
            : $this->buildAutoMessage($studentId, $subjectId, $gradeId, $student);

        $this->notifForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'message' => $message,
            'motives' => [],
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

    protected function buildAutoMessage(int $studentId, ?int $subjectId, ?int $gradeId, ?Student $student = null): string
    {
        $lines = [];
        $student ??= Student::with('user')->find($studentId);
        $studentName = $student?->user?->fullname ?? '';

        if ($studentName) {
            $lines[] = 'Estimado(a) representante del estudiante '.$studentName.',';
            $lines[] = '';
        }

        if ($this->category === 'academicas' && $subjectId) {
            $subject = Subject::find($subjectId);
            $subjectName = $subject?->subject_name ?? 'la materia';

            $currentTrimesterId = $this->filterTrimesterId ?? $this->getCurrentTrimesterId();

            $homeworks = HomeworkPending::with('activity')->where('student_id', $studentId)
                ->where('year_id', $this->yearId)
                ->where('status', 'not_submitted')
                ->where('due_date', '<=', now()->toDateString())
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->orderBy('due_date', 'desc')
                ->get();

            if ($homeworks->isNotEmpty()) {
                $lines[] = 'TAREAS PENDIENTES (vencidas o por vencer) en '.$subjectName.':';
                foreach ($homeworks as $hw) {
                    $dueDate = $hw->due_date ? Carbon::parse($hw->due_date)->format('d/m/Y') : 'sin fecha';
                    $activityName = $hw->activity?->name ?? $hw->description;
                    $topic = $hw->topic ?? $hw->activity?->topic;
                    $line = '  - '.$activityName;
                    if ($topic) {
                        $line .= ' (Tema: '.$topic.')';
                    }
                    $line .= ' - Fecha limite: '.$dueDate;
                    $lines[] = $line;
                }
                $lines[] = '';
            }

            $activityGrades = ActivityGrade::where('student_id', $studentId)
                ->whereHas('activity.assessmentBlock', fn ($q) => $q->where('subject_id', $subjectId)->where('year_id', $this->yearId)
                )
                ->get();
            $grades = $activityGrades->pluck('grade')->filter();
            $promF = $grades->count() > 0 ? round($grades->avg(), 1) : null;

            $hasLowSummative = false;
            $examGrade = null;
            $projectGrade = null;
            if ($currentTrimesterId && $gradeId) {
                $examGrade = StudentExam::where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $currentTrimesterId)
                    ->where('year_id', $this->yearId)
                    ->value('grade');

                $projectGrade = StudentProject::where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $currentTrimesterId)
                    ->where('year_id', $this->yearId)
                    ->value('grade');

                $hasLowSummative = ($examGrade !== null && (float) $examGrade < 7)
                    || ($projectGrade !== null && (float) $projectGrade < 7);
            }

            $lowPerformance = false;
            if ($promF !== null && $promF < 7) {
                $lowPerformance = true;
            }

            if ($lowPerformance || $hasLowSummative) {
                $lines[] = 'RENDIMIENTO ACADÉMICO BAJO en '.$subjectName.':';
                if ($promF !== null) {
                    $lines[] = '  - Promedio formativo: '.number_format($promF, 1).'/10';
                }
                if ($examGrade !== null) {
                    $lines[] = '  - Nota examen: '.number_format((float) $examGrade, 1).'/10';
                }
                if ($projectGrade !== null) {
                    $lines[] = '  - Nota proyecto: '.number_format((float) $projectGrade, 1).'/10';
                }
                $lines[] = '';
                $lines[] = 'Solicitamos su atención para apoyar al estudiante en el mejoramiento de su rendimiento académico.';
                $lines[] = '';
            }

            if ($homeworks->isEmpty() && ! $lowPerformance && ! $hasLowSummative) {
                $lines[] = 'Le informamos sobre la situación académica del estudiante en '.$subjectName.'.';
                $lines[] = '';
            }
        } elseif ($this->category === 'comportamentales' && $subjectId) {
            $subject = Subject::find($subjectId);
            $subjectName = $subject?->subject_name ?? 'la asignatura';

            $lines[] = 'Se reporta la siguiente situación de comportamiento del estudiante en la asignatura de '.$subjectName.':';
            $lines[] = '';
            $lines[] = 'El estudiante ha presentado conductas que requieren la atención del representante. A continuación se detallan los motivos de la notificación:';
            $lines[] = '';
            $lines[] = 'Asignatura: '.$subjectName;
            $lines[] = 'Fecha: '.now()->format('d/m/Y H:i');
            $lines[] = '';
            $lines[] = 'Solicitamos su apoyo para reforzar el comportamiento del estudiante en el ámbito escolar.';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    protected function buildWhatsAppUrl(AcademicNotification $notification): string
    {
        return $this->resolveWhatsAppPayloads($notification)[0]['url'] ?? '';
    }

    public function openInterventionModal(?int $studentId = null, ?int $subjectId = null, ?int $gradeId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->selectedSubjectId = $subjectId;
        $this->selectedGradeId = $gradeId;
        $this->editingInterventionId = null;
        $this->interventionForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'action_type' => '',
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'grade_id' => $gradeId,
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
            'subject_id' => $intervention->subject_id,
            'grade_id' => $intervention->grade_id,
            'date' => $intervention->date?->format('Y-m-d'),
            'description' => $intervention->description,
            'status' => $intervention->status,
        ];
        $this->showInterventionModal = true;
    }

    public function saveIntervention(): void
    {
        $this->validate([
            'interventionForm.action_type' => 'required|string|max:255',
            'interventionForm.date' => 'required|date',
            'interventionForm.description' => 'nullable|string',
        ]);

        $studentId = $this->interventionForm['student_id'] ?? $this->selectedStudentId;

        if (! $studentId) {
            Flux::toast(variant: 'danger', text: __('Debe seleccionar un estudiante para registrar la intervención.'));

            return;
        }

        $data = [
            'type' => $this->interventionForm['type'],
            'action_type' => $this->interventionForm['action_type'],
            'student_id' => $studentId,
            'teacher_id' => auth()->user()->teacher?->id,
            'grade_id' => $this->interventionForm['grade_id'] ?? $this->selectedGradeId,
            'subject_id' => $this->interventionForm['subject_id'] ?? $this->selectedSubjectId,
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
            'grade_id' => $this->selectedGradeId,
            'subject_id' => $this->selectedSubjectId,
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

        $payloads = $this->resolveWhatsAppPayloads($notification);

        $this->sendTelegramForNotification($notification, $path, $fileName);

        if ($payloads === []) {
            Flux::toast(variant: 'warning', text: __('El representante no tiene celular registrado. Se descargó el PDF para enviarlo manualmente.'));

            return;
        }

        if (app(ChannelStatusService::class)->apiAvailable(ChannelConfiguration::CHANNEL_WHATSAPP)) {
            $channelRow = NotificationChannel::where('notification_id', $notification->id)
                ->where('channel', 'whatsapp')
                ->first();

            foreach ($payloads as $payload) {
                SendChannelMessageJob::dispatch(
                    ChannelConfiguration::CHANNEL_WHATSAPP,
                    $payload['phone'],
                    $payload['message'],
                    $path,
                    $fileName,
                    $channelRow?->id,
                );
            }

            $notification->update(['sent_at' => $notification->sent_at ?? now()]);

            Flux::toast(variant: 'success', text: __('PDF generado. Notificación en cola de envío por WhatsApp.'));

            return;
        }

        NotificationChannel::where('notification_id', $notification->id)
            ->where('channel', 'whatsapp')
            ->update(['status' => 'sent', 'sent_at' => now()]);
        $notification->update(['sent_at' => $notification->sent_at ?? now()]);

        $urls = array_column($payloads, 'url');

        $this->dispatch('whatsapp-send', wa: $urls, pdf: $pdfUrl, name: $fileName);
    }

    public function saveNotificationThenTelegram(): void
    {
        $this->saveNotification();

        $lastNotif = AcademicNotification::where('teacher_id', auth()->user()->teacher?->id)
            ->latest()
            ->first();

        if ($lastNotif) {
            $this->sendTelegramForNotification($lastNotif);
        }
    }

    protected function sendTelegramForNotification(AcademicNotification $notification, ?string $pdfPath = null, ?string $pdfName = null): bool
    {
        if (! app(ChannelStatusService::class)->apiAvailable(ChannelConfiguration::CHANNEL_TELEGRAM)) {
            Flux::toast(variant: 'warning', text: __('Telegram no está habilitado o le faltan credenciales.'));

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

        $message = $pdfPath !== null
            ? app(NotificationMessageBuilder::class)->whatsappMessage($notification, $notification->student?->representatives->first())
            : (string) $notification->message;

        $channelRow = NotificationChannel::where('notification_id', $notification->id)
            ->where('channel', 'telegram')
            ->first();

        foreach ($chatIds as $chatId) {
            SendChannelMessageJob::dispatch(
                ChannelConfiguration::CHANNEL_TELEGRAM,
                $chatId,
                $message,
                $pdfPath,
                $pdfName,
                $channelRow?->id,
            );
        }

        return true;
    }

    /**
     * @return array<int, array{phone: string, message: string, url: string}>
     */
    protected function resolveWhatsAppPayloads(AcademicNotification $notification): array
    {
        $linkService = app(WaMeLinkService::class);
        $messageBuilder = app(NotificationMessageBuilder::class);

        $payloads = [];

        foreach ($notification->student?->representatives ?? [] as $representative) {
            $phone = $linkService->normalizePhone($representative->user?->cellphone ?? $representative->user?->phone);

            if (! $phone) {
                continue;
            }

            $message = $messageBuilder->whatsappMessage($notification, $representative);

            $payloads[] = [
                'phone' => $phone,
                'message' => $message,
                'url' => $linkService->buildLink($phone, $message),
            ];
        }

        return $payloads;
    }

    #[Computed]
    public function messagingStatuses(): array
    {
        return app(ChannelStatusService::class)->forChannels([
            ChannelConfiguration::CHANNEL_WHATSAPP,
            ChannelConfiguration::CHANNEL_TELEGRAM,
        ]);
    }

    public function openCommitmentModal(int $studentId, ?int $subjectId = null, ?int $gradeId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->selectedSubjectId = $subjectId;
        $this->selectedGradeId = $gradeId;
        $this->commitmentForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'student_id' => $studentId,
            'grade_id' => $gradeId,
            'subject_id' => $subjectId,
            'representative_id' => null,
            'date' => now()->format('Y-m-d'),
            'commitments' => '',
            'status' => 'draft',
        ];

        $rep = Representative::where('student_id', $studentId)->first();
        $this->commitmentForm['representative_id'] = $rep?->id;

        $this->showCommitmentModal = true;
    }

    public function saveCommitment(): void
    {
        $this->validate([
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
            'student_id' => $this->commitmentForm['student_id'],
            'grade_id' => $this->commitmentForm['grade_id'],
            'subject_id' => $this->commitmentForm['subject_id'],
            'teacher_id' => $teacher?->id,
            'representative_id' => $this->commitmentForm['representative_id'],
            'year_id' => $this->yearId,
            'date' => $this->commitmentForm['date'],
            'commitments' => $this->commitmentForm['commitments'],
            'status' => $this->commitmentForm['status'],
        ]);

        Flux::toast(variant: 'success', text: __('Acta de compromiso generada correctamente. Código: ').$code);
        $this->showCommitmentModal = false;
    }

    public function openReportModal(int $studentId, ?int $subjectId = null, ?int $gradeId = null): void
    {
        $this->selectedStudentId = $studentId;
        $this->selectedSubjectId = $subjectId;
        $this->selectedGradeId = $gradeId;
        $this->reportForm = [
            'type' => $this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia'),
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'grade_id' => $gradeId,
            'date' => now()->format('Y-m-d'),
            'conclusion' => '',
            'status' => 'draft',
        ];
        $this->showReportModal = true;
    }

    public function saveReport(): void
    {
        $this->validate([
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
            'student_id' => $this->reportForm['student_id'],
            'grade_id' => $this->reportForm['grade_id'],
            'subject_id' => $this->reportForm['subject_id'],
            'teacher_id' => $teacher?->id,
            'tutor_id' => null,
            'year_id' => $this->yearId,
            'date' => $this->reportForm['date'],
            'conclusion' => $this->reportForm['conclusion'],
            'status' => $this->reportForm['status'],
        ]);

        Flux::toast(variant: 'success', text: __('Informe generado correctamente. Código: ').$code);
        $this->showReportModal = false;
    }

    public function openClassObservationModal(int $studentId): void
    {
        $this->selectedStudentId = $studentId;
        $this->classObservationForm = [
            'student_id' => $studentId,
            'classtopic' => '',
            'observation' => '',
            'class_observation' => '',
            'novedad' => '',
            'observation_date' => now()->format('Y-m-d'),
        ];
        $this->showClassObservationModal = true;
    }

    public function saveClassObservation(): void
    {
        $this->validate([
            'classObservationForm.classtopic' => 'required|string|max:255',
            'classObservationForm.observation' => 'required|string',
        ]);

        $schedule = $this->getTeacherSchedules()->first();
        if ($schedule) {
            ClassObservation::create([
                'class_schedule_id' => $schedule->id,
                'teacher_id' => auth()->user()->teacher?->id,
                'year_id' => $this->yearId,
                'observation_date' => $this->classObservationForm['observation_date'],
                'classtopic' => $this->classObservationForm['classtopic'],
                'observation' => $this->classObservationForm['observation'],
                'class_observation' => $this->classObservationForm['class_observation'],
                'novedad' => $this->classObservationForm['novedad'],
            ]);
        }

        Flux::toast(variant: 'success', text: __('Observación de clase registrada correctamente.'));
        $this->showClassObservationModal = false;
    }

    public function getTeacherSchedulesProperty()
    {
        return $this->getTeacherSchedules();
    }

    protected ?\Illuminate\Support\Collection $teacherSchedulesCache = null;

    protected function registroScope($query)
    {
        return $query->where('teacher_id', auth()->user()->teacher?->id);
    }

    protected function getTeacherSchedules()
    {
        if ($this->teacherSchedulesCache !== null) {
            return $this->teacherSchedulesCache;
        }

        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return $this->teacherSchedulesCache = collect();
        }

        return $this->teacherSchedulesCache = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->with(['subject', 'grade.nivel.shift'])
            ->get();
    }

    public function getSubjectsProperty()
    {
        return $this->getTeacherSchedules()
            ->pluck('subject')
            ->unique('id')
            ->values();
    }

    public function getFilterGradesProperty()
    {
        return $this->getTeacherSchedules()
            ->pluck('grade')
            ->unique('id')
            ->values()
            ->map(fn ($g) => [
                'id' => $g['id'],
                'name' => ($g['grade_name'] ?? '').' '.($g['section'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function getFilterSubjectsProperty()
    {
        return $this->getTeacherSchedules()
            ->when($this->filterGradeId, fn ($q) => $q->where('grade_id', $this->filterGradeId))
            ->pluck('subject')
            ->unique('id')
            ->values()
            ->all();
    }

    public function updatedFilterGradeId(): void
    {
        $this->filterSubjectId = null;
    }

    public function resetFilters(): void
    {
        $this->filterTrimesterId = null;
        $this->filterGradeId = null;
        $this->filterSubjectId = null;
        $this->search = '';
        $this->searched = false;
    }

    public function buscar(): void
    {
        if (! $this->filterGradeId && ! $this->filterSubjectId && trim($this->search) === '') {
            $this->addError('buscar', __('Escriba el nombre del estudiante o seleccione un curso o asignatura.'));

            return;
        }

        $this->resetErrorBag();
        $this->searched = true;
    }

    public function getAttendanceStudentsProperty()
    {
        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return collect();
        }

        $schedules = $this->getTeacherSchedules();
        $gradeIds = $schedules->pluck('grade_id')->unique()->values()->all();
        $currentTrimesterId = $this->filterTrimesterId ?? $this->getCurrentTrimesterId();
        $currentPeriod = $currentTrimesterId ? AcademicPeriod::find($currentTrimesterId) : null;

        if (! $currentPeriod) {
            return collect();
        }

        $studentIds = StudentEnrollment::whereIn('grade_id', $gradeIds)
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        $students = Student::whereIn('id', $studentIds)
            ->with(['user', 'representatives.user'])
            ->get()
            ->keyBy('id');

        $today = now()->toDateString();
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = now()->endOfWeek(Carbon::FRIDAY)->toDateString();

        $results = collect();

        foreach ($students as $student) {
            $baseQuery = Attendance::where('student_id', $student->id)
                ->where('year_id', $this->yearId)
                ->whereBetween('date', [$currentPeriod->start_date, $currentPeriod->end_date])
                ->where('status', 'I');

            $allAbsences = $baseQuery->orderBy('date')->get();

            if ($allAbsences->isEmpty()) {
                continue;
            }

            $todayAbsences = $allAbsences->filter(fn ($a) => $a->date->toDateString() === $today);
            $weekAbsences = $allAbsences->filter(fn ($a) => $a->date >= $weekStart && $a->date <= $weekEnd);

            $consecutiveDates = [];
            $sortedDates = $allAbsences->pluck('date')->sort()->values();
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

            $lastNotif = AcademicNotification::where('student_id', $student->id)
                ->where('type', 'asistencia')
                ->where('year_id', $this->yearId)
                ->latest()
                ->first();

            $results->push((object) [
                'student' => $student,
                'totalAbsences' => $allAbsences->count(),
                'todayAbsences' => $todayAbsences->count(),
                'weekAbsences' => $weekAbsences->count(),
                'consecutiveDates' => $consecutiveDates,
                'consecutiveCount' => count($consecutiveDates),
                'allAbsenceDates' => $sortedDates->implode(', '),
                'lastNotif' => $lastNotif?->generated_date?->format('d/m/Y') ?? '-',
                'gradeName' => $schedules->firstWhere('grade_id', $student->enrollments->first()?->grade_id)?->grade?->grade_name ?? '',
            ]);
        }

        $results = $results->sortByDesc('totalAbsences')->values();

        if ($this->search) {
            $results = $results->filter(fn ($r) => str_contains(strtolower($r->student?->user?->fullname ?? ''), strtolower($this->search)) ||
                str_contains(strtolower($r->student?->student_code ?? ''), strtolower($this->search))
            )->values();
        }

        return $results;
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

        if ($currentPeriod) {
            $absences = Attendance::where('student_id', $studentId)
                ->where('year_id', $this->yearId)
                ->where('status', 'I')
                ->whereBetween('date', [$currentPeriod->start_date, $currentPeriod->end_date])
                ->orderBy('date')
                ->get();

            if ($absences->isNotEmpty()) {
                $lines[] = 'Le informamos sobre las inasistencias del estudiante en el periodo '.$currentPeriod->trimester_name.':';
                $lines[] = '';
                $lines[] = 'Total de inasistencias: '.$absences->count();
                $lines[] = '';

                $sortedDates = $absences->pluck('date')->sort()->values();
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

                if (count($consecutiveDates) >= 2) {
                    $lines[] = 'Inasistencias consecutivas detectadas:';
                    foreach ($consecutiveDates as $cd) {
                        $lines[] = '  - '.Carbon::parse($cd)->format('d/m/Y');
                    }
                    $lines[] = 'Total consecutivo: '.count($consecutiveDates).' dias';
                    $lines[] = '';
                } else {
                    $lines[] = 'Las inasistencias se encuentran distribuidas en diferentes fechas del trimestre.';
                    $lines[] = '';
                }

                $lines[] = 'Fechas de inasistencia: '.$sortedDates->map(fn ($d) => Carbon::parse($d)->format('d/m/Y'))->implode(', ');
                $lines[] = '';
                $lines[] = 'Solicitamos amablemente justifique las inasistencias del estudiante.';
                $lines[] = '';
            } else {
                $lines[] = 'Le informamos que no se registran inasistencias injustificadas en el periodo '.$currentPeriod->trimester_name.'.';
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    public function getDetectStudentsProperty()
    {
        $schedules = $this->getTeacherSchedules();

        if ($this->filterGradeId) {
            $schedules = $schedules->where('grade_id', $this->filterGradeId);
        }
        if ($this->filterSubjectId) {
            $schedules = $schedules->where('subject_id', $this->filterSubjectId);
        }

        if ($schedules->isEmpty()) {
            return collect();
        }

        $teacherId = auth()->user()->teacher?->id;
        $subjectIds = $schedules->pluck('subject_id')->unique();
        $gradeIds = $schedules->pluck('grade_id')->unique();

        $enrollments = StudentEnrollment::whereIn('grade_id', $gradeIds)
            ->where('year_id', $this->yearId)
            ->where('status', 'active')
            ->get();

        $studentIds = $enrollments->pluck('student_id')->unique();

        $students = Student::whereIn('id', $studentIds)
            ->with(['user', 'enrollments.grade', 'representatives.user'])
            ->get()
            ->keyBy('id');

        $currentTrimesterId = $this->filterTrimesterId ?? $this->getCurrentTrimesterId();
        $currentPeriod = $currentTrimesterId ? AcademicPeriod::find($currentTrimesterId) : null;

        $homeworkCounts = collect();
        $activityGradesByPair = collect();
        $examGrades = collect();
        $projectGrades = collect();
        $notifCounts = collect();
        $lastNotifs = collect();
        $absencesByStudent = collect();
        $summariesByStudent = collect();

        if ($this->category === 'academicas') {
            $homeworkCounts = HomeworkPending::whereIn('student_id', $studentIds)
                ->whereIn('subject_id', $subjectIds)
                ->where('year_id', $this->yearId)
                ->where('status', 'not_submitted')
                ->where('due_date', '<=', now()->toDateString())
                ->selectRaw('student_id, subject_id, count(*) as total')
                ->groupBy('student_id', 'subject_id')
                ->get()
                ->keyBy(fn ($row) => $row->student_id.':'.$row->subject_id);

            $activityGradesByPair = ActivityGrade::whereIn('student_id', $studentIds)
                ->whereHas('activity.assessmentBlock', fn ($q) => $q->whereIn('subject_id', $subjectIds)->where('year_id', $this->yearId)
                )
                ->with('activity.assessmentBlock')
                ->get()
                ->groupBy(fn ($ag) => $ag->student_id.':'.$ag->activity?->assessmentBlock?->subject_id);

            if ($currentTrimesterId) {
                $examGrades = StudentExam::whereIn('student_id', $studentIds)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereIn('grade_id', $gradeIds)
                    ->where('trimester_id', $currentTrimesterId)
                    ->where('year_id', $this->yearId)
                    ->get()
                    ->keyBy(fn ($e) => $e->student_id.':'.$e->subject_id.':'.$e->grade_id);

                $projectGrades = StudentProject::whereIn('student_id', $studentIds)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereIn('grade_id', $gradeIds)
                    ->where('trimester_id', $currentTrimesterId)
                    ->where('year_id', $this->yearId)
                    ->get()
                    ->keyBy(fn ($p) => $p->student_id.':'.$p->subject_id.':'.$p->grade_id);
            }
        }

        if (in_array($this->category, ['academicas', 'comportamentales'])) {
            $notifType = $this->category === 'comportamentales' ? 'comportamental' : 'academico';

            $notifCounts = AcademicNotification::whereIn('student_id', $studentIds)
                ->where('type', $notifType)
                ->where('year_id', $this->yearId)
                ->selectRaw('student_id, count(*) as total')
                ->groupBy('student_id')
                ->pluck('total', 'student_id');

            $lastNotifIds = AcademicNotification::whereIn('student_id', $studentIds)
                ->where('type', $notifType)
                ->where('year_id', $this->yearId)
                ->selectRaw('max(id) as last_id')
                ->groupBy('student_id')
                ->pluck('last_id');

            $lastNotifs = AcademicNotification::whereIn('id', $lastNotifIds)
                ->get()
                ->keyBy('student_id');
        }

        if ($this->category === 'asistencia' && $currentPeriod) {
            $absencesByStudent = Attendance::whereIn('student_id', $studentIds)
                ->where('year_id', $this->yearId)
                ->where('status', 'I')
                ->whereBetween('date', [$currentPeriod->start_date, $currentPeriod->end_date])
                ->orderBy('date')
                ->get()
                ->groupBy('student_id');

            $summariesByStudent = AttendanceSummary::whereIn('student_id', $studentIds)
                ->where('year_id', $this->yearId)
                ->get()
                ->groupBy('student_id');
        }

        $results = collect();

        foreach ($schedules as $schedule) {
            $gradeStudentIds = $enrollments->where('grade_id', $schedule->grade_id)->pluck('student_id');

            foreach ($gradeStudentIds as $sid) {
                $student = $students->get($sid);
                if (! $student) {
                    continue;
                }

                if ($this->category === 'academicas') {
                    $homeworkCount = (int) ($homeworkCounts->get($sid.':'.$schedule->subject_id)?->total ?? 0);

                    $grades = ($activityGradesByPair->get($sid.':'.$schedule->subject_id) ?? collect())
                        ->pluck('grade')
                        ->filter();
                    $promF = $grades->count() > 0 ? round($grades->avg(), 1) : null;

                    $hasLowSummative = false;
                    if ($currentTrimesterId) {
                        $pairSuffix = $sid.':'.$schedule->subject_id.':'.$schedule->grade_id;
                        $examGrade = $examGrades->get($pairSuffix)?->grade;
                        $projectGrade = $projectGrades->get($pairSuffix)?->grade;

                        $hasLowSummative = ($examGrade !== null && (float) $examGrade < 7)
                            || ($projectGrade !== null && (float) $projectGrade < 7);
                    }

                    if ($homeworkCount === 0 && ($promF === null || $promF >= 7) && ! $hasLowSummative) {
                        continue;
                    }

                    $incidentCount = (int) ($notifCounts->get($sid) ?? 0);
                    $lastNotif = $lastNotifs->get($sid);

                    $results->push((object) [
                        'student' => $student,
                        'schedule' => $schedule,
                        'promF' => $promF,
                        'homeworkPending' => $homeworkCount,
                        'incidentCount' => $incidentCount,
                        'lastNotif' => $lastNotif?->generated_date?->format('d/m/Y') ?? '-',
                        'subjectName' => $schedule->subject->subject_name,
                        'gradeName' => ($schedule->grade->grade_name ?? '').' '.($schedule->grade->section ?? ''),
                    ]);
                } elseif ($this->category === 'comportamentales') {
                    $incidentCount = (int) ($notifCounts->get($sid) ?? 0);
                    $lastNotif = $lastNotifs->get($sid);

                    $results->push((object) [
                        'student' => $student,
                        'schedule' => $schedule,
                        'incidentCount' => $incidentCount,
                        'lastNotif' => $lastNotif?->generated_date?->format('d/m/Y') ?? '-',
                        'subjectName' => $schedule->subject->subject_name,
                        'gradeName' => ($schedule->grade->grade_name ?? '').' '.($schedule->grade->section ?? ''),
                    ]);
                } elseif ($this->category === 'asistencia') {
                    $absenceGroup = $absencesByStudent->get($sid);

                    if (! $absenceGroup || $absenceGroup->isEmpty()) {
                        continue;
                    }

                    $summaryGroup = $summariesByStudent->get($sid) ?? collect();
                    $totalIA = $summaryGroup->sum('unjustified_count');
                    $totalAT = $summaryGroup->sum('late_count');
                    $totalAI = $summaryGroup->sum('abandonment_count');

                    $results->push((object) [
                        'student' => $student,
                        'schedule' => $schedule,
                        'totalIA' => $totalIA,
                        'totalAT' => $totalAT,
                        'totalAI' => $totalAI,
                        'lastAttendance' => $absenceGroup->last(),
                        'subjectName' => $schedule->subject->subject_name,
                        'gradeName' => ($schedule->grade->grade_name ?? '').' '.($schedule->grade->section ?? ''),
                    ]);
                }
            }
        }

        if ($this->search) {
            $results = $results->filter(fn ($r) => str_contains(strtolower($r->student?->user?->fullname ?? ''), strtolower($this->search)) ||
                str_contains(strtolower($r->student?->student_code ?? ''), strtolower($this->search))
            );
        }

        return $results->values();
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

    public function getInterventionsProperty()
    {
        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return collect();
        }

        $type = match ($this->category) {
            'academicas' => 'academico',
            'comportamentales' => 'comportamental',
            'asistencia' => 'asistencia',
            default => 'academico',
        };

        return $this->registroScope(IncidentIntervention::query())
            ->where('type', $type)
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getCommitmentLettersProperty()
    {
        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return collect();
        }

        $type = match ($this->category) {
            'academicas' => 'academico',
            'comportamentales' => 'comportamental',
            'asistencia' => 'asistencia',
            default => 'academico',
        };

        return $this->registroScope(IncidentCommitmentLetter::query())
            ->where('type', $type)
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getReportsProperty()
    {
        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return collect();
        }

        $type = match ($this->category) {
            'academicas' => 'academico',
            'comportamentales' => 'comportamental',
            'asistencia' => 'asistencia',
            default => 'academico',
        };

        return $this->registroScope(IncidentReport::query())
            ->where('type', $type)
            ->where('year_id', $this->yearId)
            ->with(['student.user', 'subject', 'grade'])
            ->latest('date')
            ->get();
    }

    public function getInterventionStatsProperty()
    {
        $teacherId = auth()->user()->teacher?->id;
        if (! $teacherId) {
            return ['month' => 0, 'refuerzo' => 0, 'tutoria' => 0, 'recuperacion' => 0];
        }

        $type = match ($this->category) {
            'academicas' => 'academico',
            'comportamentales' => 'comportamental',
            'asistencia' => 'asistencia',
            default => 'academico',
        };

        $all = $this->registroScope(IncidentIntervention::query())
            ->where('type', $type)
            ->where('year_id', $this->yearId);

        $month = (clone $all)->whereMonth('date', now()->month)->count();
        $refuerzo = (clone $all)->where('action_type', 'like', '%Refuerzo%')->count();
        $tutoria = (clone $all)->where('action_type', 'like', '%Tutoría%')->count();
        $recuperacion = (clone $all)->where('action_type', 'like', '%Recuperación%')->count();

        return compact('month', 'refuerzo', 'tutoria', 'recuperacion');
    }

    public function getStudentNotificationsProperty()
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return AcademicNotification::where('student_id', $this->selectedStudentId)
            ->where('year_id', $this->yearId)
            ->with(['channels'])
            ->latest()
            ->get();
    }

    public function getStudentCommitmentLettersProperty()
    {
        if (! $this->selectedStudentId) {
            return collect();
        }

        return IncidentCommitmentLetter::where('student_id', $this->selectedStudentId)
            ->where('year_id', $this->yearId)
            ->latest()
            ->get();
    }

    public function getCurrentSchoolProperty()
    {
        return School::where('status', 1)->first();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Libro de Incidencias') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestión integral de incidencias académicas, comportamentales y de asistencia') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Libro de Incidencias') }}</span>
    </nav>

    @if($this->teacherSchedules->isEmpty())
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.exclamation-triangle class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No tiene asignaciones docentes') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontraron horas asignadas para su usuario en el año lectivo activo.') }}</p>
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

        {{-- ==================== ASISTENCIA: LISTA DE INASISTENCIAS ==================== --}}
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

                {{-- Sub-tabs: Hoy / Semana --}}
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
                                <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Inasist. Trimestre') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Consecutivas') }}</th>
                                @if($this->attendanceSubTab === 'hoy')
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Inasist. Hoy') }}</th>
                                @else
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Inasist. Semana') }}</th>
                                @endif
                                <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Ultima Notif.') }}</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($this->attendanceStudents as $item)
                                @php
                                    $show = $this->attendanceSubTab === 'hoy'
                                        ? $item->todayAbsences > 0
                                        : $item->weekAbsences > 0;
                                @endphp
                                @if($show)
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
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->totalAbsences >= 5 ? 'red' : ($item->totalAbsences >= 3 ? 'yellow' : 'zinc')">{{ $item->totalAbsences }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($item->consecutiveCount >= 2)
                                                <flux:badge color="red">{{ $item->consecutiveCount }} dias</flux:badge>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        @if($this->attendanceSubTab === 'hoy')
                                            <td class="px-4 py-3 text-center">
                                                <flux:badge :color="$item->todayAbsences > 0 ? 'red' : 'zinc'">{{ $item->todayAbsences }}</flux:badge>
                                            </td>
                                        @else
                                            <td class="px-4 py-3 text-center">
                                                <flux:badge :color="$item->weekAbsences > 0 ? 'red' : 'zinc'">{{ $item->weekAbsences }}</flux:badge>
                                            </td>
                                        @endif
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $item->lastNotif }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                                <flux:menu>
                                                    <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }}, null, null)" icon="bell">{{ __('Notificar') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }})" icon="chat-bubble-left-right">{{ __('Intervenir') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-16 text-center">
                                        <flux:icon.clipboard-document-check class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                        <flux:text variant="subtle" class="text-sm">{{ __('No hay inasistencias registradas para este periodo.') }}</flux:text>
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
                        <flux:input wire:model="search" :placeholder="__('Buscar estudiante...')" icon="magnifying-glass" />
                    </div>
                    <div class="w-full sm:w-48">
                        <flux:label>{{ __('Trimestre') }}</flux:label>
                        <flux:select wire:model="filterTrimesterId" placeholder="{{ __('Todos') }}">
                            @foreach($trimesters as $tri)
                                <flux:select.option value="{{ $tri['id'] }}">{{ $tri['trimester_name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-56">
                        <flux:label>{{ __('Grado') }}</flux:label>
                        <flux:select wire:model="filterGradeId" placeholder="{{ __('Todos') }}">
                            @foreach($this->filterGrades as $grade)
                                <flux:select.option value="{{ $grade['id'] }}">{{ $grade['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="w-full sm:w-56">
                        <flux:label>{{ __('Asignatura') }}</flux:label>
                        <flux:select wire:model="filterSubjectId" placeholder="{{ __('Todas') }}">
                            @foreach($this->filterSubjects as $subject)
                                <flux:select.option value="{{ $subject['id'] }}">{{ $subject['subject_name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="flex items-end gap-2">
                        <flux:button wire:click="buscar" variant="primary" icon="magnifying-glass">{{ __('Buscar') }}</flux:button>
                        @if($filterTrimesterId || $filterGradeId || $filterSubjectId || $search)
                            <flux:button wire:click="resetFilters" size="sm" variant="ghost" icon="x-mark">{{ __('Limpiar') }}</flux:button>
                        @endif
                    </div>
                    @error('buscar')
                        <span class="text-sm text-red-500 self-center">{{ $message }}</span>
                    @enderror
                </div>

                @if(! $this->searched)
                    <div class="text-center py-16 text-zinc-400 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                        <flux:icon.magnifying-glass class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-base font-semibold">{{ __('Escriba el nombre del estudiante y presione Buscar, o filtre por curso y asignatura') }}</p>
                        <p class="text-sm text-zinc-400 mt-1">{{ __('La búsqueda se limita a los estudiantes con los que dicta clases.') }}</p>
                    </div>
                @elseif($this->category === 'academicas')
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Curso') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Materia') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Prom. Formativo') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Deberes Pend.') }}</th>
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
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $item->gradeName }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="blue">{{ $item->subjectName }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($item->promF !== null)
                                                <span class="font-mono text-sm font-bold {{ $item->promF >= 7 ? 'text-emerald-600' : ($item->promF >= 5 ? 'text-amber-600' : 'text-red-600') }}">
                                                    {{ number_format($item->promF, 1) }}
                                                </span>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->homeworkPending > 0 ? 'red' : 'zinc'">{{ $item->homeworkPending }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->incidentCount > 0 ? 'red' : 'zinc'">{{ $item->incidentCount }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $item->lastNotif }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                                <flux:menu>
                                                    <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }}, {{ $item->schedule->subject_id }}, {{ $item->schedule->grade_id }}, '{{ $item->gradeName }}')" icon="bell">
                                                        {{ __('Notificar') }}
                                                    </flux:menu.item>
                                                    <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }}, {{ $item->schedule->subject_id }}, {{ $item->schedule->grade_id }})" icon="chat-bubble-left-right">
                                                        {{ __('Intervenir') }}
                                                    </flux:menu.item>
                                                    <flux:menu.item wire:click="openClassObservationModal({{ $item->student->id }})" icon="pencil-square">
                                                        {{ __('Observación de clase') }}
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-16 text-center">
                                            <flux:icon.academic-cap class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                            <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                @elseif($this->category === 'comportamentales')
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Curso') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Materia') }}</th>
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
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $item->gradeName }}</td>
                                        <td class="px-4 py-3">
                                            <flux:badge color="blue">{{ $item->subjectName }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->incidentCount > 0 ? 'red' : 'zinc'">{{ $item->incidentCount }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-zinc-500">{{ $item->lastNotif }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                                <flux:menu>
                                                    <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }}, {{ $item->schedule->subject_id }}, {{ $item->schedule->grade_id }}, '{{ $item->gradeName }}')" icon="bell">{{ __('Notificar') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }}, {{ $item->schedule->subject_id }}, {{ $item->schedule->grade_id }})" icon="chat-bubble-left-right">{{ __('Intervenir') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openClassObservationModal({{ $item->student->id }})" icon="pencil-square">{{ __('Observación de clase') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-16 text-center">
                                            <flux:icon.user-group class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                            <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                @elseif($this->category === 'asistencia')
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                                    <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Curso') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Materia') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Inasistencias') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Atrasos') }}</th>
                                    <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Abandono Inst.') }}</th>
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
                                                    <div class="text-xs text-zinc-500 font-mono">{{ $item->student?->user?->dni ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $item->gradeName }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge color="blue">{{ $item->subjectName }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->totalIA >= 5 ? 'red' : ($item->totalIA >= 3 ? 'yellow' : 'zinc')">{{ $item->totalIA }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->totalAT >= 5 ? 'red' : ($item->totalAT >= 3 ? 'yellow' : 'zinc')">{{ $item->totalAT }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:badge :color="$item->totalAI > 0 ? 'red' : 'zinc'">{{ $item->totalAI }}</flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                                <flux:menu>
                                                    <flux:menu.item wire:click="openNotificationModal({{ $item->student->id }}, {{ $item->schedule->subject_id }}, {{ $item->schedule->grade_id }})" icon="bell">{{ __('Notificar') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openInterventionModal({{ $item->student->id }})" icon="chat-bubble-left-right">{{ __('Intervenir') }}</flux:menu.item>
                                                    <flux:menu.item wire:click="openClassObservationModal({{ $item->student->id }})" icon="pencil-square">{{ __('Observación de clase') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-16 text-center">
                                            <flux:icon.clipboard-document-list class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                            <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron registros de asistencia.') }}</flux:text>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
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
                                &middot; {{ $intervention->subject?->subject_name ?? '-' }}
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
                            <flux:text variant="subtle">{{ __('No hay intervenciones registradas.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

        {{-- ==================== TAB: EVIDENCIAR ==================== --}}
        @elseif($this->tab === 'evidenciar')
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div></div>
                    <flux:button wire:click="openCommitmentModal({{ $this->detectStudents->first()?->student->id ?? 0 }}, {{ $this->detectStudents->first()?->schedule->subject_id ?? null }}, {{ $this->detectStudents->first()?->schedule->grade_id ?? null }})" variant="primary" icon="plus">
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
                            <flux:text variant="subtle">{{ __('No hay actas de compromiso generadas.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>

        {{-- ==================== TAB: INFORMAR ==================== --}}
        @elseif($this->tab === 'informar')
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div></div>
                    <flux:button wire:click="openReportModal({{ $this->detectStudents->first()?->student->id ?? 0 }}, {{ $this->detectStudents->first()?->schedule->subject_id ?? null }}, {{ $this->detectStudents->first()?->schedule->grade_id ?? null }})" variant="primary" icon="plus">
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
                            <flux:text variant="subtle">{{ __('No hay informes generados.') }}</flux:text>
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
                        <flux:textarea wire:model="notifForm.message" rows="8" :placeholder="__('El mensaje se genera automáticamente con las actividades pendientes y el rendimiento del estudiante...')" />
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
                                @if($channel === 'telegram' && ! ($this->messagingStatuses['telegram']['api_available'] ?? false))
                                    <label class="flex items-center gap-2 text-sm opacity-50 cursor-not-allowed" title="{{ __('Requiere configurar y habilitar la API de Telegram en Ajustes.') }}">
                                        <input type="checkbox" value="{{ $channel }}" disabled />
                                        <span>{{ __(ucfirst($channel)) }} <span class="text-xs text-zinc-400 dark:text-zinc-500">({{ __('API no configurada') }})</span></span>
                                    </label>
                                @else
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <flux:checkbox value="{{ $channel }}" wire:model="notifForm.channels" />
                                        <span class="inline-flex items-center gap-1.5">
                                            {{ __(ucfirst($channel)) }}
                                            @if(in_array($channel, ['whatsapp', 'telegram'], true))
                                                <span class="size-1.5 rounded-full {{ (($this->messagingStatuses[$channel]['api_available'] ?? false)) ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></span>
                                                @if(! ($this->messagingStatuses[$channel]['api_available'] ?? false) && ($this->messagingStatuses[$channel]['manual_available'] ?? false))
                                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">({{ __('envío manual') }})</span>
                                                @endif
                                            @endif
                                        </span>
                                    </label>
                                @endif
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
                        <p class="text-xs text-zinc-500">{{ $this->selectedCourseName ?? '' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showNotificationModal', false)">{{ __('Cancelar') }}</flux:button>
                @if(in_array('whatsapp', $this->notifForm['channels']))
                    <flux:button variant="subtle" icon="document-text" wire:click="saveNotificationThenWhatsApp">
                        {{ __('Guardar + WhatsApp') }}
                    </flux:button>
                @elseif(in_array('telegram', $this->notifForm['channels']) && ($this->messagingStatuses['telegram']['api_available'] ?? false))
                    <flux:button variant="subtle" icon="paper-airplane" wire:click="saveNotificationThenTelegram">
                        {{ __('Guardar + Telegram') }}
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
                    <flux:label>{{ __('Tipo de acción') }}</flux:label>
                    <flux:select wire:model="interventionForm.action_type" :placeholder="__('Seleccione...')">
                        @foreach($this->actionTypes[$this->category === 'academicas' ? 'academico' : ($this->category === 'comportamentales' ? 'comportamental' : 'asistencia')] as $action)
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

                @if($this->category === 'academicas' && $this->selectedSubjectId)
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <a href="{{ route('admin.summaries.gradebook.index') }}" wire:navigate class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 flex items-center gap-2">
                            <flux:icon.book-open-text class="size-4" />
                            {{ __('Ir al Libro de Calificaciones') }}
                        </a>
                    </div>
                @endif
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
                        <li>• Datos del estudiante, representante, curso, docente y materia</li>
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

    {{-- ==================== MODAL: OBSERVACIÓN DE CLASE ==================== --}}
    <flux:modal wire:model="showClassObservationModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Registrar Observación de Clase') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('Registre novedades y observaciones de la clase') }}</flux:text>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Fecha') }}</flux:label>
                    <flux:input type="date" wire:model="classObservationForm.observation_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tema de clase') }}</flux:label>
                    <flux:input wire:model="classObservationForm.classtopic" :placeholder="__('Tema tratado en clase...')" />
                    <flux:error name="classObservationForm.classtopic" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Observación') }}</flux:label>
                    <flux:textarea wire:model="classObservationForm.observation" rows="3" :placeholder="__('Describa la observación de la clase...')" />
                    <flux:error name="classObservationForm.observation" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Class Observation') }}</flux:label>
                    <flux:textarea wire:model="classObservationForm.class_observation" rows="3" :placeholder="__('Observación detallada del desarrollo de la clase...')" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Novedad') }}</flux:label>
                    <flux:textarea wire:model="classObservationForm.novedad" rows="3" :placeholder="__('Registre aquí cualquier novedad o incidente ocurrido durante la clase...')" />
                    <flux:text variant="subtle" class="text-xs">{{ __('Espacio para registrar novedades escritas del día') }}</flux:text>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showClassObservationModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="saveClassObservation">{{ __('Guardar observación') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('whatsapp-send', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;

                const targets = Array.isArray(data?.wa) ? data.wa : [data?.wa].filter(Boolean);

                targets.forEach((wa) => {
                    if (wa) {
                        window.open(wa, '_blank');
                    }
                });

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
