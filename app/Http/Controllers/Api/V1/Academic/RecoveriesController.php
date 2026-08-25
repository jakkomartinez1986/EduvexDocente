<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\ListAppliedRecoveriesRequest;
use App\Http\Requests\Api\V1\Academic\ListRecoverableRequest;
use App\Http\Requests\Api\V1\Academic\StoreExamRecoveryRequest;
use App\Http\Resources\Api\V1\Academic\ExamRecoveryResource;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Academic\GradeBook\Summaries\Supplementary\ExamRecovery;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Academic\RecoveriesService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Módulo de recuperaciones del docente:
 * - GET  /recoveries/recoverable            → estudiantes recuperables (actividad o examen).
 * - GET  /recoveries/applied                → historial de aplicadas por trimestre.
 * - POST /recoveries/exams                  → registra recuperación del examen.
 * - POST /recoveries/exams/{id}/apply       → aplica recuperación del examen.
 * - DELETE /recoveries/exams/{id}           → elimina recuperación del examen no aplicada.
 * - DELETE /recoveries/activities/{id}      → elimina recuperación de actividad no aplicada.
 */
final class RecoveriesController extends Controller
{
    public function __construct(
        private readonly RecoveriesService $recoveriesService,
    ) {}

    public function recoverable(ListRecoverableRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->recoveriesService->recoverable($this->teacher($request), $request->validated()),
        );
    }

    public function applied(ListAppliedRecoveriesRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->recoveriesService->applied($this->teacher($request), $request->validated()),
        );
    }

    public function storeExam(StoreExamRecoveryRequest $request): JsonResponse
    {
        $recovery = $this->recoveriesService->registerExamRecovery(
            $this->teacher($request),
            $request->validated(),
        );

        return ApiResponse::success(
            data: ['exam_recovery' => new ExamRecoveryResource($recovery)],
            status: 201,
        );
    }

    public function applyExam(Request $request, ExamRecovery $examRecovery): JsonResponse
    {
        $this->recoveriesService->applyExamRecovery($this->teacher($request), $examRecovery);

        return ApiResponse::success(
            data: ['exam_recovery' => new ExamRecoveryResource($examRecovery->refresh())],
        );
    }

    public function destroyExam(Request $request, ExamRecovery $examRecovery): JsonResponse
    {
        $this->recoveriesService->destroyExamRecovery($this->teacher($request), $examRecovery);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    public function destroyActivity(Request $request, ActivityRecovery $activityRecovery): JsonResponse
    {
        $this->recoveriesService->destroyActivityRecovery($this->teacher($request), $activityRecovery);

        return ApiResponse::success(data: ['deleted' => true]);
    }

    private function teacher(Request $request): Teacher
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            throw new AccessDeniedHttpException('El usuario autenticado no tiene un perfil de docente.');
        }

        return $teacher;
    }
}
