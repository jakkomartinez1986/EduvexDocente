<?php

namespace App\Notifications;

use App\Models\StudentManagement\Academics\AcademicNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AcademicNotificationSent extends Notification
{
    use Queueable;

    public function __construct(
        public AcademicNotification $academicNotification,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'id' => $this->academicNotification->id,
            'code' => $this->academicNotification->code,
            'type' => $this->academicNotification->type,
            'message' => $this->academicNotification->message,
            'teacher_name' => $this->academicNotification->teacher?->user?->fullname ?? '',
            'student_name' => $this->academicNotification->student?->user?->fullname ?? '',
            'appointment_date' => $this->academicNotification->appointment_date?->format('d/m/Y'),
            'appointment_time' => $this->academicNotification->appointment_time?->format('H:i'),
            'generated_date' => $this->academicNotification->generated_date?->format('d/m/Y'),
            'url' => '/system/teacher/notifications',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Notificación Académica — '.$this->academicNotification->code)
            ->line('Estimado(a) representante,')
            ->line('Se le informa sobre una notificación académica del estudiante: '.$this->academicNotification->student?->user?->fullname)
            ->line('Docente: '.$this->academicNotification->teacher?->user?->fullname)
            ->line('Fecha de citación: '.$this->academicNotification->appointment_date?->format('d/m/Y'))
            ->line($this->academicNotification->message)
            ->action('Ver notificación', url('/system/teacher/notifications'))
            ->line('Código: '.$this->academicNotification->code);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
