<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Calificaciones</title>
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 4px; }
        .header table { width: 100%; }
        .header td { vertical-align: middle; }
        .header-logo { width: 50px; }
        .header-logo img { max-height: 42px; max-width: 42px; }
        .header-center { text-align: center; }
        .header-center h1 { font-size: 11px; font-weight: bold; margin: 0 0 1px 0; text-transform: uppercase; }
        .header-center p { font-size: 8px; margin: 0; color: #333; }
        .title { text-align: center; font-size: 10px; font-weight: bold; margin: 4px 0; text-transform: uppercase; }
        .info-section { margin-bottom: 3px; font-size: 7px; }
        .info-section table { width: 100%; }
        .info-section td { padding: 0 3px; }
        .info-label { font-weight: bold; width: 80px; }
        .student-info { margin-bottom: 3px; padding: 4px 8px; background-color: #f3f4f6; border-radius: 4px; border: 1px solid #d1d5db; }
        .student-info table { width: 100%; }
        .student-info td { padding: 1px 3px; font-size: 7px; }
        .student-info .label { font-weight: bold; width: 70px; }
        .section-label { font-size: 8px; font-weight: bold; margin: 5px 0 2px 0; text-transform: uppercase; color: #1e40af; border-bottom: 1px solid #1e40af; padding-bottom: 1px; }
        .matrix-table { width: 100%; border-collapse: collapse; font-size: 7.5px; border: 1px solid #d1d5db; }
        .matrix-table th, .matrix-table td { border: 1px solid #d1d5db; padding: 2px 5px; text-align: center; vertical-align: middle; }
        .matrix-table th { background-color: #1e40af; color: #fff; font-weight: bold; font-size: 7.5px; }
        .matrix-table .name-col { text-align: left; min-width: 120px; }
        .matrix-table .avg-col { font-weight: bold; background-color: #f3f4f6; color: #1e40af; }
        .matrix-table .status-col { font-weight: bold; }
        .matrix-table .qual-letter { font-weight: bold; font-size: 10px; color: #1e40af; }
        .matrix-table .qual-obs { font-size: 6px; color: #555; text-align: left; }
        .matrix-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .status-aprobado { color: #16a34a; font-weight: bold; }
        .status-supletorio { color: #dc2626; font-weight: bold; }
        .status-reprobado { color: #dc2626; font-weight: bold; }
        .summary-row { background-color: #dbeafe !important; font-weight: bold; }
        .summary-row td { border-top: 2px solid #1e40af !important; }
        .signatures { margin-top: 20px; page-break-inside: avoid; }
        .signatures table { width: 33%; }
        .signatures td { text-align: center; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; font-weight: bold; font-size: 8px; }
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
        .page-content { margin-bottom: 25px; }
    </style>
</head>
<body>
    @php
        $qualObs = [
            'S' => 'Transforma los desacuerdos en oportunidades de crecimiento y cooperación.',
            'F' => 'Demuestra habilidades para llegar a acuerdos y asumir compromisos.',
            'O' => 'Muestra limitaciones para llegar a acuerdos y asumir compromisos.',
            'N' => 'Requiere acompañamiento comportamental.',
        ];
        $qualObsLabel = [
            'S' => 'Siempre',
            'F' => 'Frecuentemente',
            'O' => 'Ocasionalmente',
            'N' => 'Nunca',
        ];
    @endphp

    @foreach($studentsData as $sIdx => $sData)
        <div class="page">
            <div class="header">
                <table>
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
            </div>

            <div class="title">{{ __('REPORTE DE CALIFICACIONES') }}</div>

            <div class="info-section">
                <table>
                    <tr>
                        <td class="info-label">{{ __('Trimestre:') }}</td>
                        <td style="font-weight:bold;">{{ $trimesterName }}</td>
                        <td class="info-label">{{ __('Grado / Curso:') }}</td>
                        <td>{{ $gradeName }}</td>
                        <td class="info-label">{{ __('Jornada:') }}</td>
                        <td>{{ $shiftName }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">{{ __('Ano Lectivo:') }}</td>
                        <td>{{ $yearName }}</td>
                        <td class="info-label">{{ __('Tutor(a):') }}</td>
                        <td>{{ $teacherName }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>

            <div class="student-info">
                <table>
                    <tr>
                        <td class="label">{{ __('Estudiante:') }}</td>
                        <td style="font-weight:bold;">{{ $sData['name'] }}</td>
                        <td class="label">{{ __('Codigo:') }}</td>
                        <td>{{ $sData['student_code'] }}</td>
                    </tr>
                </table>
            </div>

            {{-- ═══════ TABLA CUANTITATIVAS ═══════ --}}
            @php
                $quantSubjects = collect($sData['subjects'])->filter(fn ($s) => !($s['isQual'] ?? false));
            @endphp

            @if($quantSubjects->count() > 0)
                <div class="section-label">{{ __('Asignaturas') }}</div>
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="name-col">{{ __('Asignatura') }}</th>
                            @foreach($periodsToShow as $period)
                                <th>{{ $period->trimester_name }}</th>
                            @endforeach
                            <th class="avg-col">{{ __('Promedio') }}</th>
                            <th>{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quantSubjects as $subjId => $subject)
                            @php
                                $validTotals = array_filter($subject['trimesters'], fn ($v) => $v !== null);
                                $avg = count($validTotals) > 0
                                    ? round(array_sum($validTotals) / count($validTotals), 2)
                                    : null;
                                $statusText = $avg === null ? '—' : ($avg >= 7 ? 'Aprobado' : ($avg >= 5 ? 'Supletorio' : 'Reprobado'));
                                $statusClass = $avg === null ? '' : ($avg >= 7 ? 'status-aprobado' : ($avg >= 5 ? 'status-supletorio' : 'status-reprobado'));
                            @endphp
                            <tr>
                                <td class="name-col" style="font-weight:bold;">{{ $subject['name'] }}</td>
                                @foreach($subject['trimesters'] as $tTotal)
                                    <td>{{ $tTotal !== null ? number_format($tTotal, 2) : '—' }}</td>
                                @endforeach
                                <td class="avg-col">{{ $avg !== null ? number_format($avg, 2) : '—' }}</td>
                                <td class="status-col {{ $statusClass }}">{{ $statusText }}</td>
                            </tr>
                        @endforeach
                        @php
                            $allAvgs = [];
                            $periodAvgs = array_fill(0, count($periodsToShow), []);
                            foreach ($quantSubjects as $subject) {
                                foreach ($subject['trimesters'] as $pIdx => $tTotal) {
                                    if ($tTotal !== null) $periodAvgs[$pIdx][] = $tTotal;
                                }
                                $vt = array_filter($subject['trimesters'], fn ($v) => $v !== null);
                                if (count($vt) > 0) $allAvgs[] = round(array_sum($vt) / count($vt), 2);
                            }
                            $generalAvg = count($allAvgs) > 0 ? round(array_sum($allAvgs) / count($allAvgs), 2) : null;
                            $generalStatus = $generalAvg === null ? '—' : ($generalAvg >= 7 ? 'Aprobado' : ($generalAvg >= 5 ? 'Supletorio' : 'Reprobado'));
                            $generalStatusClass = $generalAvg === null ? '' : ($generalAvg >= 7 ? 'status-aprobado' : ($generalAvg >= 5 ? 'status-supletorio' : 'status-reprobado'));
                        @endphp
                        <tr class="summary-row">
                            <td class="name-col">{{ __('Promedio General') }}</td>
                            @foreach($periodAvgs as $pTotals)
                                @php $pAvg = count($pTotals) > 0 ? round(array_sum($pTotals) / count($pTotals), 2) : null; @endphp
                                <td>{{ $pAvg !== null ? number_format($pAvg, 2) : '—' }}</td>
                            @endforeach
                            <td class="avg-col">{{ $generalAvg !== null ? number_format($generalAvg, 2) : '—' }}</td>
                            <td class="status-col {{ $generalStatusClass }}">{{ $generalStatus }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            {{-- ═══════ TABLA CUALITATIVAS ═══════ --}}
            @php
                $qualSubjects = collect($sData['subjects'])->filter(fn ($s) => $s['isQual'] ?? false);
            @endphp

            @if($qualSubjects->count() > 0)
                <div class="section-label" style="margin-top:6px;">{{ __('Asignaturas Cualitativas') }}</div>
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="name-col">{{ __('Asignatura') }}</th>
                            @foreach($periodsToShow as $period)
                                <th>{{ $period->trimester_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($qualSubjects as $subjId => $subject)
                            <tr>
                                <td class="name-col" style="font-weight:bold;">{{ $subject['name'] }}</td>
                                @foreach($subject['trimesters'] as $tData)
                                    @php
                                        $gradeLetter = is_array($tData) ? ($tData['grade'] ?? null) : $tData;
                                        $obsLetter = is_array($tData) ? ($tData['obs'] ?? '') : '';
                                        $obsText = $obsLetter && isset($qualObs[$obsLetter]) ? $qualObs[$obsLetter] : '';
                                    @endphp
                                    <td>
                                        @if($gradeLetter)
                                            <div class="qual-letter">{{ $gradeLetter }}</div>
                                            @if($obsLetter)
                                                <div class="qual-obs"><strong>{{ $qualObsLabel[$obsLetter] ?? $obsLetter }}:</strong> {{ $obsText }}</div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="signatures">
                <table>
                    <tr>
                        <td>
                            <div class="line">{{ $teacherName }}</div>
                            <div>{{ __('Tutor(a)') }}</div>
                        </td>
                    </tr>
                </table>
            </div>

          
            <div class="footer-fixed">
                    Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $trimesterName }} · {{ $gradeName }} · {{ $shiftName }} 
            </div>
        </div>
    @endforeach
</body>
</html>
