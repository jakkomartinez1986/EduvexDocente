<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;

final class SaveBlockAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(
        ?int $editingBlockId,
        array $data,
    ): AssessmentBlock {
        if ($editingBlockId) {
            $block = AssessmentBlock::findOrFail($editingBlockId);
            $block->update($data);

            return $block->refresh();
        }

        return AssessmentBlock::create($data);
    }
}
