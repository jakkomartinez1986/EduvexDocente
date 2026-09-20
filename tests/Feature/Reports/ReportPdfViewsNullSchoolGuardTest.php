<?php

it('blinda el distrit del encabezado contra una escuela nula en todas las vistas de reportes PDF', function (string $view): void {
    $source = file_get_contents(resource_path('views/pdf/'.$view));

    expect($source)
        ->toContain('@if($school && $school->distrit)')
        ->and($source)->not->toContain('@if($school->distrit)');
})->with([
    'gradebook-formative' => ['gradebook-formative.blade.php'],
    'gradebook-summative' => ['gradebook-summative.blade.php'],
    'qualitative-report' => ['qualitative-report.blade.php'],
    'subject-annual-report' => ['subject-annual-report.blade.php'],
    'supletorio-report' => ['supletorio-report.blade.php'],
    'tutor-student-report' => ['tutor-student-report.blade.php'],
    'tutor-student-report-trimester' => ['tutor-student-report-trimester.blade.php'],
    'tutor-student-formative-trimester' => ['tutor-student-formative-trimester.blade.php'],
    'tutor-all-students-trimester' => ['tutor-all-students-trimester.blade.php'],
    'student-trimester-report' => ['student-trimester-report.blade.php'],
    'student-annual-report' => ['student-annual-report.blade.php'],
]);
