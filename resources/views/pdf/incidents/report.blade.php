<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe {{ $report->code }}</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #212529; line-height: 1.6; }
        .header { text-align: center; border-bottom: 2px solid #0a2c5e; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 16pt; color: #0a2c5e; margin: 0; text-transform: uppercase; }
        .header h2 { font-size: 11pt; font-weight: 400; margin: 2px 0 0; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; }
        .meta-item { font-size: 10pt; }
        .meta-item strong { display: block; font-size: 8pt; color: #6c757d; text-transform: uppercase; }
        .section { margin-bottom: 18px; }
        .section h3 { font-size: 11pt; color: #0a2c5e; border-bottom: 1px solid #dee2e6; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 8px 0; }
        th, td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
        .footer { margin-top: 28px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; border-top: 1px solid #dee2e6; padding-top: 16px; }
        .firma { text-align: center; }
        .firma .line { border-bottom: 1px solid #000; height: 36px; margin-bottom: 4px; }
        .firma p { font-size: 9pt; font-weight: 600; margin: 0; }
    </style>
</head>
<body>
    @php
        $student = $report->student;
        $teacher = $report->teacher;
        $tutor = $report->tutor;
    @endphp

    <div class="header">
        <h1>{{ $school->name_school ?? 'UNIDAD EDUCATIVA' }}</h1>
        <h2>INFORME AL DOCENTE TUTOR</h2>
    </div>

    <div class="meta">
        <div class="meta-item">
            <strong>Código</strong>
            <span>{{ $report->code }}</span>
        </div>
        <div class="meta-item">
            <strong>No. Informe</strong>
            <span>{{ $report->sequential_number }}</span>
        </div>
        <div class="meta-item">
            <strong>Fecha</strong>
            <span>{{ $report->date?->format('d/m/Y') }}</span>
        </div>
        <div class="meta-item">
            <strong>Docente</strong>
            <span>{{ $teacher?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Tutor</strong>
            <span>{{ $tutor?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Estado</strong>
            <span>{{ strtoupper($report->status) }}</span>
        </div>
        <div class="meta-item">
            <strong>Estudiante</strong>
            <span>{{ $student?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Grado / Curso</strong>
            <span>{{ $report->grade?->grade_name ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Materia</strong>
            <span>{{ $report->subject?->subject_name ?? '-' }}</span>
        </div>
    </div>

    @if($notifications->count() > 0)
    <div class="section">
        <h3>Notificaciones Generadas</h3>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha</th>
                    <th>Canales</th>
                    <th>Asistencia Representante</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notifications as $notif)
                <tr>
                    <td>{{ $notif->code }}</td>
                    <td>{{ $notif->generated_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $notif->channels?->pluck('channel')->implode(', ') ?: $notif->channel }}</td>
                    <td>{{ $notif->parent_attended ? 'Sí' : ($notif->parent_attended === false ? 'No' : 'Pendiente') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($letters->count() > 0)
    <div class="section">
        <h3>Actas de Compromiso Generadas</h3>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($letters as $letter)
                <tr>
                    <td>{{ $letter->code }}</td>
                    <td>{{ $letter->date?->format('d/m/Y') }}</td>
                    <td>{{ strtoupper($letter->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($report->conclusion)
    <div class="section">
        <h3>Conclusión</h3>
        <p>{{ $report->conclusion }}</p>
    </div>
    @endif

    <div class="footer">
        <div class="firma">
            <div class="line"></div>
            <p>DOCENTE DE LA MATERIA</p>
        </div>
        <div class="firma">
            <div class="line"></div>
            <p>TUTOR<br><small>(Recibí Conforme)</small></p>
        </div>
    </div>
</body>
</html>
