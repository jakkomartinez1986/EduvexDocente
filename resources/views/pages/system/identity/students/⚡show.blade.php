<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\School;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Estudiante')] class extends Component {
    public int $studentId;

    public function mount(int $id): void
    {
        $this->studentId = $id;
    }

    public function getStudentProperty(): Student
    {
        return Student::query()
            ->with(['user', 'representatives.user', 'enrollments.grade.nivel.shift'])
            ->findOrFail($this->studentId);
    }

    public function getSchoolProperty(): ?School
    {
        return School::where('status', 1)->first();
    }

    public function getActiveYearProperty()
    {
        return app(AcademicYearService::class)->getActiveYear();
    }

    public function getEnrollmentProperty(): ?StudentEnrollment
    {
        $yearId = app(AcademicYearService::class)->getActiveYearId();
        return $this->student->enrollments()
            ->where('year_id', $yearId)
            ->with('grade.nivel.shift')
            ->first();
    }

    public function getInitialsProperty(): string
    {
        $name = $this->student->user?->name ?? '';
        $lastname = $this->student->user?->lastname ?? '';
        $i1 = mb_strtoupper(mb_substr($lastname, 0, 1));
        $i2 = mb_strtoupper(mb_substr($name, 0, 1));
        return $i1 . $i2;
    }

    public function getQrSvgProperty(): string
    {
        $school = $this->school;
        $year = $this->activeYear;
        $enrollment = $this->enrollment;
        $gradeName = $enrollment ? trim(($enrollment->grade->grade_name ?? '') . ' ' . ($enrollment->grade->section ?? '')) : '-';
        $schoolName = $school?->name_school ?? 'Institución Educativa';
        $yearName = $year?->year_name ?? '-';

        $data = $schoolName . ' | ' . $this->student->student_code . ' | ' . $this->student->user?->fullname . ' | ' . $gradeName . ' | ' . $yearName;

        return (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
            'outputBase64' => false,
            'drawLightModules' => false,
            'moduleValues' => [
                'light' => '#ffffff',
                'dark'  => '#374151',
            ],
        ])))->render($data);
    }
}; ?>
<style>
    .carnet-print-wrapper { display: flex; gap: 1.2rem; flex-wrap: wrap; justify-content: center; }
    .carnet-print-side { width: 325px; }
    .carnet-card { width: 100%; aspect-ratio: 85.6 / 54; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); position: relative; border: 1px solid #d1d5db; background: #fff; }
    .carnet-front { display: flex; flex-direction: column; padding: 8px 10px; height: 100%; position: relative; background: #ffffff; }
    .carnet-front::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #374151; }
    .cf-badge { position: absolute; top: 5px; right: 5px; background: #f3f4f6; color: #374151; padding: 1px 5px; border-radius: 10px; font-size: 5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 2px; border: 0.5px solid #d1d5db; }
    .cf-header { display: flex; align-items: center; gap: 5px; margin-bottom: 3px; padding-top: 1px; }
    .cf-logo { width: 24px; height: 24px; border-radius: 5px; background: #374151; display: flex; align-items: center; justify-content: center; color: white; font-weight: 900; font-size: 7px; flex-shrink: 0; overflow: hidden; }
    .cf-logo img { width: 100%; height: 100%; object-fit: contain; }
    .cf-inst { flex: 1; }
    .cf-inst .nm { font-weight: 700; font-size: 8px; color: #111827; line-height: 1.1; }
    .cf-inst .sb { font-size: 5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; color: #6b7280; }
    .cf-type { background: #374151; color: white; padding: 1px 4px; border-radius: 8px; font-size: 5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
    .cf-body { display: flex; gap: 6px; flex: 1; align-items: center; }
    .cf-photo { width: 42px; height: 42px; border-radius: 6px; background: #6b7280; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; flex-shrink: 0; border: 1.5px solid white; overflow: hidden; }
    .cf-photo img { width: 100%; height: 100%; object-fit: cover; }
    .cf-data { flex: 1; display: flex; flex-direction: column; gap: 0px; }
    .cf-data .nm { font-weight: 700; font-size: 8px; color: #111827; line-height: 1.1; margin-bottom: 1px; }
    .cf-data .fld { display: flex; align-items: baseline; gap: 2px; font-size: 5.5px; color: #6b7280; line-height: 1.3; }
    .cf-data .fld .lb { font-weight: 600; text-transform: uppercase; font-size: 4.5px; letter-spacing: 0.2px; color: #9ca3af; min-width: 28px; }
    .cf-data .fld .vl { font-weight: 500; color: #111827; }
    .cf-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 3px; border-top: 0.5px solid #f3f4f6; font-size: 4.5px; color: #9ca3af; }
    .cf-footer .sig { display: flex; align-items: center; gap: 4px; }
    .cf-footer .sig .ln { width: 35px; border-bottom: 0.5px dashed #d1d5db; }
    .carnet-back { display: flex; flex-direction: column; justify-content: space-between; height: 100%; padding: 8px 10px; position: relative; background: #ffffff; }
    .carnet-back::before { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: #374151; }
    .cb-header { display: flex; align-items: center; gap: 5px; }
    .cb-logo { width: 20px; height: 20px; border-radius: 4px; background: #374151; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 6px; overflow: hidden; }
    .cb-logo img { width: 100%; height: 100%; object-fit: contain; }
    .cb-title { font-weight: 700; font-size: 7px; color: #111827; }
    .cb-title small { font-weight: 400; font-size: 4.5px; color: #6b7280; display: block; }
    .cb-qr-section { display: flex; align-items: center; gap: 8px; justify-content: center; flex: 1; }
    .cb-qr { width: 52px; height: 52px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; padding: 3px; }
    .cb-qr svg { width: 100%; height: 100%; }
    .cb-qr-info { display: flex; flex-direction: column; gap: 0px; font-size: 5px; color: #6b7280; }
    .cb-qr-info .ql { font-weight: 700; font-size: 4.5px; text-transform: uppercase; letter-spacing: 0.3px; color: #111827; }
    .cb-qr-info .qd { font-weight: 500; color: #111827; font-size: 6px; }
    .cb-legend { background: #f9fafb; border: 0.5px dashed #d1d5db; border-radius: 4px; padding: 3px 5px; text-align: center; }
    .cb-legend .tx { font-size: 4.5px; font-weight: 600; color: #111827; }
    .cb-legend .tx small { font-weight: 400; color: #6b7280; display: block; font-size: 4px; margin-top: 0.5px; }
    .cb-footer { display: flex; justify-content: space-between; align-items: center; font-size: 4px; color: #9ca3af; margin-top: auto; padding-top: 2px; border-top: 0.5px solid #f3f4f6; }
</style>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Estudiante') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del estudiante') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('system.identity.students.edit', $this->studentId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('system.identity.students.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.students.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Estudiantes') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->student->student_code }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <flux:avatar src="{{ $this->student->user?->defaultUserPhotoUrl() }}" size="size-20" class="mx-auto mb-4" />
                <flux:heading size="lg">{{ $this->student->user?->fullname ?? '-' }}</flux:heading>
                <flux:text class="text-zinc-500">{{ $this->student->user?->email }}</flux:text>
                <div class="mt-3">
                    <flux:badge color="{{ $this->student->user?->status === 1 ? 'green' : 'red' }}">
                        {{ $this->student->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
                <div class="mt-2">
                    <flux:badge color="blue">{{ $this->student->student_code }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Personal') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Apellido') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->lastname ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('DNI') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->dni ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Telefono') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Celular') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->cellphone ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Direccion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->user?->address ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Estudiante') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Codigo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->student_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Matricula') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->enrollment_date?->format('d/m/Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Nacimiento') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->birth_date?->format('d/m/Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Tipo de Sangre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->blood_type ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Contacto de Emergencia') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->emergency_contact ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Informacion Medica') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->medical_info ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->student->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            @if ($this->student->representatives->count())
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:heading size="md" class="mb-4">{{ __('Representantes') }}</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($this->student->representatives as $representative)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50 dark:bg-zinc-800/50">
                                <div class="flex items-start gap-3 mb-3">
                                    <flux:avatar src="{{ $representative->user?->defaultUserPhotoUrl() }}" size="size-12" />
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $representative->user?->fullname ?? '-' }}</div>
                                        @if($representative->relationship)
                                            <div class="mt-0.5"><flux:badge size="xs" color="blue">{{ $representative->relationship }}</flux:badge></div>
                                        @endif
                                    </div>
                                </div>
                                <dl class="space-y-1.5 text-xs">
                                    @if($representative->user?->dni)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('DNI') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $representative->user->dni }}</dd>
                                        </div>
                                    @endif
                                    @if($representative->user?->phone)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('Telefono') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $representative->user->phone }}</dd>
                                        </div>
                                    @endif
                                    @if($representative->user?->cellphone)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('Celular') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $representative->user->cellphone }}</dd>
                                        </div>
                                    @endif
                                    @if($representative->user?->email)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('Email') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100 truncate" title="{{ $representative->user->email }}">{{ $representative->user->email }}</dd>
                                        </div>
                                    @endif
                                    @if($representative->user?->address)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('Direccion') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100 truncate" title="{{ $representative->user->address }}">{{ $representative->user->address }}</dd>
                                        </div>
                                    @endif
                                    @if($representative->occupation)
                                        <div class="flex justify-between">
                                            <dt class="text-zinc-500">{{ __('Ocupacion') }}</dt>
                                            <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $representative->occupation }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Carnet Estudiantil Digital --}}
            @php
                $school = $this->school;
                $year = $this->activeYear;
                $enrollment = $this->enrollment;
                $gradeName = $enrollment ? trim(($enrollment->grade->grade_name ?? '') . ' ' . ($enrollment->grade->section ?? '')) : '-';
                $shiftName = $enrollment->grade->nivel->shift->shift_name ?? '-';
                $yearName = $year?->year_name ?? '-';
                $validUntil = $year?->end_date ? $year->end_date->format('Y') : '-';
                $schoolName = $school?->name_school ?? 'Institución Educativa';
                $schoolAddress = $school?->address ?? '';
                $schoolPhone = $school?->phone ?? '';
                $schoolEmail = $school?->email ?? '';
                $schoolWebsite = $school?->website ?? '';
                $verificationCode = strtoupper($this->student->student_code . '-' . $yearName);
            @endphp
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="md">{{ __('Carnet Estudiantil') }}</flux:heading>
                    {{-- <flux:button href="{{ route('system.identity.students.carnet', $this->studentId) }}" target="_blank" size="sm" variant="primary" icon="printer">
                        {{ __('Imprimir Carnet') }}
                    </flux:button> --}}
                </div>

                <div class="carnet-print-wrapper">
                    {{-- FRONT --}}
                    <div class="carnet-print-side">
                        <div class="carnet-card">
                            <div class="carnet-front">
                                <div class="cf-badge">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                                    {{ $this->student->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                                </div>
                                <div class="cf-header">
                                    <div class="cf-logo">
                                        @if($school && $school->logo_path)
                                            <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo" />
                                        @else
                                            IE
                                        @endif
                                    </div>
                                    <div class="cf-inst">
                                        <div class="nm">{{ $schoolName }}</div>
                                        <div class="sb">Institución Educativa</div>
                                    </div>
                                    <span class="cf-type">Estudiante</span>
                                </div>
                                <div class="cf-body">
                                    <div class="cf-photo">
                                        @if($this->student->user?->profile_photo_path)
                                            <img src="{{ asset('storage/' . $this->student->user->profile_photo_path) }}" alt="Foto" />
                                        @else
                                            {{ $this->initials }}
                                        @endif
                                    </div>
                                    <div class="cf-data">
                                        <div class="nm">{{ $this->student->user?->fullname ?? '-' }}</div>
                                        <div class="fld"><span class="lb">Código</span><span class="vl">{{ $this->student->student_code }}</span></div>
                                        <div class="fld"><span class="lb">Curso</span><span class="vl">{{ $gradeName }}</span></div>
                                        <div class="fld"><span class="lb">Jornada</span><span class="vl">{{ $shiftName }}</span></div>
                                        <div class="fld"><span class="lb">Año lectivo</span><span class="vl">{{ $yearName }}</span></div>
                                        @if($this->student->emergency_contact)
                                            <div class="fld"><span class="lb">Emergencia</span><span class="vl">{{ $this->student->emergency_contact }}</span></div>
                                        @endif
                                    </div>
                                </div>
                                <div class="cf-footer">
                                    <span>Válido hasta: {{ $validUntil }}</span>
                                    <div class="sig"><span>Firma</span><span class="ln"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- BACK --}}
                    <div class="carnet-print-side">
                        <div class="carnet-card">
                            <div class="carnet-back">
                                <div class="cb-header">
                                    <div class="cb-logo">
                                        @if($school && $school->logo_path)
                                            <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo" />
                                        @else
                                            IE
                                        @endif
                                    </div>
                                    <div class="cb-title">
                                        {{ $schoolName }}
                                        <small>Institución Educativa · Carnet Estudiantil</small>
                                    </div>
                                </div>
                                <div class="cb-qr-section">
                                    <div class="cb-qr">
                                        {!! $this->qrSvg !!}
                                    </div>
                                    <div class="cb-qr-info">
                                        <span class="ql">Código de verificación</span>
                                        <span class="qd">{{ $verificationCode }}</span>
                                        <span style="font-size:0.5rem; color:#9ca3af; margin-top:3px;">Certificado digital</span>
                                        <span style="font-size:0.5rem; color:#9ca3af;">Emitido: {{ now()->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                                <div class="cb-legend">
                                    <div class="tx">
                                        EN CASO DE ENCONTRAR, FAVOR DEVOLVER A:
                                        <small>{{ $schoolName }} · Secretaría Académica @if($schoolPhone) · Tel: {{ $schoolPhone }} @endif</small>
                                        @if($schoolEmail || $schoolAddress)
                                            <small>@if($schoolEmail) {{ $schoolEmail }} @endif @if($schoolAddress && $schoolEmail) · @endif @if($schoolAddress) {{ $schoolAddress }} @endif</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="cb-footer">
                                    <div class="flex gap-3">
                                        @if($schoolPhone) <span>Tel: {{ $schoolPhone }}</span> @endif
                                        @if($schoolWebsite) <span>{{ $schoolWebsite }}</span> @endif
                                    </div>
                                    <span>v.1.0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
