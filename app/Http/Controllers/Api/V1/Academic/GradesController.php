<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\GradebookViewRequest;
use App\Http\Requests\Api\V1\Academic\StoreActivityGradesRequest;
use App\Http\Requests\Api\V1\Academic\StoreActivityRequest;
use App\Http\Requests\Api\V1\Academic\StoreAssessmentBlockRequest;
use App\Http\Requests\Api\V1\Academic\StoreRecoveryRequest;
use App\Http\Requests\Api\V1\Academic\StoreSummativeGradesRequest;
use App\Http\Requests\Api\V1\Academic\StoreSupplementaryGradesRequest;
use App\Http\Requests\Api\V1\Academic\UpdateActivityRequest;
use App\Http\Resources\Api\V1\Academic\ActivityRecoveryResource;
use App\Http\Resources\Api\V1\Academic\ActivityResource;
use App\Http\Resources\Api\V1\Academic\AssessmentBlockResource;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityRecovery;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Academic\GradebookService;
use App\Services\Api\V1\Academic\GradeRegistrationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * API transaccional del libro de calificaciones del docente.
 */
final class GradesController extends Controller
{
    public function __construct(
        private readonly GradebookService $gradebookService,
        private readonly GradeRegistrationService $gradeRegistrationService,
    ) {}

    public function index(GradebookViewRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: $this->gradebookService->view($teacher, $request->validated()),
        );
    }

    public function storeBlock(StoreAssessmentBlockRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['block' => new AssessmentBlockResource($this->gradeRegistrationService->storeBlock($teacher, $request->validated()))],
            status: 201,
        );
    }

    public function storeActivity(StoreActivityRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['activity' => new ActivityResource($this->gradeRegistrationService->storeActivity($teacher, $request->validated()))],
            status: 201,
        );
    }

    public function updateActivity(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['activity' => new ActivityResource($this->gradeRegistrationService->updateActivity($teacher, $activity, $request->validated()))],
        );
    }

    public function storeActivityGrades(StoreActivityGradesRequest $request, Activity $activity): JsonResponse
    {
        $teacher = $this->teacher($request);

        $updated = $this->gradeRegistrationService->storeActivityGrades($teacher, $activity, $request->validated('grades'));

        return ApiResponse::success(
            data: ['updated' => $updated],
        );
    }

    public function storeExams(StoreSummativeGradesRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['updated' => $this->gradeRegistrationService->storeSummative($teacher, 'exam', $request->validated())],
        );
    }

    public function storeProjects(StoreSummativeGradesRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['updated' => $this->gradeRegistrationService->storeSummative($teacher, 'project', $request->validated())],
        );
    }

    public function storeSupplementary(StoreSupplementaryGradesRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['updated' => $this->gradeRegistrationService->storeSupplementary($teacher, $request->validated())],
        );
    }

    public function storeRecovery(StoreRecoveryRequest $request, Activity $activity): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: ['recovery' => new ActivityRecoveryResource($this->gradeRegistrationService->registerRecovery($teacher, $activity, $request->validated()))],
            status: 201,
        );
    }

    public function applyRecovery(Request $request, ActivityRecovery $recovery): JsonResponse
    {
        $teacher = $this->teacher($request);

        $this->gradeRegistrationService->applyRecovery($teacher, $recovery);

        return ApiResponse::success(
            data: ['recovery' => new ActivityRecoveryResource($recovery->refresh())],
        );
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
