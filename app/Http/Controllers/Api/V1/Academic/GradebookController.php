<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Academic\GradebookIndexRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Academic\GradebookDownloadService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Descarga del libro de calificaciones del docente para trabajo offline.
 */
final class GradebookController extends Controller
{
    public function __construct(private readonly GradebookDownloadService $gradebookDownloadService) {}

    public function index(GradebookIndexRequest $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            return ApiResponse::error(
                message: 'El usuario autenticado no tiene un perfil de docente.',
                status: 403,
            );
        }

        return ApiResponse::success(
            data: $this->gradebookDownloadService->download($teacher, $request->validated()),
        );
    }
}
