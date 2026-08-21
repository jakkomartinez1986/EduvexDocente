<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Notas por Trimestre</title>
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
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            border: 1px solid #d1d5db;
        }
        .matrix-table th,
        .matrix-table td {
            border: 1px solid #d1d5db;
            padding: 4px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table th {
            background-color: #1e40af;
            color: #fff;
            font-weight: bold;
            font-size: 9px;
        }
        .matrix-table .name-col {
            text-align: left;
            min-width: 180px;
        }
        .matrix-table .total-col {
            font-weight: bold;
            background-color: #f3f4f6;
            color: #1e40af;
        }
        .matrix-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .matrix-table .status-aprobado {
            color: #16a34a;
            font-weight: bold;
        }
        .matrix-table .status-supletorio {
            color: #d97706;
            font-weight: bold;
        }
        .matrix-table .status-reprobado {
            color: #dc2626;
            font-weight: bold;
        }
        .summary-row {
            background-color: #dbeafe !important;
            font-weight: bold;
        }
        .summary-row td {
            border-top: 2px solid #1e40af !important;
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

    <div class="title">{{ __('REPORTE DE NOTAS POR TRIMESTRE') }}</div>

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

    {{-- @if ($gradingScheme)
        <div style="font-size:8px;margin-bottom:8px;color:#555;">
            {{ __('Esquema de calificacion:') }}
            {{ __('Formativa') }} {{ number_format($gradingScheme->formative_percentage, 1) }}% ·
            {{ __('Examen') }} {{ number_format($gradingScheme->exam_percentage, 1) }}% ·
            {{ __('Proyecto') }} {{ number_format($gradingScheme->project_percentage, 1) }}%
        </div>
    @endif --}}

    <table class="matrix-table">
        <thead>
            <tr>
                <th class="name-col">{{ __('Asignatura') }}</th>
                <th>{{ __('Formativa') }}<div style="font-size:6.5px;font-weight:normal;">{{ number_format($gradingScheme->formative_percentage ?? 0, 0) }}%</div></th>
                <th>{{ __('Examen') }}<div style="font-size:6.5px;font-weight:normal;">{{ number_format($gradingScheme->exam_percentage ?? 0, 0) }}%</div></th>
                <th>{{ __('Proyecto') }}<div style="font-size:6.5px;font-weight:normal;">{{ number_format($gradingScheme->project_percentage ?? 0, 0) }}%</div></th>
                <th class="total-col">{{ __('Total') }}</th>
                <th>{{ __('Estado') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $totals = ['formative' => [], 'exam' => [], 'project' => []]; @endphp
            @forelse($subjectsData as $subject)
                @php
                    $formativeWeighted = $subject['formative'] !== null
                        ? $subject['formative'] * (($gradingScheme->formative_percentage ?? 0) / 100)
                        : 0;
                    $examWeighted = $subject['exam'] !== null
                        ? $subject['exam'] * (($gradingScheme->exam_percentage ?? 0) / 100)
                        : 0;
                    $projectWeighted = $subject['project'] !== null
                        ? $subject['project'] * (($gradingScheme->project_percentage ?? 0) / 100)
                        : 0;

                    $totalValue = $formativeWeighted + $examWeighted + $projectWeighted;
                    $hasData = $subject['formative'] !== null || $subject['exam'] !== null || $subject['project'] !== null;
                    $total = $hasData ? round($totalValue, 2) : null;

                    $statusText = $total === null ? '—'
                        : ($total >= 7 ? 'Aprobado' : ($total >= 5 ? 'Supletorio' : 'Reprobado'));
                    $statusClass = $total === null ? ''
                        : ($total >= 7 ? 'status-aprobado' : ($total >= 5 ? 'status-supletorio' : 'status-reprobado'));

                    if ($subject['formative'] !== null) $totals['formative'][] = $subject['formative'];
                    if ($subject['exam'] !== null) $totals['exam'][] = $subject['exam'];
                    if ($subject['project'] !== null) $totals['project'][] = $subject['project'];
                @endphp
                <tr>
                    <td class="name-col" style="font-weight:bold;">{{ $subject['name'] }}</td>
                    <td>{{ $subject['formative'] !== null ? number_format($subject['formative'], 2) : '—' }}</td>
                    <td>{{ $subject['exam'] !== null ? number_format($subject['exam'], 2) : '—' }}</td>
                    <td>{{ $subject['project'] !== null ? number_format($subject['project'], 2) : '—' }}</td>
                    <td class="total-col">{{ $total !== null ? number_format($total, 2) : '—' }}</td>
                    <td class="{{ $statusClass }}">{{ $statusText }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;">{{ __('No hay datos de calificaciones para este trimestre.') }}</td>
                </tr>
            @endforelse
            @php
                $avgFormative = count($totals['formative']) > 0
                    ? floor(array_sum($totals['formative']) / count($totals['formative']) * 100) / 100
                    : null;
                $avgExam = count($totals['exam']) > 0
                    ? floor(array_sum($totals['exam']) / count($totals['exam']) * 100) / 100
                    : null;
                $avgProject = count($totals['project']) > 0
                    ? floor(array_sum($totals['project']) / count($totals['project']) * 100) / 100
                    : null;

                $fw = $avgFormative !== null ? $avgFormative * (($gradingScheme->formative_percentage ?? 0) / 100) : 0;
                $ew = $avgExam !== null ? $avgExam * (($gradingScheme->exam_percentage ?? 0) / 100) : 0;
                $pw = $avgProject !== null ? $avgProject * (($gradingScheme->project_percentage ?? 0) / 100) : 0;
                $generalTotal = ($avgFormative !== null || $avgExam !== null || $avgProject !== null)
                    ? round($fw + $ew + $pw, 2) : null;

                $generalStatus = $generalTotal === null ? '—'
                    : ($generalTotal >= 7 ? 'Aprobado' : ($generalTotal >= 5 ? 'Supletorio' : 'Reprobado'));
                $generalStatusClass = $generalTotal === null ? ''
                    : ($generalTotal >= 7 ? 'status-aprobado' : ($generalTotal >= 5 ? 'status-supletorio' : 'status-reprobado'));
            @endphp
            <tr class="summary-row">
                <td class="name-col">{{ __('Promedio General del Trimestre') }}</td>
                <td>{{ $avgFormative !== null ? number_format($avgFormative, 2) : '—' }}</td>
                <td>{{ $avgExam !== null ? number_format($avgExam, 2) : '—' }}</td>
                <td>{{ $avgProject !== null ? number_format($avgProject, 2) : '—' }}</td>
                <td class="total-col">{{ $generalTotal !== null ? number_format($generalTotal, 2) : '—' }}</td>
                <td class="{{ $generalStatusClass }}">{{ $generalStatus }}</td>
            </tr>
        </tbody>
    </table>

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
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $trimesterName }} · {{ $student->student_code }}
    </div>
</body>
</html>
