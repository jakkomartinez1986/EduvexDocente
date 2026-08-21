<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Notas del Estudiante</title>
    <style>
        @page {
            margin: 12mm 10mm;
        }
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
            margin-bottom: 10px;
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
            font-size: 8px;
            border: 1px solid #d1d5db;
        }
        .matrix-table th,
        .matrix-table td {
            border: 1px solid #d1d5db;
            padding: 3px 6px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table th {
            background-color: #e5e7eb;
            font-weight: bold;
            font-size: 8px;
        }
        .matrix-table .name-col {
            text-align: left;
            min-width: 130px;
        }
        .matrix-table .total-col {
            font-weight: bold;
            background-color: #f3f4f6;
        }
        .matrix-table .status-aprobado {
            color: #16a34a;
            font-weight: bold;
        }
        .matrix-table .status-supletorio {
            color: #dc2626;
            font-weight: bold;
        }
        .matrix-table .status-reprobado {
            color: #dc2626;
            font-weight: bold;
        }
        .annual-row {
            background-color: #dbeafe !important;
            font-weight: bold;
        }
        .annual-row td {
            border-top: 2px solid #1e40af !important;
        }
        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures table {
            width: 100%;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .signatures .line {
            border-top: 1px solid #000;
            margin: 0 40px;
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

    <div class="title">{{ __('REPORTE DE NOTAS DEL ESTUDIANTE') }}</div>

    <div class="info-section">
        <table>
            <tr>
                <td class="info-label">{{ __('Grado / Curso:') }}</td>
                <td>{{ $gradeName }}</td>
                <td class="info-label">{{ __('Jornada:') }}</td>
                <td>{{ $shiftName }}</td>
            </tr>
            <tr>
                <td class="info-label">{{ __('Año Lectivo:') }}</td>
                <td>{{ $yearName }}</td>
                <td class="info-label">{{ __('Tutor(a):') }}</td>
                <td>{{ $teacherName }}</td>
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

    {{-- @if ($gradingScheme)
        <div style="font-size:8px;margin-bottom:8px;color:#555;">
            {{ __('Esquema de calificacion:') }}
            {{ __('Formativa') }} {{ number_format($gradingScheme->formative_percentage, 1) }}% ·
            {{ __('Examen') }} {{ number_format($gradingScheme->exam_percentage, 1) }}% ·
            {{ __('Proyecto') }} {{ number_format($gradingScheme->project_percentage, 1) }}%
        </div>
    @endif --}}

    @foreach($subjectsData as $subjectInfo)
        <div class="subject-block">
            <div class="subject-title">{{ $subjectInfo['name'] }}</div>
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th class="name-col">{{ __('Trimestre') }}</th>
                        <th>{{ __('Formativa') }}</th>
                        <th>{{ __('Examen') }}</th>
                        <th>{{ __('Proyecto') }}</th>
                        <th class="total-col">{{ __('Total') }}</th>
                        <th>{{ __('Estado') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $annualTotals = ['formative' => [], 'exam' => [], 'project' => []]; @endphp
                    @foreach($subjectInfo['trimesters'] as $tri)
                        @php
                            $formativeWeighted = $tri['formative'] !== null
                                ? $tri['formative'] * (($gradingScheme->formative_percentage ?? 0) / 100)
                                : 0;
                            $examWeighted = $tri['exam'] !== null
                                ? $tri['exam'] * (($gradingScheme->exam_percentage ?? 0) / 100)
                                : 0;
                            $projectWeighted = $tri['project'] !== null
                                ? $tri['project'] * (($gradingScheme->project_percentage ?? 0) / 100)
                                : 0;

                            $totalValue = $formativeWeighted + $examWeighted + $projectWeighted;
                            $hasData = $tri['formative'] !== null || $tri['exam'] !== null || $tri['project'] !== null;
                            $total = $hasData ? round($totalValue, 2) : null;

                            $statusText = $total === null ? '—'
                                : ($total >= 7 ? 'Aprobado' : ($total >= 5 ? 'Supletorio' : 'Reprobado'));
                            $statusClass = $total === null ? ''
                                : ($total >= 7 ? 'status-aprobado' : ($total >= 5 ? 'status-supletorio' : 'status-reprobado'));

                            if ($tri['formative'] !== null) $annualTotals['formative'][] = $tri['formative'];
                            if ($tri['exam'] !== null) $annualTotals['exam'][] = $tri['exam'];
                            if ($tri['project'] !== null) $annualTotals['project'][] = $tri['project'];
                        @endphp
                        <tr>
                            <td class="name-col" style="font-weight:bold;">{{ $tri['trimester'] }}</td>
                            <td>{{ $tri['formative'] !== null ? number_format($tri['formative'], 2) : '—' }}</td>
                            <td>{{ $tri['exam'] !== null ? number_format($tri['exam'], 2) : '—' }}</td>
                            <td>{{ $tri['project'] !== null ? number_format($tri['project'], 2) : '—' }}</td>
                            <td class="total-col">{{ $total !== null ? number_format($total, 2) : '—' }}</td>
                            <td class="{{ $statusClass }}">{{ $statusText }}</td>
                        </tr>
                    @endforeach
                    @php
                        $avgFormative = count($annualTotals['formative']) > 0
                            ? floor(array_sum($annualTotals['formative']) / count($annualTotals['formative']) * 100) / 100
                            : null;
                        $avgExam = count($annualTotals['exam']) > 0
                            ? floor(array_sum($annualTotals['exam']) / count($annualTotals['exam']) * 100) / 100
                            : null;
                        $avgProject = count($annualTotals['project']) > 0
                            ? floor(array_sum($annualTotals['project']) / count($annualTotals['project']) * 100) / 100
                            : null;

                        $fw = $avgFormative !== null ? $avgFormative * (($gradingScheme->formative_percentage ?? 0) / 100) : 0;
                        $ew = $avgExam !== null ? $avgExam * (($gradingScheme->exam_percentage ?? 0) / 100) : 0;
                        $pw = $avgProject !== null ? $avgProject * (($gradingScheme->project_percentage ?? 0) / 100) : 0;
                        $annualTotal = ($avgFormative !== null || $avgExam !== null || $avgProject !== null)
                            ? round($fw + $ew + $pw, 2) : null;

                        $annualStatus = $annualTotal === null ? '—'
                            : ($annualTotal >= 7 ? 'Aprobado' : ($annualTotal >= 5 ? 'Supletorio' : 'Reprobado'));
                        $annualStatusClass = $annualTotal === null ? ''
                            : ($annualTotal >= 7 ? 'status-aprobado' : ($annualTotal >= 5 ? 'status-supletorio' : 'status-reprobado'));
                    @endphp
                    <tr class="annual-row">
                        <td class="name-col">{{ __('Promedio Anual') }}</td>
                        <td>{{ $avgFormative !== null ? number_format($avgFormative, 2) : '—' }}</td>
                        <td>{{ $avgExam !== null ? number_format($avgExam, 2) : '—' }}</td>
                        <td>{{ $avgProject !== null ? number_format($avgProject, 2) : '—' }}</td>
                        <td class="total-col">{{ $annualTotal !== null ? number_format($annualTotal, 2) : '—' }}</td>
                        <td class="{{ $annualStatusClass }}">{{ $annualStatus }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

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
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $student->student_code }}
    </div>
</body>
</html>
