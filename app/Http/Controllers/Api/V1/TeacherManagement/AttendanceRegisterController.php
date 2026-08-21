<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TeacherManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TeacherManagement\AttendanceRegisterRequest;
use App\Http\Requests\Api\V1\TeacherManagement\AttendanceRegisterShowRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\TeacherManagement\AttendanceRegistrationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Registro de asistencias por clase del docente.
 */
final class AttendanceRegisterController extends Controller
{
    public function __construct(private readonly AttendanceRegistrationService $attendanceRegistrationService) {}

    public function show(AttendanceRegisterShowRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: $this->attendanceRegistrationService->detail($teacher, $request->validated()),
        );
    }

    public function update(AttendanceRegisterRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        return ApiResponse::success(
            data: $this->attendanceRegistrationService->register($teacher, $request->validated()),
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
