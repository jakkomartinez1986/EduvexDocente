<?php

namespace App\Models\Incidents;

use App\Models\StudentManagement\Academics\AcademicNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannel extends Model
{
    protected $fillable = [
        'notification_id', 'channel', 'status',
        'sent_at', 'printed_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_id' => 'integer',
            'sent_at' => 'datetime',
            'printed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AcademicNotification::class, 'notification_id');
    }
}
