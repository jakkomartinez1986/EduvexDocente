<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\User;
use Database\Seeders\BasicDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra datos básicos sin crear usuarios', function (): void {
    $this->seed(BasicDataSeeder::class);

    expect(User::count())->toBe(0)
        ->and(School::count())->toBe(1)
        ->and(ScolarYear::count())->toBe(1)
        ->and(AcademicPeriod::count())->toBe(4)
        ->and(GradingScheme::count())->toBe(1)
        ->and(Shift::count())->toBe(3)
        ->and(Nivel::count())->toBe(27)
        ->and(Grade::count())->toBe(315)
        ->and(Area::count())->toBe(14)
        ->and(Subject::count())->toBe(74);
});

it('es idempotente y no duplica registros al ejecutarse de nuevo', function (): void {
    $this->seed(BasicDataSeeder::class);
    $this->seed(BasicDataSeeder::class);

    expect(User::count())->toBe(0)
        ->and(Grade::count())->toBe(315)
        ->and(Subject::count())->toBe(74)
        ->and(AcademicPeriod::count())->toBe(4);
});

it('se ejecuta a través del comando seed:basic-data', function (): void {
    $this->artisan('seed:basic-data')->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(Grade::count())->toBe(315)
        ->and(Subject::count())->toBe(74);
});
