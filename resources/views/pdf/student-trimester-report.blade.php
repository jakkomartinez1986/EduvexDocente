<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Notas por Trimestre</title>
    <style>
        @page { margin: 8mm 8mm; }
        body { font-family: sans-serif; font-size: 8px; color: #000; margin: 0; padding: 0; line-height: 1.3; }

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

        .qual-violet { background-color: #6d28d9 !important; }
        .avg-col-violet { font-weight: bold; background-color: #f5f3ff !important; color: #6d28d9 !important; }

        .behavior-table { width: 100%; border-collapse: collapse; font-size: 7.5px; margin-top: 4px; border: 1px solid #d1d5db; }
        .behavior-table th, .behavior-table td { border: 1px solid #d1d5db; padding: 2px 4px; text-align: center; vertical-align: middle; }
        .behavior-table th { background-color: #fee2e2; font-weight: bold; font-size: 7px; }
        .behavior-table .name-col { text-align: left; }
        .behavior-table tbody tr:nth-child(even) { background-color: #fef2f2; }

        .legend { font-size: 6px; color: #666; margin: 2px 0 4px 0; }

        .signatures { margin-top: 20px; page-break-inside: avoid; }
        .signatures table { width: 33%; }
        .signatures td { text-align: center; vertical-align: bottom; }
        .signatures .line { border-top: 1px solid #000; margin: 0 15px; padding-top: 3px; font-weight: bold; font-size: 8px; }

        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
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

    <div class="title">{{ __('REPORTE DE CALIFICACIONES POR TRIMESTRE') }}</div>

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
                <td class="info-label">{{ __('Año Lectivo:') }}</td>
                <td>{{ $yearName ?? '' }}</td>
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
                <td class="label">{{ __('Código:') }}</td>
                <td>{{ $student->student_code }}</td>
            </tr>
        </table>
    </div>

    {{-- ═══════ ASIGNATURAS CUANTITATIVAS ═══════ --}}
    @if(count($subjectsData) > 0)
        <div class="section-label">{{ __('Asignaturas') }}</div>
        <table class="matrix-table">
            <thead>
                <tr>
                    <th class="name-col">{{ __('Asignatura') }}</th>
                    <th>EVAL. FORMATIVA<br>NOTA {{ $formativePct }}%</th>
                    <th>EVAL. SUMATIVA<br>NOTA {{ $sumativePct }}%</th>
                    <th>NOTA</th>
                    <th>{{ __('Estado') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subjectsData as $subject)
                    @php
                        $nota = $subject['nota'] ?? null;
                        $statusText = $nota === null ? '—' : ($nota >= 7 ? 'Aprobado' : ($nota >= 5 ? 'Supletorio' : 'Reprobado'));
                        $statusClass = $nota === null ? '' : ($nota >= 7 ? 'status-aprobado' : ($nota >= 5 ? 'status-supletorio' : 'status-reprobado'));
                    @endphp
                    <tr>
                        <td class="name-col" style="font-weight:bold;">{{ $subject['name'] }}</td>
                        <td>{{ $subject['formativeWeighted'] !== null ? number_format($subject['formativeWeighted'], 2) : '—' }}</td>
                        <td>{{ $subject['sumativeWeighted'] !== null ? number_format($subject['sumativeWeighted'], 2) : '—' }}</td>
                        <td style="font-weight:bold;">{{ $nota !== null ? number_format($nota, 2) : '—' }}</td>
                        <td class="status-col {{ $statusClass }}">{{ $statusText }}</td>
                    </tr>
                @endforeach
                @php
                    $allNotas = array_column(array_filter($subjectsData, fn ($s) => $s['nota'] !== null), 'nota');
                    $allFmt = array_column(array_filter($subjectsData, fn ($s) => $s['formativeWeighted'] !== null), 'formativeWeighted');
                    $allSum = array_column(array_filter($subjectsData, fn ($s) => $s['sumativeWeighted'] !== null), 'sumativeWeighted');
                    $avgNota = count($allNotas) > 0 ? round(array_sum($allNotas) / count($allNotas), 2) : null;
                    $avgFmt = count($allFmt) > 0 ? round(array_sum($allFmt) / count($allFmt), 2) : null;
                    $avgSum = count($allSum) > 0 ? round(array_sum($allSum) / count($allSum), 2) : null;
                    $generalStatus = $avgNota === null ? '—' : ($avgNota >= 7 ? 'Aprobado' : ($avgNota >= 5 ? 'Supletorio' : 'Reprobado'));
                    $generalStatusClass = $avgNota === null ? '' : ($avgNota >= 7 ? 'status-aprobado' : ($avgNota >= 5 ? 'status-supletorio' : 'status-reprobado'));
                @endphp
                <tr class="summary-row">
                    <td class="name-col">{{ __('Promedio General') }}</td>
                    <td>{{ $avgFmt !== null ? number_format($avgFmt, 2) : '—' }}</td>
                    <td>{{ $avgSum !== null ? number_format($avgSum, 2) : '—' }}</td>
                    <td class="avg-col">{{ $avgNota !== null ? number_format($avgNota, 2) : '—' }}</td>
                    <td class="status-col {{ $generalStatusClass }}">{{ $generalStatus }}</td>
                </tr>
            </tbody>
        </table>

        <div class="legend">
            <strong>{{ __('Escala:') }}</strong> MA ({{ $formativePct }}%) = {{ __('Promedio de Actividades formativas') }} · SUM ({{ $sumativePct }}%) = {{ __('Examen + Proyecto') }} · NOTA = MA + SUM
        </div>
    @endif

    {{-- ═══════ ASIGNATURAS CUALITATIVAS ═══════ --}}
    @php
        $qualSubjects = collect($qualSubjectsData);
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

    @if($qualSubjects->count() > 0)
        <div class="section-label" style="color:#6d28d9; border-bottom-color:#6d28d9;">{{ __('Asignaturas Cualitativas') }}</div>
        <table class="matrix-table">
            <thead>
                <tr style="background-color:#6d28d9;">
                    <th class="name-col" style="background-color:#6d28d9;">{{ __('Asignatura') }}</th>
                    <th style="background-color:#6d28d9;">{{ __('Calificación') }}</th>
                    <th style="background-color:#6d28d9;">{{ __('Observación') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($qualSubjects as $qualSubject)
                    @php
                        $periodData = $qualSubject['periods'][0] ?? null;
                        $gradeLetter = $periodData['letter'] ?? null;
                        $obsKey = $periodData['obs'] ?? null;
                        $obsText = $obsKey && isset($qualObs[$obsKey]) ? $qualObs[$obsKey] : '';
                    @endphp
                    <tr>
                        <td class="name-col" style="font-weight:bold;">{{ $qualSubject['name'] }}</td>
                        <td>
                            @if($gradeLetter)
                                <span class="qual-letter" style="color:#6d28d9;">{{ $gradeLetter }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="qual-obs">
                            @if($obsText)
                                <strong>{{ $qualObsLabel[$obsKey] ?? $obsKey }}:</strong> {{ $obsText }}
                            @else
                                —
                            @endif
                        </td>
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
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $trimesterName }} · {{ $gradeName }} · {{ $shiftName }} · {{ $student->student_code }}
    </div>
</body>
</html>
