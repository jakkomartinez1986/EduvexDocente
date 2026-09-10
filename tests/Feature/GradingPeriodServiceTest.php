<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Services\Academic\GradingPeriodService;
use Illuminate\Support\Collection;

function gradingPeriodPayload(Collection $periods): Collection
{
    return $periods->map(fn (AcademicPeriod $p) => [
        'id' => $p->id,
        'is_supletorio' => $p->is_supletorio,
    ]);
}

function gradeClosedPeriod(string $name, int $yearId): AcademicPeriod
{
    return AcademicPeriod::create([
        'year_id' => $yearId,
        'trimester_name' => $name,
        'start_date' => now()->subDays(90)->toDateString(),
        'end_date' => now()->subDays(10)->toDateString(),
        'grading_open_date' => now()->subDays(90)->toDateString(),
        'grading_close_date' => now()->subDays(5)->toDateString(),
        'is_supletorio' => false,
        'status' => 1,
    ]);
}

function gradeOpenPeriod(string $name, int $yearId): AcademicPeriod
{
    return AcademicPeriod::create([
        'year_id' => $yearId,
        'trimester_name' => $name,
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->addDays(60)->toDateString(),
        'grading_open_date' => now()->subDays(30)->toDateString(),
        'grading_close_date' => now()->addDays(60)->toDateString(),
        'is_supletorio' => false,
        'status' => 1,
    ]);
}

it('devuelve false con menos de 3 trimestres regulares', function (): void {
    $context = academicContext();
    $period = gradeOpenPeriod('Primer Trimestre', (int) $context['year']->id);

    $result = app(GradingPeriodService::class)
        ->isSupletorioAvailable(gradingPeriodPayload(collect([$period])));

    expect($result)->toBeFalse();
});

it('devuelve true cuando los 3 trimestres regulares ya cerraron calificación', function (): void {
    $yearId = (int) academicContext()['year']->id;
    $periods = collect([
        gradeClosedPeriod('Primer Trimestre', $yearId),
        gradeClosedPeriod('Segundo Trimestre', $yearId),
        gradeClosedPeriod('Tercer Trimestre', $yearId),
    ]);

    $result = app(GradingPeriodService::class)
        ->isSupletorioAvailable(gradingPeriodPayload($periods));

    expect($result)->toBeTrue();
});

it('devuelve false cuando un trimestre regular sigue en calificación', function (): void {
    $yearId = (int) academicContext()['year']->id;
    $mixed = collect([
        gradeClosedPeriod('Primer Trimestre', $yearId),
        gradeClosedPeriod('Segundo Trimestre', $yearId),
        gradeOpenPeriod('Tercer Trimestre', $yearId),
    ]);

    $result = app(GradingPeriodService::class)
        ->isSupletorioAvailable(gradingPeriodPayload($mixed));

    expect($result)->toBeFalse();
});

it('ejecuta solo 1 consulta batch para resolver los períodos', function (): void {
    $yearId = (int) academicContext()['year']->id;
    $periods = collect([
        gradeClosedPeriod('Primer Trimestre', $yearId),
        gradeClosedPeriod('Segundo Trimestre', $yearId),
        gradeClosedPeriod('Tercer Trimestre', $yearId),
    ]);
    $service = app(GradingPeriodService::class);

    DB::enableQueryLog();
    $service->isSupletorioAvailable(gradingPeriodPayload($periods));
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(1)
        ->and($service->isSupletorioAvailable(gradingPeriodPayload($periods)))->toBeTrue();
});
