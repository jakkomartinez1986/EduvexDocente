<?php

namespace App\Services\Messaging;

use App\Models\Identity\Users\Representative;
use App\Models\StudentManagement\Academics\AcademicNotification;

/**
 * Single source of truth for notification message texts shared by
 * API sending and manual (wa.me) sending, so every channel carries
 * exactly the same text.
 */
class NotificationMessageBuilder
{
    /**
     * The exact WhatsApp message text previously generated inline by the
     * incidents components.
     */
    public function whatsappMessage(AcademicNotification $notification, ?Representative $representative): string
    {
        $lines = [];

        $representativeName = $representative !== null ? data_get($representative, 'user.full_name') : null;

        $lines[] = ($representativeName ? 'Estimado(a) '.$representativeName : 'Estimado(a) representante').':';
        $lines[] = '';
        $lines[] = 'Le compartimos la notificación '.$notification->code.' correspondiente al estudiante '.(data_get($notification, 'student.user.fullname') ?? '-').'.';
        $lines[] = '';
        $lines[] = 'Adjunto encontrará el documento PDF con el detalle.';

        return implode("\n", $lines);
    }
}
