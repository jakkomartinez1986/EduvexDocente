<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting\YearSettings\ScolarYear;

class AcademicYearService
{
    public function getActiveYear(): ?ScolarYear
    {
        return ScolarYear::where('status', true)->latest('year_name')->first();
    }

    public function getActiveYearId(): ?int
    {
        return $this->getActiveYear()?->id;
    }
}
