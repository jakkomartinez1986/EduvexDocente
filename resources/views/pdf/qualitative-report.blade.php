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
        .info-label { font-weight: bold; width: 75px; }
        .matrix-table { width: 100%; border-collapse: collapse; font-size: 7px; page-break-inside: avoid; }
        .matrix-table th, .matrix-table td { border: 1px solid #000; padding: 2px 3px; text-align: center; }
        .matrix-table thead th { background-color: #dbeafe; font-size: 7px; }
        .matrix-table .student-col { text-align: left; font-weight: bold; min-width: 130px; font-size: 7px; }
        .matrix-table .indicator-col { min-width: 55px; font-size: 6.5px; }
        .matrix-table .promedio-col { min-width: 45px; font-weight: bold; font-size: 8px; background-color: #eff6ff; border-left: 2px solid #60a5fa; }
        .matrix-table .eje-header { background-color: #f3e8ff; color: #7c3aed; font-weight: bold; font-size: 7px; text-align: center; }
        .matrix-table .val-S { color: #059669; font-weight: bold; }
        .matrix-table .val-F { color: #2563eb; font-weight: bold; }
        .matrix-table .val-O { color: #d97706; font-weight: bold; }
        .matrix-table .val-N { color: #dc2626; font-weight: bold; }
        .prom-A\+ { color: #059669; } .prom-A\- { color: #10b981; }
        .prom-B\+ { color: #2563eb; } .prom-B\- { color: #3b82f6; }
        .prom-C\+ { color: #d97706; } .prom-C\- { color: #f59e0b; }
        .prom-D\+ { color: #ea580c; } .prom-D\- { color: #f97316; }
        .prom-E\+ { color: #dc2626; } .prom-E\- { color: #ef4444; }
        .signatures { margin-top: 20px; page-break-inside: avoid; }
        .signatures table { width: 33%; }
        .signatures td { text-align: center; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; font-weight: bold; font-size: 8px; }
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
        .page-content { margin-bottom: 25px; }
        .legend { margin-top: 4px; font-size: 7px; color: #333; }
        .legend span { margin-right: 8px; }
        .legend b { font-weight: bold; }
        .legend-section { margin-top: 2px; font-size: 7px; color: #333; }
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

    @php
        $isReading = ($qualType ?? '') === 'reading_promotion';
        $hasEjeGrouping = in_array($qualType ?? '', ['career_guidance', 'classroom_support']);

        $valueMap = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];
        $sfnLetterTable = [
            ['min' => 35, 'max' => 36, 'letter' => 'A+'],
            ['min' => 33, 'max' => 34, 'letter' => 'A-'],
            ['min' => 30, 'max' => 32, 'letter' => 'B+'],
            ['min' => 27, 'max' => 29, 'letter' => 'B-'],
            ['min' => 20, 'max' => 26, 'letter' => 'C+'],
            ['min' => 18, 'max' => 19, 'letter' => 'C-'],
            ['min' => 15, 'max' => 17, 'letter' => 'D+'],
            ['min' => 13, 'max' => 14, 'letter' => 'D-'],
            ['min' => 11, 'max' => 12, 'letter' => 'E+'],
            ['min' => 0,  'max' => 10, 'letter' => 'E-'],
        ];
        $readingLetterTable = [
            ['min' => 9.01, 'max' => 10, 'letter' => 'A+'],
            ['min' => 8.01, 'max' => 9,   'letter' => 'A-'],
            ['min' => 7.01, 'max' => 8,   'letter' => 'B+'],
            ['min' => 6.01, 'max' => 7,   'letter' => 'B-'],
            ['min' => 5.01, 'max' => 6,   'letter' => 'C+'],
            ['min' => 4.01, 'max' => 5,   'letter' => 'C-'],
            ['min' => 3.01, 'max' => 4,   'letter' => 'D+'],
            ['min' => 2.01, 'max' => 3,   'letter' => 'D-'],
            ['min' => 1.01, 'max' => 2,   'letter' => 'E+'],
            ['min' => 0,    'max' => 1,   'letter' => 'E-'],
        ];

        $groupedIndicators = $hasEjeGrouping ? collect($indicators)->groupBy(fn ($i) => $i['eje'] ?? 'General') : null;

        function calcSFNOAvg($studentId, $grades, $indicators, $valueMap, $letterTable) {
            $sum = 0; $hasValue = false;
            foreach ($indicators as $ind) {
                $id = $ind['id'] ?? $ind;
                $val = $grades[$studentId . '_' . $id] ?? null;
                if ($val && isset($valueMap[$val])) { $sum += $valueMap[$val]; $hasValue = true; }
            }
            if (!$hasValue) return null;
            foreach ($letterTable as $r) {
                if ($sum >= $r['min'] && $sum <= $r['max']) return $r['letter'];
            }
            return null;
        }

        function calcReadingAvg($studentId, $grades, $indicators, $letterTable) {
            $sum = 0; $count = 0; $total = count($indicators);
            foreach ($indicators as $ind) {
                $id = $ind['id'] ?? $ind;
                $val = $grades[$studentId . '_' . $id] ?? null;
                if ($val !== null && $val !== '' && is_numeric($val)) { $sum += (int) $val; $count++; }
            }
            if ($count === 0) return null;
            $avg = ceil($sum / $total);
            foreach ($letterTable as $r) {
                if ($avg >= $r['min'] && $avg <= $r['max']) return $r['letter'];
            }
            return null;
        }
    @endphp

    <table class="matrix-table">
        <thead>
            <tr>
                <th class="student-col">{{ __('Estudiante') }}</th>
                @if($hasEjeGrouping && $groupedIndicators)
                    @foreach($groupedIndicators as $ejeName => $ejeInds)
                        <th class="eje-header" colspan="{{ count($ejeInds) }}">{{ $ejeName }}</th>
                    @endforeach
                @else
                    @foreach($indicators as $ind)
                        <th class="indicator-col">{{ $ind['name'] }}</th>
                    @endforeach
                @endif
                <th class="promedio-col">{{ __('Promedio') }}</th>
            </tr>
            @if($hasEjeGrouping && $groupedIndicators)
                <tr>
                    <th class="student-col" style="font-size:7px;font-weight:normal;color:#666;">{{ __('Indicador') }}</th>
                    @foreach($groupedIndicators as $ejeInds)
                        @foreach($ejeInds as $ind)
                            <th class="indicator-col" style="font-size:7px;font-weight:normal;">{{ $ind['name'] }}</th>
                        @endforeach
                    @endforeach
                    <th style="background-color:#eff6ff;border-left:2px solid #60a5fa;"></th>
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse($students as $student)
                @php $avg = $isReading ? calcReadingAvg($student->id, $grades, $indicators, $readingLetterTable) : calcSFNOAvg($student->id, $grades, $indicators, $valueMap, $sfnLetterTable); @endphp
                <tr>
                    <td class="student-col">
                        {{ $student->user?->full_name ?? trim(($student->user?->lastname ?? '') . ' ' . ($student->user?->name ?? '')) }}
                        <span style="font-size:7px;font-weight:normal;color:#666;">{{ $student->student_code }}</span>
                    </td>
                    @if($hasEjeGrouping && $groupedIndicators)
                        @foreach($groupedIndicators as $ejeInds)
                            @foreach($ejeInds as $ind)
                                @php $val = $grades[$student->id . '_' . $ind['id']] ?? null; @endphp
                                <td class="indicator-col {{ $val ? 'val-' . $val : '' }}">{{ $val ?? '—' }}</td>
                            @endforeach
                        @endforeach
                    @else
                        @foreach($indicators as $ind)
                            @php $val = $grades[$student->id . '_' . $ind['id']] ?? null; @endphp
                            <td class="indicator-col {{ ($isReading && $val) ? '' : ($val ? 'val-' . $val : '') }}">
                                {{ $val ?? '—' }}
                            </td>
                        @endforeach
                    @endif
                    <td class="promedio-col prom-{{ $avg }}">{{ $avg ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($indicators) + 2 }}" style="text-align:center;">{{ __('No hay estudiantes matriculados.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($isReading)
        <div class="legend">
            <span>{{ __('Valores numericos del 1 al 10') }}</span>
        </div>
        <div class="legend-section">
            <b>{{ __('Promedio:') }}</b>
            {{ __('ROUNDUP(suma/indicadores, 0)') }} — {{ __('Escala de 1 a 10') }}
            · A+(9.01-10) A-(8.01-9) B+(7.01-8) B-(6.01-7) C+(5.01-6) C-(4.01-5) D+(3.01-4) D-(2.01-3) E+(1.01-2) E-(≤1)
        </div>
    @else
        <div class="legend">
            <span><b>S</b> — {{ __('Siempre') }}</span>
            <span><b>F</b> — {{ __('Frecuentemente') }}</span>
            <span><b>O</b> — {{ __('Ocasionalmente') }}</span>
            <span><b>N</b> — {{ __('Nunca') }}</span>
        </div>
        <div class="legend-section">
            <b>{{ __('Promedio:') }}</b>
            S , F , O , N  — {{ __('Escalas') }}
            · A+, A-, B+,B-, C+, C-, D+, D-, E+, E-
        </div>
    @endif

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
