<?php

namespace App\Models\Identity\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $student_id
 * @property string|null $occupation
 * @property string|null $relationship
 * @property string|null $work_phone
 * @property string|null $geolocation_info
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id', 'student_id', 'occupation', 'relationship',
    'work_phone', 'geolocation_info',
])]
class Representative extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'student_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->lastname.' '.$this->user?->name ?? '';
    }
}
