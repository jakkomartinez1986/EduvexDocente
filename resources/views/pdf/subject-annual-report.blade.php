<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Calificaciones</title>
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; }
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
        .matrix-table { width: 100%; border-collapse: collapse; font-size: 8px; border: 1px solid #d1d5db; page-break-inside: avoid; }
        .matrix-table th, .matrix-table td { border: 1px solid #d1d5db; padding: 2px 5px; text-align: center; vertical-align: middle; }
        .matrix-table th { background-color: #1e40af; color: #fff; font-weight: bold; font-size: 8px; }
        .matrix-table .name-col { text-align: left; min-width: 140px; }
        .matrix-table .total-col { font-weight: bold; background-color: #f3f4f6; color: #1e40af; }
        .matrix-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .status-aprobado { color: #16a34a; font-weight: bold; }
        .status-supletorio { color: #dc2626; font-weight: bold; }
        .status-reprobado { color: #dc2626; font-weight: bold; }
        .signatures { margin-top: 20px; page-break-inside: avoid; }
        .signatures table { width: 33%; }
        .signatures td { text-align: center; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; font-weight: bold; font-size: 8px; }
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
                <td style="font-weight:bold;">{{ $subjectName }}</td>
                <td class="info-label">{{ __('Grado / Curso:') }}</td>
                <td>{{ $gradeName }}</td>
                <td class="info-label">{{ __('Jornada:') }}</td>
                <td>{{ $shiftName }}</td>
            </tr>
            <tr>
                <td class="info-label">{{ __('Ano Lectivo:') }}</td>
                <td>{{ $yearName }}</td>
                <td class="info-label">{{ __('Docente:') }}</td>
                <td>{{ $teacherName }}</td>
                <td></td>
                <td></td>
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
                <th class="name-col">{{ __('Estudiante') }}</th>
                @foreach($trimesters as $t)
                    <th>{{ $t['name'] }}<div style="font-size:6.5px;font-weight:normal;">{{ __('Total') }}</div></th>
                @endforeach
                <th class="total-col">{{ __('Prom. Anual') }}</th>
                <th>{{ __('Estado') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($studentsData as $sData)
                @php
                    $totals = $sData['trimesters'];
                    $validTotals = array_filter($totals, fn($v) => $v !== null);
                    $annualAvg = count($validTotals) > 0
                        ? round(array_sum($validTotals) / count($validTotals), 2)
                        : null;

                    $annualStatus = $annualAvg === null ? '—'
                        : ($annualAvg >= 7 ? 'Aprobado' : ($annualAvg >= 5 ? 'Supletorio' : 'Reprobado'));
                    $annualStatusClass = $annualAvg === null ? ''
                        : ($annualAvg >= 7 ? 'status-aprobado' : ($annualAvg >= 5 ? 'status-supletorio' : 'status-reprobado'));
                @endphp
                <tr>
                    <td class="name-col" style="font-weight:bold;">{{ $sData['name'] }}</td>
                    @foreach($totals as $t)
                        <td style="font-weight:bold;">{{ $t !== null ? number_format($t, 2) : '—' }}</td>
                    @endforeach
                    <td class="total-col">{{ $annualAvg !== null ? number_format($annualAvg, 2) : '—' }}</td>
                    <td class="{{ $annualStatusClass }}">{{ $annualStatus }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($trimesters) + 3 }}" style="text-align:center;">{{ __('No hay datos de calificaciones.') }}</td>
                </tr>
            @endforelse
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
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $subjectName }}
    </div>
</body>
</html>