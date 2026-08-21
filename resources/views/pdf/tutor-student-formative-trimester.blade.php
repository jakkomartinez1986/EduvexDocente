<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Notas Formativas por Trimestre</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .header table {
            width: 100%;
        }
        .header td {
            vertical-align: middle;
        }
        .header-logo {
            width: 60px;
        }
        .header-logo img {
            max-height: 55px;
            max-width: 55px;
        }
        .header-center {
            text-align: center;
        }
        .header-center h1 {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .header-center p {
            font-size: 9px;
            margin: 0;
            color: #333;
        }
        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 8px 0;
            text-transform: uppercase;
        }
        .info-section {
            margin-bottom: 8px;
            font-size: 9px;
        }
        .info-section table {
            width: 100%;
        }
        .info-section td {
            padding: 1px 4px;
        }
        .info-label {
            font-weight: bold;
            width: 110px;
        }
        .student-info {
            margin-bottom: 10px;
            padding: 8px 12px;
            background-color: #f3f4f6;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }
        .student-info table {
            width: 100%;
        }
        .student-info td {
            padding: 2px 4px;
            font-size: 9px;
        }
        .student-info .label {
            font-weight: bold;
            width: 100px;
        }
        .subject-block {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .subject-title {
            font-size: 10px;
            font-weight: bold;
            background-color: #1e40af;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px 4px 0 0;
            margin: 0;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            border: 1px solid #d1d5db;
        }
        .matrix-table th,
        .matrix-table td {
            border: 1px solid #d1d5db;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table th {
            background-color: #e5e7eb;
            font-weight: bold;
        }
        .matrix-table .name-col {
            text-align: left;
            min-width: 110px;
        }
        .matrix-table .block-header-group {
            background-color: #dbeafe;
        }
        .matrix-table .block-activity-header {
            background-color: #eff6ff;
            font-size: 6.5px;
        }
        .matrix-table .block-avg-col {
            font-weight: bold;
            background-color: #f3f4f6;
            font-size: 7px;
        }
        .matrix-table .activity-col {
            font-size: 7px;
            min-width: 25px;
        }
        .matrix-table .formative-col {
            font-weight: bold;
            background-color: #dbeafe;
        }
        .matrix-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures table {
            width: 33%;
        }
        .signatures td {
            text-align: center;
            vertical-align: bottom;
        }
        .signatures .line {
            border-top: 1px solid #000;
            margin: 0 15px;
            padding-top: 4px;
            font-weight: bold;
            font-size: 9px;
        }
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
        .page-content { margin-bottom: 25px; }
    </style>
</head>
<body>
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

    <div class="title">{{ __('REPORTE DE NOTAS FORMATIVAS POR TRIMESTRE') }}</div>

    <div class="info-section">
        <table>
            <tr>
                <td class="info-label">{{ __('Trimestre:') }}</td>
                <td style="font-weight:bold;">{{ $trimesterName }}</td>
                <td class="info-label">{{ __('Grado / Curso:') }}</td>
                <td>{{ $gradeName }}</td>
            </tr>
            <tr>
                <td class="info-label">{{ __('Jornada:') }}</td>
                <td>{{ $shiftName }}</td>
                <td class="info-label">{{ __('Año Lectivo:') }}</td>
                <td>{{ $yearName }}</td>
            </tr>
            <tr>
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
                <td style="font-weight:bold;">{{ $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')) }}</td>
                <td class="label">{{ __('Codigo:') }}</td>
                <td>{{ $student->student_code }}</td>
                <td class="label">{{ __('DNI:') }}</td>
                <td>{{ $student->user->dni ?? '-' }}</td>
            </tr>
        </table>
    </div>

    @php
        $numericSubjects = [];
        $qualitativeSubjects = [];
        $qualKeywords = ['orientacion vocacional', 'ovp', 'acompañamiento integral', 'aiac', 'civica', 'animacion a la lectura', 'animación a la lectura'];
        foreach ($subjectsData as $subjectInfo) {
            $nameLower = strtolower($subjectInfo['name']);
            $isQual = false;
            foreach ($qualKeywords as $kw) {
                if (str_contains($nameLower, $kw)) { $isQual = true; break; }
            }
            if ($isQual) {
                $qualitativeSubjects[] = $subjectInfo;
            } else {
                $numericSubjects[] = $subjectInfo;
            }
        }
    @endphp

    @foreach($numericSubjects as $subjectInfo)
        @php
            $blocks = $subjectInfo['blocks'];
            $blockCount = $blocks->count();
        @endphp

        <div class="subject-block">
            <div class="subject-title">{{ $subjectInfo['name'] }}</div>

            @if($blockCount > 0)
                @php
                    $totalActivityCols = 0;
                    foreach ($blocks as $block) {
                        $totalActivityCols += max($block->activities->count(), 1) + 1;
                    }
                @endphp

                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:22px;">N°</th>
                            <th rowspan="2" class="name-col">{{ __('Estudiante') }}</th>
                            @foreach($blocks as $block)
                                @php
                                    $actCount = $block->activities->count();
                                    $blockColspan = max($actCount, 1) + 1;
                                @endphp
                                <th colspan="{{ $blockColspan }}" class="block-header-group">
                                    {{ $block->name }}
                                    @if($block->internal_percentage)
                                        <div style="font-weight:normal;">({{ $block->internal_percentage }}%)</div>
                                    @endif
                                </th>
                            @endforeach
                            <th rowspan="2" class="formative-col">{{ __('Prom. Formativa') }}</th>
                        </tr>
                        <tr>
                            @foreach($blocks as $block)
                                @if($block->activities->count() > 0)
                                    @foreach($block->activities as $activity)
                                        <th class="block-activity-header" title="{{ $activity->name }}">
                                            {{ Str::limit($activity->name, 6) }}
                                        </th>
                                    @endforeach
                                @else
                                    <th class="block-activity-header">—</th>
                                @endif
                                <th class="block-avg-col">{{ __('Prom.') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $blockAverages = [];
                            foreach ($blocks as $block) {
                                $totalActivities = $block->activities->count();
                                if ($totalActivities === 0) {
                                    $blockAverages[] = null;
                                    continue;
                                }
                                $total = 0;
                                foreach ($block->activities as $activity) {
                                    $grade = $activity->grades->first();
                                    if ($grade && $grade->grade !== null) {
                                        $total += $grade->grade;
                                    }
                                }
                                $blockAverages[] = floor($total / $totalActivities * 100) / 100;
                            }
                            $validAverages = array_values(array_filter($blockAverages, fn ($v) => $v !== null));
                            $formativeAvg = count($validAverages) > 0
                                ? floor(array_sum($validAverages) / count($validAverages) * 100) / 100
                                : null;
                        @endphp
                        <tr>
                            <td>1</td>
                            <td class="name-col">
                                {{ $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')) }}
                                <div style="font-size:6px;color:#666;font-family:monospace;">{{ $student->student_code }}</div>
                            </td>
                            @foreach($blocks as $blockIdx => $block)
                                @foreach($block->activities as $activity)
                                    @php
                                        $grade = $activity->grades->first();
                                        $val = ($grade && $grade->grade !== null) ? $grade->grade : null;
                                    @endphp
                                    <td class="activity-col">{{ $val !== null ? number_format($val, 1) : '—' }}</td>
                                @endforeach
                                @if($block->activities->count() === 0)
                                    <td class="activity-col">—</td>
                                @endif
                                <td class="block-avg-col">{{ $blockAverages[$blockIdx] !== null ? number_format($blockAverages[$blockIdx], 2) : '—' }}</td>
                            @endforeach
                            <td class="formative-col">{{ $formativeAvg !== null ? number_format($formativeAvg, 2) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div style="padding:8px;text-align:center;color:#999;font-size:8px;border:1px solid #d1d5db;border-top:none;">
                    {{ __('Sin bloques de evaluacion registrados.') }}
                </div>
            @endif
        </div>
    @endforeach

    @if(count($qualitativeSubjects) > 0)
        <div style="margin: 6px 0; border-top: 2px solid #1e40af; padding-top: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #1e40af;">
            {{ __('Cualitativas') }}
        </div>

        @foreach($qualitativeSubjects as $subjectInfo)
            @php
                $qualType = $subjectInfo['qualType'];
                $indicators = $subjectInfo['indicators'] ?? collect();
                $grades = $subjectInfo['grades'] ?? collect();
            @endphp

            <div class="subject-block">
                <div class="subject-title">{{ $subjectInfo['name'] }}</div>

                @if($qualType === 'career_guidance' || $qualType === 'classroom_support')
                    @php
                        $sfoLabels = ['S' => 'Siempre', 'F' => 'Frecuentemente', 'O' => 'Ocasionalmente', 'N' => 'Nunca'];
                        $sfoValues = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];
                        if ($qualType === 'career_guidance') {
                            $grouped = $indicators->groupBy('eje');
                        } else {
                            $grouped = collect(['' => $indicators]);
                        }
                        $totalScore = 0;
                        $counted = 0;
                        foreach ($indicators as $ind) {
                            $g = $grades->get($ind->id);
                            if ($g && $g->value && isset($sfoValues[$g->value])) {
                                $totalScore += $sfoValues[$g->value];
                                $counted++;
                            }
                        }
                        $avgScore = $counted > 0 ? $totalScore : null;
                        $qualLetterMap = [
                            'A+' => [35,36], 'A-' => [33,34], 'B+' => [30,32], 'B-' => [27,29],
                            'C+' => [20,26], 'C-' => [18,19], 'D+' => [15,17], 'D-' => [13,14],
                            'E+' => [11,12], 'E-' => [0,10],
                        ];
                        $avgLetter = '—';
                        foreach ($qualLetterMap as $letter => $range) {
                            if ($avgScore !== null && $avgScore >= $range[0] && $avgScore <= $range[1]) {
                                $avgLetter = $letter;
                                break;
                            }
                        }
                    @endphp

                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th style="width:30px;">N°</th>
                                <th style="text-align:left;">Indicador</th>
                                <th style="width:100px;">Calificacion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNum = 1; @endphp
                            @foreach($grouped as $eje => $ejeIndicators)
                                @if($eje !== '')
                                    <tr>
                                        <td colspan="3" style="background-color:#e0e7ff;font-weight:bold;text-align:left;font-size:7px;color:#1e40af;">
                                            {{ $eje }}
                                        </td>
                                    </tr>
                                @endif
                                @foreach($ejeIndicators as $ind)
                                    @php
                                        $g = $grades->get($ind->id);
                                        $val = $g ? $g->value : null;
                                        $label = $val && isset($sfoLabels[$val]) ? $sfoLabels[$val] . ' (' . $val . ')' : '—';
                                    @endphp
                                    <tr>
                                        <td>{{ $rowNum++ }}</td>
                                        <td style="text-align:left;font-size:7px;">{{ $ind->name }}</td>
                                        <td style="font-weight:bold;">{{ $label }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                            <tr style="background-color:#dbeafe;font-weight:bold;">
                                <td colspan="2" style="text-align:right;">Promedio:</td>
                                <td style="color:#1e40af;">{{ $avgLetter }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="font-size:6px;color:#666;margin-top:2px;">
                        S=Siempre(4) · F=Frecuentemente(3) · O=Ocasionalmente(2) · N=Nunca(1) | Suma {{ $counted }} indicadores = {{ $avgScore ?? '—' }} → {{ $avgLetter }}
                    </div>

                @elseif($qualType === 'reading_promotion')
                    @php
                        $readingScores = [1 => 'E-', 2 => 'E+', 3 => 'D-', 4 => 'D+', 5 => 'C-', 6 => 'C+', 7 => 'B-', 8 => 'B+', 9 => 'A-', 10 => 'A+'];
                        $totalVal = 0;
                        $cnt = 0;
                        foreach ($indicators as $ind) {
                            $g = $grades->get($ind->id);
                            if ($g && $g->value !== null) {
                                $totalVal += (int) $g->value;
                                $cnt++;
                            }
                        }
                        $readingAvg = $cnt > 0 ? min(10, (int) ceil($totalVal / $cnt)) : null;
                        $readingLetter = $readingAvg !== null ? ($readingScores[$readingAvg] ?? '—') : '—';
                    @endphp

                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th style="width:30px;">N°</th>
                                <th style="text-align:left;">Indicador</th>
                                <th style="width:100px;">Calificacion (1-10)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNum = 1; @endphp
                            @foreach($indicators as $ind)
                                @php
                                    $g = $grades->get($ind->id);
                                    $val = $g ? $g->value : null;
                                @endphp
                                <tr>
                                    <td>{{ $rowNum++ }}</td>
                                    <td style="text-align:left;font-size:7px;">{{ $ind->name }}</td>
                                    <td style="font-weight:bold;">{{ $val !== null ? $val : '—' }}</td>
                                </tr>
                            @endforeach
                            <tr style="background-color:#dbeafe;font-weight:bold;">
                                <td colspan="2" style="text-align:right;">Promedio:</td>
                                <td style="color:#1e40af;">{{ $readingLetter }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="font-size:6px;color:#666;margin-top:2px;">
                        Calificacion numerica 1-10 → Promedio = {{ $readingAvg !== null ? 'ROUNDUP('.round($totalVal/$cnt,1).')' : '—' }} → {{ $readingLetter }}
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="line">{{ $teacherName }}</div>
                    <div>{{ __('Tutor(a)') }}</div>
                </td>
                {{-- <td>
                    <div class="line">{{ $inspectorName }}</div>
                    <div>{{ __('Inspector(a)') }}</div>
                </td> --}}
            </tr>
        </table>
    </div>

    <div class="footer-fixed">
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $trimesterName }} · {{ $gradeName }} · {{ $shiftName }} · {{ $student->student_code }}
    </div>
</body>
</html>
