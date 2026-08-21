<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TeacherManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TeacherManagement\AttendanceIndexRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\TeacherManagement\AttendanceDownloadService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Descarga de asistencias y observaciones de clase del docente para trabajo offline.
 */
final class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceDownloadService $attendanceDownloadService) {}

    public function index(AttendanceIndexRequest $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            return ApiResponse::error(
                message: 'El usuario autenticado no tiene un perfil de docente.',
                status: 403,
            );
        }

        return ApiResponse::success(
            data: $this->attendanceDownloadService->download($teacher, $request->validated()),
        );
    }
}
