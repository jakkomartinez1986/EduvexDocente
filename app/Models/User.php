<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'lastname', 'dni', 'phone', 'cellphone',
    'status', 'address', 'profile_photo_path', 'signature_photo_path',
    'email', 'password', 'must_change_password',
])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = strtoupper($value);
    }

    public function setLastnameAttribute(?string $value): void
    {
        $this->attributes['lastname'] = $value ? strtoupper($value) : '';
    }

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['address'] = $value ? strtoupper($value) : null;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value ? preg_replace('/[^0-9+]/', '', $value) : null;
    }

    public function setCellphoneAttribute(?string $value): void
    {
        $this->attributes['cellphone'] = $value ? preg_replace('/[^0-9+]/', '', $value) : null;
    }

    // Verificar si el usuario está activo
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    // Método para enviar notificación de reseteo de contraseña personalizada
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Enviar notificación de verificación de email
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function defaultUserPhotoUrl()
    {

        if (is_null($this->profile_photo_path)) {

            $fotografia = 'https://ui-avatars.com/api/?name='.$this->initials().'&background=6875F5&color=f5f5f5'; // &background=6875F5&color=f5f5f5

        } else {
            $fotografia = 'storage/'.$this->profile_photo_path;
        }

        return $fotografia;
    }

    public function defaultSignaturePhotoUrl()
    {

        if (is_null($this->signature_photo_path)) {

            $signatureohoto = 'https://ui-avatars.com/api/?name=SF&background=6875F5&color=f5f5f5'; // &background=6875F5&color=f5f5f5

        } else {
            $signatureohoto = 'storage/'.$this->signature_photo_path;
        }

        return $signatureohoto;
    }

    public function getFullNameAttribute()
    {
        return $this->lastname.' '.$this->name;
    }

    public function getContactsAttribute()
    {
        return $this->phone.'/'.$this->cellphone;
    }

    public function getStatusUser()
    {

        return match ((int) $this->status) {

            0 => 'Inactivo',
            1 => 'Activo',
            default => 'Desconocido', // Por si en el futuro hay otro valor como 2, 3, etc.
        };
    }
}
