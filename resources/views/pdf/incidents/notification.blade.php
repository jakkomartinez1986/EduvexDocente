<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificación {{ $notification->code }}</title>
    <style>
        @page { margin: 15mm 12mm; }
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 10pt; 
            color: #212529; 
            line-height: 1.4; 
            margin: 0; 
            padding: 0; 
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid #0a2c5e; 
            padding-bottom: 6px; 
            margin-bottom: 10px; 
        }
        .header .titulo { 
            text-align: center; 
            flex: 1; 
        }
        .header .titulo h1 { 
            font-size: 13pt; 
            color: #0a2c5e; 
            margin: 0; 
            text-transform: uppercase; 
            font-weight: bold;
        }
        .header .titulo h2 { 
            font-size: 10pt; 
            font-weight: 600; 
            margin: 2px 0 0; 
            text-transform: uppercase;
        }
        .meta { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 4px 12px; 
            margin-bottom: 10px; 
            padding: 6px 10px; 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
        }
        .meta-item { 
            font-size: 9pt; 
        }
        .meta-item strong { 
            display: block; 
            font-size: 7pt; 
            color: #6c757d; 
            text-transform: uppercase; 
            font-weight: bold;
        }
        .body { 
            font-size: 10pt; 
            text-align: justify; 
        }
        .body p {
            margin: 4px 0;
        }
        .motivos { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 2px 16px; 
            margin: 8px 0; 
            padding: 6px 10px; 
            border: 1px solid #dee2e6; 
            background: #fafafa;
            font-size: 9pt;
        }
        .motivos div {
            padding: 1px 0;
        }
        .footer { 
            margin-top: 20px; 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 12px; 
            border-top: 1px solid #dee2e6; 
            padding-top: 12px; 
        }
        .firma { 
            text-align: center; 
        }
        .firma .line { 
            border-bottom: 1px solid #000; 
            height: 30px; 
            margin-bottom: 3px; 
        }
        .firma p { 
            font-size: 8pt; 
            font-weight: 600; 
            margin: 0; 
            text-transform: uppercase;
        }
        .firma small {
            font-size: 7pt;
            font-weight: normal;
        }
        .badge { 
            display: inline-block; 
            padding: 1px 6px; 
            border-radius: 8px; 
            font-size: 8pt; 
            background: #e8f0fe; 
        }
        .fecha {
            text-align: right;
            font-size: 9pt;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    @php
        $student = $notification->student;
        $teacher = $notification->teacher;
    @endphp

    <div class="header">
        <div class="titulo">
            <h1>{{ $school->name_school ?? 'UNIDAD EDUCATIVA' }}</h1>
            <h2>NOTIFICACIÓN AL REPRESENTANTE LEGAL</h2>
        </div>
    </div>

    <div class="meta">
        <div class="meta-item">
            <strong>Fecha Emisión</strong>
            <span>{{ $notification->generated_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
        </div>
        <div class="meta-item">
            <strong>Código</strong>
            <span>{{ $notification->code }}</span>
        </div>
        <div class="meta-item">
            <strong>No.</strong>
            <span>{{ $notification->notification_number }}</span>
        </div>
        <div class="meta-item">
            <strong>Materia / Docente</strong>
            <span>{{ $notification->subject?->subject_name ?? '-' }} / {{ $teacher?->user?->fullname ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Grado / Curso</strong>
            <span>{{ $notification->grade?->grade_name ?? '-' }}</span>
        </div>
        <div class="meta-item">
            <strong>Canales</strong>
            <span>{{ $channels?->pluck('channel')->implode(', ') ?: $notification->channel }}</span>
        </div>
    </div>

    <div class="body">
        <p>Estimado(a) representante del estudiante <strong>{{ $student?->user?->fullname ?? '____________________' }}</strong>:</p>
        <p>Por medio de la presente, y de conformidad al <strong>Reglamento de la Ley Orgánica de Educación Intercultural (Art. 76)</strong> y al código de convivencia institucional, se le notifica sobre las siguientes novedades presentadas por su representado:</p>

        @if($notification->motives)
        <div class="motivos">
            @foreach($notification->motives as $motive)
                <div>☑ {{ $motive }}</div>
            @endforeach
        </div>
        @endif

        @if($notification->observation)
            <p><strong>Observación:</strong> {{ $notification->observation }}</p>
        @endif

        @if($notification->appointment_date)
            <p>Por lo tanto, sírvase <strong>concurrir al plantel</strong> el día <strong>{{ $notification->appointment_date->format('d/m/Y') }}</strong> a las <strong>{{ $notification->appointment_time?->format('H:i') ?? '_________' }}</strong>.</p>
        @endif
    </div>

    <div class="footer">
        <div class="firma">
            <div class="line"></div>
            <p>DOCENTE DE LA MATERIA</p>
        </div>
        <div class="firma">
            <div class="line"></div>
            <p>RECIBÍ CONFORME<br><small>(Representante)</small></p>
        </div>
        <div class="firma">
            <div class="line"></div>
            <p>TUTOR / INSPECTOR</p>
        </div>
    </div>
</body>
</html>