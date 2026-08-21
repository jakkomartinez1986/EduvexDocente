<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Compromiso {{ $letter->code }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #212529; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #0a2c5e; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 16pt; color: #0a2c5e; margin: 0; text-transform: uppercase; }
        .header h2 { font-size: 11pt; font-weight: 400; margin: 2px 0 0; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; }
        .meta-item { font-size: 10pt; }
        .meta-item strong { display: block; font-size: 8pt; color: #6c757d; text-transform: uppercase; }
        .body { font-size: 11pt; text-align: justify; }
        .compromisos { margin: 14px 0; padding: 12px; border: 1px solid #dee2e6; }
        .footer { margin-top: 28px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; border-top: 1px solid #dee2e6; padding-top: 16px; }
        .firma { text-align: center; }
        .firma .line { border-bottom: 1px solid #000; height: 36px; margin-bottom: 4px; }
        .firma p { font-size: 9pt; font-weight: 600; margin: 0; }
    </style>
</head>
<body>
    @php
        $student = $letter->student;
        $teacher = $letter->teacher;
        $rep = $letter->representative;
    @endphp

    <div class="header">
        <h1>{{ $school->name_school ?? 'UNIDAD EDUCATIVA' }}</h1>
        <h2>ACTA DE COMPROMISO</h2>
    </div>

    <div class="meta">
        <div class="meta-item">
            <strong>Código</strong>
            <span>{{ $letter->code }}</span>
        </div>
        <div class="meta-item">
            <strong>No.</strong>
            <span>{{ $letter->sequential_number }}</span>
        </div>
        <div class="meta-item">
            <strong>Fecha</strong>
            <span>{{ $letter->date?->format('d/m/Y') }}</span>
        </div>
        <div class="meta-item">
            <strong>Estudiante</strong>
            <span>{{ $student?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Representante</strong>
            <span>{{ $rep?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Grado / Curso</strong>
            <span>{{ $letter->grade?->grade_name ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Materia</strong>
            <span>{{ $letter->subject?->subject_name ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Docente</strong>
            <span>{{ $teacher?->user?->fullname ?? '-' }}</span>
        </div>
    </div>

    <div class="body">
        <p>Por medio de la presente, el estudiante <strong>{{ $student?->user?->fullname ?? '-' }}</strong> y su representante <strong>{{ $rep?->user?->fullname ?? '-' }}</strong>, se comprometen a cumplir con lo siguiente:</p>

        <div class="compromisos">
            {!! nl2br(e($letter->commitments)) !!}
        </div>

        <p>El incumplimiento de los compromisos aquí establecidos dará lugar a las acciones disciplinarias correspondientes según el Código de Convivencia Institucional.</p>
    </div>

    <div class="footer">
        <div class="firma">
            <div class="line"></div>
            <p>DOCENTE</p>
        </div>
        <div class="firma">
            <div class="line"></div>
            <p>ESTUDIANTE</p>
        </div>
        <div class="firma">
            <div class="line"></div>
            <p>REPRESENTANTE</p>
        </div>
    </div>

    <div style="margin-top: 16px; font-size: 9pt; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 8px;">
        <p>Estado: <strong>{{ strtoupper($letter->status) }}</strong></p>
        @if($letter->signed_at)
            <p>Firmado el: {{ $letter->signed_at->format('d/m/Y') }}</p>
        @endif
    </div>
</body>
</html>
