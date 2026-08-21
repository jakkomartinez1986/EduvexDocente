<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Libro de Asistencias</title>
    <style>
        @page {
            margin: 15mm 10mm 15mm 10mm;
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
            margin-bottom: 10px;
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
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 2px 0;
        }
        .header-center p {
            font-size: 9px;
            margin: 0;
            color: #333;
        }
        .header-right {
            width: 60px;
            text-align: right;
        }
        .header-right img {
            max-height: 50px;
            max-width: 55px;
        }
        .info-section {
            margin-bottom: 8px;
            font-size: 10px;
        }
        .info-section table {
            width: 100%;
        }
        .info-section td {
            padding: 1px 4px;
        }
        .info-label {
            font-weight: bold;
            width: 70px;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
            page-break-inside: auto;
        }
        .matrix-table th,
        .matrix-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
        }
        .matrix-table th {
            background-color: #e5e7eb;
            font-weight: bold;
        }
        .matrix-table .name-col {
            text-align: left;
            min-width: 140px;
        }
        .matrix-table .num-col {
            width: 60px;
        }
        .matrix-table .subject-header {
            min-width: 50px;
        }
        .matrix-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .matrix-table tfoot {
            font-weight: bold;
            background-color: #e5e7eb;
        }
        .chart-section {
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .chart-section h3 {
            font-size: 10px;
            margin: 0 0 6px 0;
            padding: 0;
            border-bottom: 1px solid #999;
        }
        .chart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        .chart-table td {
            padding: 1px 3px;
            vertical-align: middle;
        }
        .bar-cell {
            width: 50%;
        }
        .bar-container {
            width: 100%;
            height: 10px;
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
        }
        .bar-j {
            height: 10px;
            background-color: #10b981;
        }
        .bar-i {
            height: 10px;
            background-color: #ef4444;
        }
        .bar-label {
            font-size: 7.5px;
            text-align: center;
            width: 25px;
        }
        .bar-subject {
            font-weight: bold;
            font-size: 7.5px;
            width: 100px;
        }
        .signature-section {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-section table {
            width: 100%;
        }
        .signature-section td {
            vertical-align: bottom;
            padding: 0 10px;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
        .signature-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            margin-top: 40px;
            font-size: 9px;
            font-weight: bold;
        }
        .signature-sub {
            font-size: 7.5px;
            font-weight: normal;
            color: #555;
        }
        .legend {
            font-size: 7.5px;
            margin-top: 4px;
        }
        .legend span {
            display: inline-block;
            margin-right: 10px;
        }
        .legend-color {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 2px;
            vertical-align: middle;
        }
        .page-break {
            page-break-after: always;
        }
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="header-logo">
                    @if ($school->report_logo_path)
                        <img src="{{ asset('storage/' . $school->report_logo_path) }}" alt="Logo">
                    @endif
                </td>
                <td class="header-center">
                    <h1>{{ $school->name_school ?? 'Unidad Educativa' }}</h1>
                    <p>{{ __('Libro de Asistencias') }}
                        @if ($periodLabel)
                            · {{ $periodLabel }}
                        @endif
                    </p>
                    <p style="font-size:8px; color:#666;">{{ $school->location ?? '' }}</p>
                </td>
                <td class="header-right">
                    @if ($school->logo_path)
                        <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Info --}}
    <div class="info-section">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-label">{{ __('Grado') }}:</td>
                <td>{{ $gradeName }} @if ($shiftName) · {{ $shiftName }} @endif</td>
                <td class="info-label" style="width:80px;">{{ __('Periodo') }}:</td>
                <td>{{ $periodLabel }}</td>
                <td class="info-label" style="width:70px;">{{ __('Alumnos') }}:</td>
                <td>{{ $studentCount }}</td>
                <td class="info-label" style="width:90px;">{{ __('Año Lectivo') }}:</td>
                <td>{{ $yearName }}</td>
            </tr>
        </table>
    </div>

    {{-- Attendance Matrix --}}
    @if (count($students) > 0)
        <table class="matrix-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th rowspan="2" style="width:20px;">N°</th>
                    <th rowspan="2" class="name-col">{{ __('Nómina') }}</th>
                    @foreach ($subjects as $subject)
                        <th colspan="2" class="subject-header">{{ $subject['subject_name'] }}</th>
                    @endforeach
                    <th colspan="2" class="num-col" style="background-color:#d1d5db;">{{ __('Total') }}</th>
                </tr>
                <tr>
                    @foreach ($subjects as $subject)
                        <th style="color:#059669;">J</th>
                        <th style="color:#dc2626;">I</th>
                    @endforeach
                    <th style="background-color:#d1d5db;color:#059669;">J</th>
                    <th style="background-color:#d1d5db;color:#dc2626;">I</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $idx => $student)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td class="name-col">{{ $student['name'] }}</td>
                        @foreach ($student['subjects'] as $sd)
                            <td>{{ $sd['j'] > 0 ? $sd['j'] : '-' }}</td>
                            <td>{{ $sd['i'] > 0 ? $sd['i'] : '-' }}</td>
                        @endforeach
                        <td style="background-color:#f3f4f6;">{{ $student['total_j'] > 0 ? $student['total_j'] : '-' }}</td>
                        <td style="background-color:#f3f4f6;">{{ $student['total_i'] > 0 ? $student['total_i'] : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;">{{ __('Totales') }}</td>
                    @foreach ($subjects as $subject)
                        @php $t = $totalsBySubject[$subject['id']] ?? ['j' => 0, 'i' => 0]; @endphp
                        <td>{{ $t['j'] > 0 ? $t['j'] : '-' }}</td>
                        <td>{{ $t['i'] > 0 ? $t['i'] : '-' }}</td>
                    @endforeach
                    <td>{{ $globalTotalJ > 0 ? $globalTotalJ : '-' }}</td>
                    <td>{{ $globalTotalI > 0 ? $globalTotalI : '-' }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <p style="text-align:center;padding:30px 0;color:#999;">{{ __('No hay registros de asistencia para este período.') }}</p>
    @endif

    {{-- Statistical Chart --}}
    @if (count($subjects) > 0 && ($globalTotalJ > 0 || $globalTotalI > 0))
        <div class="chart-section">
            <h3>{{ __('Estadísticas de Inasistencias por Materia') }}</h3>
            <table class="chart-table">
                <tr>
                    <td colspan="3"></td>
                    <td class="bar-label" style="text-align:left;font-size:7px;">0</td>
                    <td class="bar-label" style="text-align:right;font-size:7px;">{{ $chartMax }}</td>
                </tr>
                @foreach ($subjects as $subject)
                    @php
                        $t = $totalsBySubject[$subject['id']] ?? ['j' => 0, 'i' => 0];
                        $jPercent = $chartMax > 0 ? ($t['j'] / $chartMax * 100) : 0;
                        $iPercent = $chartMax > 0 ? ($t['i'] / $chartMax * 100) : 0;
                    @endphp
                    <tr>
                        <td class="bar-subject">{{ $subject['subject_name'] }}</td>
                        <td style="color:#059669;width:18px;">J</td>
                        <td class="bar-cell">
                            <div class="bar-container">
                                <div class="bar-j" style="width: {{ $jPercent }}%;"></div>
                            </div>
                        </td>
                        <td class="bar-label">{{ $t['j'] }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="color:#dc2626;width:18px;">I</td>
                        <td class="bar-cell">
                            <div class="bar-container">
                                <div class="bar-i" style="width: {{ $iPercent }}%;"></div>
                            </div>
                        </td>
                        <td class="bar-label">{{ $t['i'] }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="legend">
                <span><span class="legend-color" style="background:#10b981;"></span> J = {{ __('Falta Justificada') }}</span>
                <span><span class="legend-color" style="background:#ef4444;"></span> I = {{ __('Falta Injustificada') }}</span>
            </div>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signature-section">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="signature-box" style="text-align:left;">
                    <div class="signature-line">
                        {{ $teacherName }}<br>
                        <span class="signature-sub">{{ __('Docente Tutor/a') }}</span>
                    </div>
                </td>
                <td style="width:10%;"></td>
                <td class="signature-box" style="text-align:right;">
                    <div class="signature-line">
                        {{ $inspectorName ?: '______________________' }}<br>
                        <span class="signature-sub">{{ __('Inspector/a - General') }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-fixed">
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $subjectName }}
    </div>
</body>
</html>