<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sync\SyncPullRequest;
use App\Http\Requests\Api\V1\Sync\SyncPushRequest;
use App\Models\Identity\Users\Teacher;
use App\Services\Api\V1\Sync\SyncService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Motor de sincronización offline-first: push del outbox del cliente y
 * pull incremental por colecciones (synchronization.md §3-§4).
 */
final class SyncController extends Controller
{
    public function __construct(private readonly SyncService $syncService) {}

    public function push(SyncPushRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $data = $this->syncService->push(
            $teacher,
            (string) $request->validated('device_id'),
            (array) $request->validated('operations'),
        );

        return ApiResponse::success(data: $data);
    }

    public function pull(SyncPullRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $collections = Collection::make(explode(',', (string) $request->validated('collections', 'attendance,gradebook')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        $data = $this->syncService->pull(
            $teacher,
            $collections,
            $request->validated('cursor'),
            (int) $request->validated('limit', 500),
        );

        return ApiResponse::success(data: $data);
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
