<?php

use App\Actions\TeacherManagement\SaveQuickGradesAction;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;

it('guarda las notas en lote y reasigna sin duplicar filas', function (): void {
    $context = syncGradebookContext();
    [$a, $b] = $context['students'];

    app(SaveQuickGradesAction::class)->handle(
        activityId: $context['activity']->id,
        values: [$a->id => '8.5', $b->id => ''],
        userId: $context['teacher']->user_id,
    );

    expect(ActivityGrade::query()->where('activity_id', $context['activity']->id)->count())->toBe(2);

    $gradeA = ActivityGrade::query()->where('activity_id', $context['activity']->id)->where('student_id', $a->id)->first();
    $gradeB = ActivityGrade::query()->where('activity_id', $context['activity']->id)->where('student_id', $b->id)->first();

    expect($gradeA->grade)->toEqual(8.5);
    expect($gradeA->recorded_by)->toBe($context['teacher']->user_id);
    expect($gradeB->grade)->toBeNull();

    app(SaveQuickGradesAction::class)->handle(
        activityId: $context['activity']->id,
        values: [$a->id => '9.0', $b->id => '7'],
        userId: $context['teacher']->user_id,
    );

    expect(ActivityGrade::query()->where('activity_id', $context['activity']->id)->count())->toBe(2);
    expect(ActivityGrade::query()->where('activity_id', $context['activity']->id)->where('student_id', $a->id)->value('grade'))->toEqual(9.0);
});

it('acota la nota al rango 0-10 y trata vacio como null', function (): void {
    $context = syncGradebookContext();
    [$a, $b, $c] = $context['students'];

    app(SaveQuickGradesAction::class)->handle(
        activityId: $context['activity']->id,
        values: [$a->id => '12', $b->id => '-3', $c->id => ''],
        userId: $context['teacher']->user_id,
    );

    expect(ActivityGrade::query()->where('student_id', $a->id)->value('grade'))->toEqual(10.0);
    expect(ActivityGrade::query()->where('student_id', $b->id)->value('grade'))->toEqual(0.0);
    expect(ActivityGrade::query()->where('student_id', $c->id)->value('grade'))->toBeNull();
});
