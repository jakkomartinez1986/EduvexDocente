<?php

namespace App\Console\Commands;

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use Database\Seeders\BasicDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('seed:basic-data')]
#[Description('Siembra los datos básicos de la institución (escuela, año lectivo, trimestres, esquema de calificación, turnos, niveles, grados, áreas y materias) SIN crear usuarios. Idempotente: seguro de ejecutar como comando de deploy en Laravel Cloud.')]
class SeedBasicData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BasicDataSeeder $seeder): int
    {
        DB::transaction(fn () => $seeder->run());

        $this->info('Datos básicos listos:');
        $this->info(sprintf('  Escuela: %d', School::count()));
        $this->info(sprintf('  Años lectivos: %d', ScolarYear::count()));
        $this->info(sprintf('  Periodos académicos: %d', AcademicPeriod::count()));
        $this->info(sprintf('  Esquemas de calificación: %d', GradingScheme::count()));
        $this->info(sprintf('  Turnos: %d', Shift::count()));
        $this->info(sprintf('  Niveles: %d', Nivel::count()));
        $this->info(sprintf('  Grados: %d', Grade::count()));
        $this->info(sprintf('  Áreas: %d', Area::count()));
        $this->info(sprintf('  Materias: %d', Subject::count()));
        $this->info('No se crearon usuarios, roles ni permisos.');

        return self::SUCCESS;
    }
}
