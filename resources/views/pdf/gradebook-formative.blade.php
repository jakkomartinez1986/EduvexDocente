<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Calificaciones</title>
    <style>
        @page {
            margin: 8mm 8mm;
            size: portrait;
        }
        body {
            font-family: sans-serif;
            font-size: 7px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 4px;
        }
        .header table { width: 100%; }
        .header td { vertical-align: middle; }
        .header-logo { width: 50px; }
        .header-logo img { max-height: 42px; max-width: 42px; }
        .header-center { text-align: center; }
        .header-center h1 { font-size: 11px; font-weight: bold; margin: 0 0 1px 0; text-transform: uppercase; }
        .header-center p { font-size: 8px; margin: 0; color: #333; }
        .title {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            margin: 4px 0;
            text-transform: uppercase;
        }
        .info-section { margin-bottom: 3px; font-size: 7px; }
        .info-section table { width: 100%; }
        .info-section td { padding: 0 3px; }
        .info-label { font-weight: bold; width: 75px; }
        .scheme-note { font-size: 7px; margin-bottom: 3px; }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5px;
            page-break-inside: avoid;
        }
        .matrix-table th, .matrix-table td {
            border: 1px solid #000;
            padding: 1.5px 2px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table th {
            background-color: #e5e7eb;
            font-weight: bold;
            font-size: 6.5px;
        }
        .matrix-table .name-col {
            text-align: left;
            min-width: 110px;
            font-size: 6.5px;
        }
        .matrix-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .matrix-table .block-col { font-size: 6px; }
        .matrix-table .activity-col { font-size: 6px; min-width: 22px; max-width: 28px; }
        .matrix-table .block-avg-col { font-size: 6px; font-weight: bold; background-color: #f3f4f6; min-width: 26px; }
        .matrix-table .block-header-group { background-color: #dbeafe; }
        .matrix-table .block-activity-header { background-color: #eff6ff; font-size: 6px; }
        .signatures { margin-top: 20px; page-break-inside: avoid; }
        .signatures table { width: 33%; }
        .signatures td { text-align: center; vertical-align: bottom; }
        .signatures .line {
            border-top: 1px solid #000;
            margin: 0 15px;
            padding-top: 3px;
            font-weight: bold;
            font-size: 8px;
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

    <div class="title">{{ __('REPORTE DE CALIFICACIONES') }}</div>

    <div class="info-section">
        <table>
            <tr>
                <td class="info-label">{{ __('Asignatura:') }}</td>
                <td>{{ $subjectName }}</td>
                <td class="info-label">{{ __('Grado / Curso:') }}</td>
                <td>{{ $gradeName }}</td>
                <td class="info-label">{{ __('Trimestre:') }}</td>
                <td>{{ $trimesterName }}</td>
            </tr>
            <tr>
                <td class="info-label">{{ __('Jornada:') }}</td>
                <td>{{ $shiftName }}</td>
                <td class="info-label">{{ __('Ano Lectivo:') }}</td>
                <td>{{ $yearName }}</td>
                <td class="info-label">{{ __('Docente:') }}</td>
                <td>{{ $teacherName }}</td>
            </tr>
        </table>
    </div>

    {{-- @if ($gradingScheme)
        <div class="scheme-note">
            {{ __('Formativa') }} {{ number_format($gradingScheme->formative_percentage, 1) }}% ·
            {{ __('Examen') }} {{ number_format($gradingScheme->exam_percentage, 1) }}% ·
            {{ __('Proyecto') }} {{ number_format($gradingScheme->project_percentage, 1) }}%
        </div>
    @endif --}}

    @php
        $totalActivityCols = 0;
        foreach ($blocks as $block) {
            $totalActivityCols += max($block->activities->count(), 1) + 1;
        }
        $colspan = 2 + $totalActivityCols + 1;
    @endphp

    <table class="matrix-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:18px;">N°</th>
                <th rowspan="2" class="name-col">{{ __('Estudiante') }}</th>
                @foreach($blocks as $block)
                    @php
                        $actCount = $block->activities->count();
                        $blockColspan = max($actCount, 1) + 1;
                    @endphp
                    <th colspan="{{ $blockColspan }}" class="block-header-group">
                        {{ $block->name }}
                        @if($block->internal_percentage)
                            <span style="font-weight:normal;">({{ $block->internal_percentage }}%)</span>
                        @endif
                    </th>
                @endforeach
                <th rowspan="2">{{ __('Prom.') }}</th>
            </tr>
            <tr>
                @foreach($blocks as $block)
                    @if($block->activities->count() > 0)
                        @foreach($block->activities as $activity)
                            <th class="block-activity-header" title="{{ $activity->name }}">
                                {{ Str::limit($activity->name, 5) }}
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
            @foreach($students as $idx => $student)
                @php
                    $blockAverages = [];
                    foreach ($blocks as $block) {
                        $totalActivities = $block->activities->count();
                        if ($totalActivities === 0) { $blockAverages[] = null; continue; }
                        $total = 0;
                        foreach ($block->activities as $activity) {
                            $grade = $activity->grades->firstWhere('student_id', $student->id);
                            if ($grade && $grade->grade !== null) { $total += $grade->grade; }
                        }
                        $blockAverages[] = floor($total / $totalActivities * 100) / 100;
                    }
                    $validAverages = array_values(array_filter($blockAverages, fn ($v) => $v !== null));
                    $formativeAvg = count($validAverages) > 0
                        ? floor(array_sum($validAverages) / count($validAverages) * 100) / 100
                        : null;
                @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td class="name-col">{{ $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')) }}</td>
                    @foreach($blocks as $blockIdx => $block)
                        @foreach($block->activities as $activity)
                            @php
                                $grade = $activity->grades->firstWhere('student_id', $student->id);
                                $val = ($grade && $grade->grade !== null) ? $grade->grade : null;
                            @endphp
                            <td class="activity-col">{{ $val !== null ? number_format($val, 1) : '—' }}</td>
                        @endforeach
                        @if($block->activities->count() === 0)
                            <td class="activity-col">—</td>
                        @endif
                        <td class="block-avg-col">{{ $blockAverages[$blockIdx] !== null ? number_format($blockAverages[$blockIdx], 1) : '—' }}</td>
                    @endforeach
                    <td style="font-weight:bold;">{{ $formativeAvg !== null ? number_format($formativeAvg, 1) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <div class="line">{{ $teacherName }}</div>
                    <div>{{ __('Docente') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-fixed">
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $trimesterName }} · {{ $gradeName }} · {{ $shiftName }} · {{ $subjectName }}
    </div>
</body>
</html>
