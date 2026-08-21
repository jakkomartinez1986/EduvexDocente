<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TeacherManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TeacherManagement\ScheduleIndexRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\TeacherManagement\ScheduleService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Consulta del horario del docente.
 */
final class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    public function index(ScheduleIndexRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: $this->scheduleService->index($teacher, $request->validated()),
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
