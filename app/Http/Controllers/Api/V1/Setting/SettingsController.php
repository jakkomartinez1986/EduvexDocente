<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Setting;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\Setting\OfflinePackageService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paquete de configuración descargable para clientes offline.
 */
final class SettingsController extends Controller
{
    public function __construct(private readonly OfflinePackageService $offlinePackageService) {}

    public function bootstrap(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->offlinePackageService->build($request->user()),
        );
    }
}
