<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Configuration\ConfigurationRequest;
use App\Http\Resources\Api\V1\Configuration\ConfigurationResource;
use App\Services\Api\V1\Configuration\ConfigurationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Configuración de arranque del cliente autenticado.
 */
final class ConfigurationController extends Controller
{
    public function __construct(private readonly ConfigurationService $configurationService) {}

    public function index(ConfigurationRequest $request): JsonResponse
    {
        $user = $request->user();

        $content = $this->configurationService->content($user);
        $version = $this->configurationService->version($content);

        if ($request->validated('version') === $version) {
            return ApiResponse::success(
                data: null,
                meta: [
                    'configuration_version' => $version,
                    'not_modified' => true,
                ],
            )->header('ETag', $version);
        }

        return ApiResponse::success(
            data: new ConfigurationResource(
                $this->configurationService->payload($content, $version),
            ),
        )->header('ETag', $version);
    }
}
