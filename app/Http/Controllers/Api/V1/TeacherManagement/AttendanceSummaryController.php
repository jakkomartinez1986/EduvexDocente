<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TeacherManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TeacherManagement\AttendanceSummaryIndexRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\TeacherManagement\AttendanceSummaryService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resumen de asistencias por período del docente.
 */
final class AttendanceSummaryController extends Controller
{
    public function __construct(private readonly AttendanceSummaryService $attendanceSummaryService) {}

    public function index(AttendanceSummaryIndexRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: $this->attendanceSummaryService->summary($teacher, $request->validated()),
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
