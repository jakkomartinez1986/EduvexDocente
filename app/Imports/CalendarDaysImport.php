<?php

namespace App\Imports;

use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CalendarDaysImport implements ToCollection, WithHeadingRow
{
    protected bool $previewOnly;

    protected array $rows = [];

    protected int $totalRows = 0;

    protected int $validRows = 0;

    protected int $errorRows = 0;

    protected int $yearId;

    public function __construct(bool $previewOnly, int $yearId)
    {
        $this->previewOnly = $previewOnly;
        $this->yearId = $yearId;
    }

    public function collection(Collection $rows): void
    {
        $this->totalRows = $rows->count();

        $trimesters = AcademicPeriod::where('year_id', $this->yearId)
            ->where('is_supletorio', false)
            ->orderBy('start_date')
            ->get();

        foreach ($rows as $row) {
            $dateStr = trim($row['fecha'] ?? $row['date'] ?? '');
            $activity = trim($row['actividad'] ?? $row['activity'] ?? '');
            $isHoliday = strtolower(trim($row['feriado'] ?? $row['is_holiday'] ?? 'no'));
            $trimesterName = trim($row['trimester'] ?? $row['trimester_name'] ?? '');

            $rowData = [
                'date' => $dateStr,
                'activity' => $activity,
                'is_holiday' => $isHoliday,
                'trimester_name' => $trimesterName,
            ];

            try {
                if (empty($dateStr)) {
                    throw new \Exception('La fecha es obligatoria.');
                }

                if (is_numeric($dateStr)) {
                    $date = Carbon::instance(ExcelDate::excelToDateTimeObject((int) $dateStr));
                } else {
                    $formats = ['j/n/Y', 'j/n/y', 'd/m/Y', 'd/m/y', 'j-m-Y', 'j-n-Y', 'd-m-Y', 'd-m-y', 'Y-m-d', 'm/d/Y', 'm-d-Y'];
                    $date = null;
                    foreach ($formats as $format) {
                        try {
                            $date = Carbon::createFromFormat($format, $dateStr);
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    if (! $date) {
                        $date = Carbon::parse($dateStr);
                    }
                }
                $rowData['parsed_date'] = $date->format('Y-m-d');

                $dayNames = [
                    1 => 'LUNES', 2 => 'MARTES', 3 => 'MIERCOLES',
                    4 => 'JUEVES', 5 => 'VIERNES', 6 => 'SABADO', 7 => 'DOMINGO',
                ];
                $rowData['day_name'] = $dayNames[$date->dayOfWeekIso];
                $rowData['month_name'] = strtoupper($date->translatedFormat('F'));
                $rowData['day_number'] = $date->day;

                $matchedTrimester = null;
                foreach ($trimesters as $t) {
                    if ($date->between($t->start_date, $t->end_date)) {
                        $matchedTrimester = $t;
                        break;
                    }
                }
                $rowData['trimester_id'] = $matchedTrimester?->id;
                $rowData['period'] = $matchedTrimester?->trimester_name;

                if ($date->dayOfWeekIso >= 6) {
                    $rowData['is_holiday'] = true;
                } else {
                    $rowData['is_holiday'] = in_array(strtolower($isHoliday), ['si', 'sí', '1', 'true', 'yes']);
                }

                $existing = CalendarDay::where('year_id', $this->yearId)
                    ->where('date', $rowData['parsed_date'])
                    ->exists();

                $rowData['status'] = $existing ? 'actualizar' : 'nuevo';
                $this->validRows++;
            } catch (\Exception $e) {
                $rowData['errors'] = $e->getMessage();
                $this->errorRows++;
            }

            $this->rows[] = $rowData;

            if (! $this->previewOnly && empty($rowData['errors'])) {
                CalendarDay::updateOrCreate(
                    [
                        'year_id' => $this->yearId,
                        'date' => $rowData['parsed_date'],
                    ],
                    [
                        'trimester_id' => $rowData['trimester_id'],
                        'period' => $rowData['period'],
                        'month_name' => $rowData['month_name'],
                        'day_name' => $rowData['day_name'],
                        'day_number' => $rowData['day_number'],
                        'week' => $date->weekOfYear,
                        'activity' => $activity ?: null,
                        'is_holiday' => $rowData['is_holiday'],
                    ]
                );
            }
        }
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getValidRows(): int
    {
        return $this->validRows;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }
}
