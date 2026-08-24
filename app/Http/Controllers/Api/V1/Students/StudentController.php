<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Students\StudentIndexRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Students\StudentService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * GET /students — estudiantes de los grados asignados al docente
 * (DTO mínimo §3.2, paginación por cursor D-07).
 */
final class StudentController extends Controller
{
    public function __construct(private readonly StudentService $studentService) {}

    public function index(StudentIndexRequest $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            throw new AccessDeniedHttpException('El usuario autenticado no tiene un perfil de docente.');
        }

        $result = $this->studentService->paginated($teacher, $request->gradeId());

        return ApiResponse::success(
            data: $result['items'],
            meta: [
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
            ],
        );
    }
}
