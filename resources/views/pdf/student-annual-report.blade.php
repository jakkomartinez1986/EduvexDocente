<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Anual de Calificaciones</title>
    <style>
        body { font-family: sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; line-height: 1.3; }

        .header-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 3px; }
        .header-table td { vertical-align: middle; border: none; padding: 0; }
        .header-logo { width: 40px; }
        .header-logo img { max-height: 34px; max-width: 34px; }
        .header-center { text-align: center; }
        .header-center h1 { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .header-center p { font-size: 7px; color: #333; }

        .title { text-align: center; font-size: 10px; font-weight: bold; margin: 4px 0; text-transform: uppercase; }

        .student-info { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .student-info td { border: 0.5px solid #ccc; padding: 2px 4px; font-size: 7.5px; }
        .student-info .label { font-weight: bold; width: 55px; }

        .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; margin: 6px 0 2px 0; padding: 2px 6px; color: #fff; }
        .section-title.blue { background-color: #1e40af; }
        .section-title.violet { background-color: #6d28d9; }
        .section-title.green { background-color: #166534; }
        .section-title.red { background-color: #991b1b; }

        .grades-table { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 6px; }
        .grades-table th, .grades-table td { border: 0.5px solid #888; padding: 2px 4px; text-align: center; vertical-align: middle; }
        .grades-table th { background-color: #e5e7eb; font-weight: bold; font-size: 7px; }
        .grades-table .name-col { text-align: left; }
        .grades-table .bold { font-weight: bold; }
        .grades-table .avg-row td { background-color: #dbeafe; font-weight: bold; border-top: 1.5px solid #000; }
        .grades-table tbody tr:nth-child(even) { background-color: #f9fafb; }

        .qual-table { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 6px; }
        .qual-table th, .qual-table td { border: 0.5px solid #888; padding: 2px 4px; text-align: center; vertical-align: middle; }
        .qual-table th { background-color: #ede9fe; font-weight: bold; font-size: 7px; }
        .qual-table .name-col { text-align: left; }
        .qual-table tbody tr:nth-child(even) { background-color: #f5f3ff; }

        .behavior-table { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 4px; }
        .behavior-table th, .behavior-table td { border: 0.5px solid #888; padding: 2px 4px; text-align: center; vertical-align: top; }
        .behavior-table th { background-color: #fee2e2; font-weight: bold; font-size: 7px; }
        .behavior-table .name-col { text-align: left; }

        .attend-table { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-bottom: 6px; }
        .attend-table th, .attend-table td { border: 0.5px solid #888; padding: 2px 4px; text-align: center; }
        .attend-table th { background-color: #fef3c7; font-weight: bold; font-size: 7px; }

        .legend { font-size: 6px; color: #666; margin: 2px 0 4px 0; }

        .signature-table { width: 40%; border-collapse: collapse; margin-top: 20px; margin-left: auto; margin-right: 0; }
        .signature-table td { border: none; text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-line { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; font-size: 8px; }
        .sig-label { font-size: 6.5px; color: #333; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-logo">
                @if ($school && $school->report_logo_path)
                    <img src="{{ asset('storage/' . $school->report_logo_path) }}" alt="Logo">
                @endif
            </td>
            <td class="header-center">
                <h1>{{ $school->name_school ?? 'UNIDAD EDUCATIVA' }}</h1>
                <p>{{ trim(($school->location ?? '') . ' ' . ($school->address ?? '')) }}</p>
                @if($school->distrit)
                    <p>{{ $school->distrit }}</p>
                @endif
            </td>
            <td class="header-logo" style="text-align:right;">
                @if ($school && $school->logo_path)
                    <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <div class="title">{{ __('REPORTE ANUAL DE CALIFICACIONES') }}</div>

    <table class="student-info">
        <tr>
            <td class="label">Estudiante:</td>
            <td style="width:30%;">{{ $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')) }}</td>
            <td class="label">Código:</td>
            <td style="width:12%;">{{ $student->student_code }}</td>
            <td class="label">Grado/Curso:</td>
            <td style="width:12%;">{{ $gradeName }}</td>
            <td class="label">Jornada:</td>
            <td style="width:10%;">{{ $shiftName }}</td>
            <td class="label">Año:</td>
            <td style="width:10%;">{{ $yearName }}</td>
        </tr>
    </table>

    @php
        $numPeriods = $periods->count();
        $romanNums = ['I', 'II', 'III'];
    @endphp

    {{-- ASIGNATURAS CUANTITATIVAS --}}
    <div class="section-title blue">{{ __('ASIGNATURAS CUANTITATIVAS') }}</div>

    <table class="grades-table">
        <thead>
            <tr>
                <th style="width:22%; text-align:left;">ASIGNATURA</th>
                @foreach($periods as $period)
                    <th>{{ $romanNums[$loop->index] }}° TRIM.</th>
                @endforeach
                @if($numPeriods < 3)
                    @for($i = $numPeriods; $i < 3; $i++)
                        <th>—</th>
                    @endfor
                @endif
                <th>PROM. ANUAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subjectsData as $subject)
                <tr>
                    <td class="name-col">{{ $subject['name'] }}</td>
                    @for($i = 0; $i < 3; $i++)
                        <td>{{ isset($subject['periods'][$i]['total']) ? number_format($subject['periods'][$i]['total'], 2) : '—' }}</td>
                    @endfor
                    <td class="bold">{{ $subject['annual'] !== null ? number_format($subject['annual'], 2) : '—' }}</td>
                </tr>
            @endforeach
            @php
                $allAnnuals = array_column(array_filter($subjectsData, fn ($s) => $s['annual'] !== null), 'annual');
                $grandAvg = count($allAnnuals) > 0 ? round(array_sum($allAnnuals) / count($allAnnuals), 2) : null;
            @endphp
            <tr class="avg-row">
                <td class="name-col">PROMEDIO GENERAL</td>
                @for($i = 0; $i < 3; $i++)
                    @php
                        $periodTotals = [];
                        foreach ($subjectsData as $s) {
                            if (isset($s['periods'][$i]['total']) && $s['periods'][$i]['total'] !== null) {
                                $periodTotals[] = $s['periods'][$i]['total'];
                            }
                        }
                        $periodAvg = count($periodTotals) > 0 ? round(array_sum($periodTotals) / count($periodTotals), 2) : null;
                    @endphp
                    <td>{{ $periodAvg !== null ? number_format($periodAvg, 2) : '—' }}</td>
                @endfor
                <td class="bold">{{ $grandAvg !== null ? number_format($grandAvg, 2) : '—' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ASIGNATURAS CUALITATIVAS + EVALUACIÓN COMPORTAMENTAL --}}
    @php
        $classroomSupport = null;
        $careerGuidance = null;
        $readingPromotion = null;
        foreach ($qualSubjectsData as $qs) {
            if ($qs['type'] === 'classroom_support') {
                $classroomSupport = $qs;
            } elseif ($qs['type'] === 'career_guidance') {
                $careerGuidance = $qs;
            } elseif ($qs['type'] === 'reading_promotion') {
                $readingPromotion = $qs;
            }
        }
    @endphp

    <div class="section-title violet">{{ __('ASIGNATURAS CUALITATIVAS Y EVALUACIÓN COMPORTAMENTAL') }}</div>

    {{-- Tabla resumen de calificaciones cualitativas por asignatura --}}
    @if(count($qualSubjectsData) > 0)
        <table class="qual-table">
            <thead>
                <tr>
                    <th style="width:22%; text-align:left;">ASIGNATURA</th>
                    @foreach($periods as $period)
                        <th>{{ $romanNums[$loop->index] }}° TRIM.</th>
                    @endforeach
                    @if($numPeriods < 3)
                        @for($i = $numPeriods; $i < 3; $i++)
                            <th>—</th>
                        @endfor
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($qualSubjectsData as $qualSubject)
                    <tr>
                        <td class="name-col">{{ $qualSubject['name'] }}</td>
                        @for($i = 0; $i < 3; $i++)
                            <td style="font-weight:bold;">{{ isset($qualSubject['periods'][$i]) ? $qualSubject['periods'][$i]['letter'] : '—' }}</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legend">
            A+ = Excelente (35-36) · A- = Excelente (33-34) · B+ = Bueno (30-32) · B- = Bueno (27-29) · C+ = Regular (20-26) · C- = Regular (18-19) · D+ = En proceso (15-17) · D- = En proceso (13-14) · E+ = Incipiente (11-12) · E- = Incipiente (0-10)
        </div>
    @endif

    <div class="legend">
        S = Siempre (4) · F = Frecuentemente (3) · O = Ocasionalmente (2) · N = Nunca (1)
    </div>

    {{-- ASISTENCIA --}}
    <div class="section-title green">{{ __('ASISTENCIA') }}</div>

    <table class="attend-table">
        <thead>
            <tr>
                <th style="width:22%; text-align:left;">DETALLE</th>
                <th>FALTAS JUSTIFICADAS</th>
                <th>FALTAS INJUSTIFICADAS</th>
                <th>TOTAL FALTAS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align:left; font-weight:bold;">TOTAL ANUAL</td>
                <td>{{ $attendanceSummary['justified'] }}</td>
                <td>{{ $attendanceSummary['unjustified'] }}</td>
                <td style="font-weight:bold;">{{ $attendanceSummary['total'] }}</td>
            </tr>
        </tbody>
    </table>

    {{-- FIRMA --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-line">{{ $teacherName }}</div>
                <div class="sig-label">{{ __('Tutor(a)') }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-fixed">
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $yearName }} · {{ $student->student_code }}
    </div>
</body>
</html>
