<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Enums\Api\ApiVersion;
use Illuminate\Http\JsonResponse;

/**
 * Convención uniforme de respuestas de la API.
 *
 * Éxito:
 *   { "success": true, "data": {...}, "meta": {...} }
 *
 * Error:
 *   { "success": false, "message": "...", "errors": {...}|null, "meta": {...} }
 */
final class ApiResponse
{
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => self::withBaseMeta($meta),
        ], $status);
    }

    public static function error(string $message, ?array $errors = null, int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => self::withBaseMeta($meta),
        ], $status);
    }

    private static function withBaseMeta(array $meta): array
    {
        return [
            'api_version' => ApiVersion::V1->value,
            ...$meta,
        ];
    }
}
