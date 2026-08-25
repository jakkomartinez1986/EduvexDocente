<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\GradebookIndexRequest;
use App\Http\Requests\Api\V1\Academic\GradebookViewRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Academic\GradebookDownloadService;
use App\Services\Api\V1\Academic\GradebookService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Libro de calificaciones del docente:
 * - GET /academic/gradebook          → vista transaccional (contexto + estudiantes + bloques).
 * - GET /academic/gradebook/download → dataset completo para trabajo offline.
 */
final class GradebookController extends Controller
{
    public function __construct(
        private readonly GradebookService $gradebookService,
        private readonly GradebookDownloadService $gradebookDownloadService,
    ) {}

    public function index(GradebookViewRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->gradebookService->view($this->teacher($request), $request->validated()),
        );
    }

    public function download(GradebookIndexRequest $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->gradebookDownloadService->download($this->teacher($request), $request->validated()),
        );
    }

    private function teacher(GradebookViewRequest|GradebookIndexRequest $request): Teacher
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            throw new AccessDeniedHttpException('El usuario autenticado no tiene un perfil de docente.');
        }

        return $teacher;
    }
}
