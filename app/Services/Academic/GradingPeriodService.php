<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\Setting\YearSettings\AcademicPeriod;
use Illuminate\Support\Collection;

final class GradingPeriodService
{
    public function isGradingOpen(
        ?int $trimesterId,
        ?string $activeBlockId,
        bool $isQualitativeSubject,
        ?bool $isSupletorioAvailable,
    ): bool {
        if ($isQualitativeSubject) {
            if (! $trimesterId || $trimesterId === -1) {
                return false;
            }
            $period = AcademicPeriod::find($trimesterId);

            return $period ? $period->isGradingOpen() : false;
        }

        if ($activeBlockId === '_supletorios') {
            return $isSupletorioAvailable ?? false;
        }

        if (! $trimesterId || $trimesterId === -1) {
            return false;
        }

        $period = AcademicPeriod::find($trimesterId);
        if (! $period) {
            return false;
        }

        return $period->isGradingOpen();
    }

    /**
     * @param  Collection<int, array{id: int, is_supletorio: bool}>  $trimesters
     */
    public function isSupletorioAvailable(Collection $trimesters): bool
    {
        $regularTrimesters = $trimesters->filter(fn ($t) => ! ($t['is_supletorio'] ?? false));

        if ($regularTrimesters->count() < 3) {
            return false;
        }

        foreach ($regularTrimesters as $t) {
            $period = AcademicPeriod::find($t['id']);
            if ($period && ! $period->isGradingPast()) {
                return false;
            }
        }

        return true;
    }

    public function isSumativaAvailable(int $trimesterId): bool
    {
        $period = AcademicPeriod::find($trimesterId);
        if (! $period) {
            return false;
        }

        return $period->isGradingPast() || $period->isGradingOpen();
    }
}
