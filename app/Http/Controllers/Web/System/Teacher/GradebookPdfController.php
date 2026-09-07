<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\System\Teacher\Concerns\DispatchesAsyncPdfReports;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Services\Reports\GradebookPdfService;
use Illuminate\Http\Request;

/**
 * Reportes PDF del gradebook y del tutor (C-04): los endpoint validan los
 * parámetros, reproducen los abort(404) del flujo síncrono y delegan el
 * render pesado a la cola (GeneratePdfReport + GradebookPdfService). La
 * respuesta es una página de espera que refresca hasta que el Object Storage
 * esté listo y redirige a la URL firmada.
 */
class GradebookPdfController extends Controller
{
    use DispatchesAsyncPdfReports;

    public function formative(Request $request)
    {
        return $this->dispatchSubject($request, GradebookPdfService::FORMATIVE, 'Reporte de notas formativas');
    }

    public function summative(Request $request)
    {
        return $this->dispatchSubject($request, GradebookPdfService::SUMMATIVE, 'Reporte de notas sumativas');
    }

    public function qualitativeReport(Request $request)
    {
        return $this->dispatchSubject($request, GradebookPdfService::QUALITATIVE, 'Reporte cualitativo');
    }

    public function subjectAnnualReport(Request $request)
    {
        $validated = $this->castIds($request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
        ]));

        return $this->dispatchGradebook(
            GradebookPdfService::SUBJECT_ANNUAL,
            $validated + $this->teacherContext(),
            'Informe anual de asignatura',
        );
    }

    public function supletorioReport(Request $request)
    {
        $validated = $this->castIds($request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
        ]));

        return $this->dispatchGradebook(
            GradebookPdfService::SUPLETORIO,
            $validated + $this->teacherContext(),
            'Reporte de supletorio',
        );
    }

    public function tutorStudentReport(Request $request)
    {
        return $this->dispatchStudent($request, GradebookPdfService::TUTOR_STUDENT, 'Reporte de notas del estudiante');
    }

    public function tutorStudentReportByTrimester(Request $request)
    {
        return $this->dispatchStudentTrimester($request, GradebookPdfService::TUTOR_STUDENT_TRI, 'Reporte de notas por trimestre');
    }

    public function tutorStudentFormativeByTrimester(Request $request)
    {
        return $this->dispatchStudentTrimester($request, GradebookPdfService::TUTOR_FORMATIVE_TRI, 'Reporte de formativas por trimestre');
    }

    public function tutorAllStudentsTrimesterReport(Request $request)
    {
        $validated = $this->castIds($request->validate([
            'trimester_id' => ['required', 'integer'],
        ]));

        return $this->dispatchGradebook(
            GradebookPdfService::TUTOR_ALL_TRI,
            $validated + $this->teacherContext(),
            'Reporte de notas de todos los estudiantes',
        );
    }

    public function studentTrimesterReport(Request $request)
    {
        return $this->dispatchStudentTrimester($request, GradebookPdfService::STUDENT_TRI, 'Reporte de trimestre del estudiante');
    }

    public function studentAnnualReport(Request $request)
    {
        return $this->dispatchStudent($request, GradebookPdfService::STUDENT_ANNUAL, 'Reporte anual del estudiante');
    }

    private function dispatchSubject(Request $request, string $type, string $title)
    {
        $validated = $this->castIds($request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
            'student_id' => ['nullable', 'integer'],
        ]));

        $period = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $period || $period->is_supletorio, 404, __('No se encontró el trimestre.'));

        return $this->dispatchGradebook($type, $validated + $this->teacherContext(), $title);
    }

    private function dispatchStudent(Request $request, string $type, string $title)
    {
        $validated = $this->castIds($request->validate([
            'student_id' => ['required', 'integer'],
        ]));

        return $this->dispatchGradebook($type, $validated + $this->teacherContext(), $title);
    }

    private function dispatchStudentTrimester(Request $request, string $type, string $title)
    {
        $validated = $this->castIds($request->validate([
            'student_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
        ]));

        return $this->dispatchGradebook($type, $validated + $this->teacherContext(), $title);
    }

    private function dispatchGradebook(string $type, array $context, string $title)
    {
        app(GradebookPdfService::class)->assertDispatchable($type, $context);

        return $this->asyncPdf($type, null, $context, $title);
    }

    /**
     * @return array{teacher_id: int|null, teacher_name: string}
     */
    private function teacherContext(): array
    {
        return [
            'teacher_id' => auth()->user()->teacher?->id,
            'teacher_name' => auth()->user()?->fullname ?? '',
        ];
    }

    /**
     * Los inputs validados llegan como strings del request; el contexto del job
     * debe transportar identificadores enteros para las comparaciones estrictas.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function castIds(array $validated): array
    {
        foreach (['subject_id', 'grade_id', 'trimester_id', 'student_id'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $validated[$key] = (int) $validated[$key];
            }
        }

        return $validated;
    }
}
