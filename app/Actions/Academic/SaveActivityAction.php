<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;

final class SaveActivityAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(
        ?int $editingActivityId,
        array $data,
    ): Activity {
        if ($editingActivityId) {
            $activity = Activity::findOrFail($editingActivityId);
            $activity->update($data);

            return $activity->refresh();
        }

        return Activity::create($data);
    }
}
