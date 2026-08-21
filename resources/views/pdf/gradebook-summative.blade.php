<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Calificaciones</title>
    <style>
        body { font-family: sans-serif; font-size: 7px; color: #000; margin: 0; padding: 0; line-height: 1.2; }

        /* Header */
        .header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 3px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; border: none; padding: 0; }
        .header-logo { width: 40px; }
        .header-logo img { max-height: 34px; max-width: 34px; }
        .header-center { text-align: center; }
        .header-center h1 { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .header-center p { font-size: 6.5px; color: #333; }

        /* Title + Info */
        .title { text-align: center; font-size: 9px; font-weight: bold; margin: 2px 0; text-transform: uppercase; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
        .info-table td { font-size: 6.5px; padding: 0 4px; border: none; }
        .info-label { font-weight: bold; }

        /* Grades Table */
        .grades-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
        .grades-table th, .grades-table td { border: 0.5px solid #888; padding: 2px 3px; text-align: center; vertical-align: middle; }
        .grades-table th { background-color: #d1d5db; font-weight: bold; font-size: 7.5px; }
        .grades-table .name-col { text-align: left; white-space: nowrap; }
        .grades-table .bold { font-weight: bold; }
        .grades-table .avg-row td { background-color: #e5e7eb; font-weight: bold; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; }

        /* Bottom Section */
        .bottom-table { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .bottom-table > tbody > tr > td { vertical-align: top; border: none; padding: 0 2px; }

        .box { border: 1px solid #555; padding: 3px 4px; margin-bottom: 2px; }
        .box-title { font-weight: bold; font-size: 7px; border-bottom: 0.5px solid #aaa; margin-bottom: 2px; padding-bottom: 1px; }

        /* Scale */
        .scale-table { width: 100%; border-collapse: collapse; }
        .scale-table td { padding: 1px 3px; font-size: 6px; border: none; }
        .scale-table .sc-label { font-weight: bold; width: 14px; }

        /* Distribution */
        .dist-table { width: 100%; border-collapse: collapse; }
        .dist-table th, .dist-table td { border: 0.5px solid #aaa; padding: 1.5px 4px; text-align: center; font-size: 6px; }
        .dist-table th { background-color: #e5e7eb; font-weight: bold; }

        /* Chart */
        .chart-table { width: 100%; border-collapse: collapse; }
        .chart-table td { border: none; text-align: center; vertical-align: bottom; padding: 0 2px; }
        .chart-table .bar-cell { border-left: 0.5px solid #000; border-bottom: 0.5px solid #000; height: 34px; }
        .bar-rect { width: 80%; margin: 0 auto; display: block; }
        .bar-da { background-color: #22c55e; }
        .bar-aa { background-color: #3b82f6; }
        .bar-pa { background-color: #f59e0b; }
        .bar-na { background-color: #ef4444; }
        .chart-val { font-size: 5px; color: #333; }
        .chart-lbl { font-size: 6px; font-weight: bold; }

        /* Signature */
        .signature-table { width: 40%; border-collapse: collapse; margin-top: 25px; margin-left: auto; margin-right: 0; }
        .signature-table td { border: none; text-align: center; vertical-align: bottom; padding: 0 10px; }
        .sig-line { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; font-size: 7px; }
        .sig-label { font-size: 6px; color: #333; }

        /* Footer */
        .footer-fixed { position: fixed; bottom: 0; left: 0; right: 0; width: 100%; font-size: 5.5px; color: #666; text-align: center; border-top: 0.5px solid #ccc; padding-top: 3px; margin: 0; }
        .page-content { margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="page-content">
        {{-- HEADER --}}
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

        <div class="title">{{ __('REPORTE DE CALIFICACIONES') }}</div>

        <table class="info-table">
            <tr>
                <td class="info-label">Asignatura:</td><td>{{ $subjectName }}</td>
                <td class="info-label">Grado/Curso:</td><td>{{ $gradeName }}</td>
                <td class="info-label">Trimestre:</td><td>{{ $trimesterName }}</td>
                <td class="info-label">Docente:</td><td>{{ $teacherName }}</td>
            </tr>
            <tr>
                <td class="info-label">Jornada:</td><td>{{ $shiftName }}</td>
                <td class="info-label">Año Lectivo:</td><td>{{ $yearName }}</td>
                <td class="info-label">Total Est.:</td><td>{{ $students->count() }}</td>
            </tr>
        </table>

        @php
            $formativePct = $gradingScheme->formative_percentage ?? 70;
            $examPct = $gradingScheme->exam_percentage ?? 20;
            $projectPct = $gradingScheme->project_percentage ?? 10;
            $sumativaPct = $examPct + $projectPct;
            $allTotals = [];
            $scaleCounts = ['DA' => 0, 'AA' => 0, 'PA' => 0, 'NA' => 0];
        @endphp

        <table class="grades-table">
            <thead>
                <tr>
                    <th style="width:15px;">N°</th>
                    <th class="name-col">NOMINA</th>
                    <th style="width:16%;">FORMATIVA<br><span style="font-size:5.5px;font-weight:normal;">NOTA {{ $formativePct }}%</span></th>
                    <th style="width:16%;">SUMATIVA<br><span style="font-size:5.5px;font-weight:normal;">NOTA {{ $sumativaPct }}%</span></th>
                    <th style="width:28px;">NOTA</th>
                    <th style="width:22px;">CUALIT.</th>
                    <th style="width:22%;">OBSERVACIÓN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($students as $idx => $student)
                    @php
                        $blockAverages = [];
                        foreach ($blocks as $block) {
                            $totalActivities = $block->activities->count();
                            if ($totalActivities === 0) continue;
                            $total = 0;
                            foreach ($block->activities as $activity) {
                                $grade = $activity->grades->firstWhere('student_id', $student->id);
                                if ($grade && $grade->grade !== null) { $total += $grade->grade; }
                            }
                            $blockAverages[] = $total / $totalActivities;
                        }
                        $formativeAvg = count($blockAverages) > 0
                            ? floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100
                            : null;

                        $examGrade = $exams->get($student->id)?->grade;
                        $projectGrade = $projects->get($student->id)?->grade;

                        $formativeWeighted = $formativeAvg !== null ? $formativeAvg * ($formativePct / 100) : 0;
                        $examWeighted = $examGrade !== null ? $examGrade * ($examPct / 100) : 0;
                        $projectWeighted = $projectGrade !== null ? $projectGrade * ($projectPct / 100) : 0;
                        $sumativaWeighted = $examWeighted + $projectWeighted;

                        $totalValue = $formativeWeighted + $examWeighted + $projectWeighted;
                        $hasData = $formativeAvg !== null || $examGrade !== null || $projectGrade !== null;
                        $total = $hasData ? round($totalValue, 2) : null;

                        $qualitative = match(true) {
                            $total === null => '—',
                            $total >= 9.00 => 'DA',
                            $total >= 7.00 => 'AA',
                            $total >= 4.01 => 'PA',
                            default => 'NA',
                        };

                        $missingItems = [];
                        if ($examGrade === null) { $missingItems[] = 'S/EXAMEN'; }
                        if ($projectGrade === null) { $missingItems[] = 'S/PROY'; }
                        $observation = implode(', ', $missingItems);

                        $showNota = $total !== null && count($missingItems) === 0;

                        if ($total !== null) {
                            $allTotals[] = $total;
                            $scaleCounts[$qualitative]++;
                        }

                        $rowBg = ($showNota && $total < 7) ? 'background-color:#fecaca;' : '';
                    @endphp
                    <tr style="{{ $rowBg }}">
                        <td>{{ $idx + 1 }}</td>
                        <td class="name-col">{{ $student->user->full_name ?? trim(($student->user->lastname ?? '') . ' ' . ($student->user->name ?? '')) }}</td>
                        <td>{{ $formativeAvg !== null ? number_format($formativeWeighted, 2) : '—' }}</td>
                        <td>{{ $sumativaWeighted > 0 ? number_format($sumativaWeighted, 2) : '—' }}</td>
                        <td class="bold">{{ $showNota ? number_format($total, 2) : '—' }}</td>
                        <td class="bold">{{ $qualitative }}</td>
                        <td style="font-size:6px; color:#991b1b; font-weight:bold;">{{ $observation }}</td>
                    </tr>
                @endforeach
                @php
                    $classAvg = count($allTotals) > 0 ? round(array_sum($allTotals) / count($allTotals), 2) : null;
                    $classAvgQual = match(true) {
                        $classAvg === null => '—',
                        $classAvg >= 9.00 => 'DA',
                        $classAvg >= 7.00 => 'AA',
                        $classAvg >= 4.01 => 'PA',
                        default => 'NA',
                    };
                @endphp
                <tr class="avg-row">
                    <td></td>
                    <td class="name-col">PROMEDIO DE CURSO</td>
                    <td></td>
                    <td></td>
                    <td>{{ $classAvg !== null ? number_format($classAvg, 2) : '—' }}</td>
                    <td>{{ $classAvgQual }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        @php
            $totalStudents = array_sum($scaleCounts);
            $pcts = $totalStudents > 0
                ? array_map(fn($c) => round($c * 100 / $totalStudents, 1), $scaleCounts)
                : ['DA' => 0, 'AA' => 0, 'PA' => 0, 'NA' => 0];
            $maxPct = max(array_values($pcts));
        @endphp

        <table class="bottom-table">
            <tr>
                <td style="width:34%;">
                    <div class="box">
                        <div class="box-title">ESCALA (Art. 26 R-LOEI)</div>
                        <table class="scale-table">
                            <tr><td class="sc-label">DA</td><td>Domina los aprendizajes</td><td style="text-align:right;">9,00 – 10,00</td></tr>
                            <tr><td class="sc-label">AA</td><td>Alcanza los aprendizajes</td><td style="text-align:right;">7,00 – 8,99</td></tr>
                            <tr><td class="sc-label">PA</td><td>Esta proximo a alcanzar</td><td style="text-align:right;">4,01 – 6,99</td></tr>
                            <tr><td class="sc-label">NA</td><td>No alcanza los aprendizajes</td><td style="text-align:right;">&lt; 4,00</td></tr>
                        </table>
                    </div>
                </td>
                <td style="width:33%;">
                    <div class="box">
                        <div class="box-title">DISTRIBUCION POR ESCALA</div>
                        <table class="dist-table">
                            <tr><th>DA</th><th>AA</th><th>PA</th><th>NA</th><th>TOTAL</th></tr>
                            <tr>
                                <td>{{ $scaleCounts['DA'] }}<br>({{ number_format($pcts['DA'], 1) }}%)</td>
                                <td>{{ $scaleCounts['AA'] }}<br>({{ number_format($pcts['AA'], 1) }}%)</td>
                                <td>{{ $scaleCounts['PA'] }}<br>({{ number_format($pcts['PA'], 1) }}%)</td>
                                <td>{{ $scaleCounts['NA'] }}<br>({{ number_format($pcts['NA'], 1) }}%)</td>
                                <td>{{ $totalStudents }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width:33%;">
                    <div class="box">
                        <div class="box-title">GRAFICO DE ESCALAS</div>
                        <table class="chart-table">
                            <tr>
                                <td class="chart-val">{{ number_format($pcts['DA'], 1) }}%</td>
                                <td class="chart-val">{{ number_format($pcts['AA'], 1) }}%</td>
                                <td class="chart-val">{{ number_format($pcts['PA'], 1) }}%</td>
                                <td class="chart-val">{{ number_format($pcts['NA'], 1) }}%</td>
                            </tr>
                            <tr>
                                @php $colors = ['DA' => 'bar-da', 'AA' => 'bar-aa', 'PA' => 'bar-pa', 'NA' => 'bar-na']; @endphp
                                @foreach($colors as $label => $color)
                                    @php $barH = $maxPct > 0 ? round($pcts[$label] / $maxPct * 28) : 0; @endphp
                                    <td class="bar-cell" style="height:34px;vertical-align:bottom;">
                                        <div class="bar-rect {{ $color }}" style="height:{{ $barH }}px;">&nbsp;</div>
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($colors as $label => $color)
                                    <td class="chart-lbl">{{ $label }}</td>
                                @endforeach
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="signature-table">
            <tr>
                <td>
                    <div class="sig-line">{{ $teacherName }}</div>
                    <div class="sig-label">{{ __('Docente') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-fixed">
        Generado por {{ config('app.name') }} el {{ $generatedAt }} · {{ $gradeName }} · {{ $shiftName }} · {{ $subjectName }}
    </div>
</body>
</html>
