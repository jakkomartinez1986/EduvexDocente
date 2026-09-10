<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

/**
 * Procesador Monolog que redacta valores asociados a claves sensibles en los
 * registros de contexto y extra, antes de que el formatter serialice.
 *
 * Prevents credenciales de canales, tokens, contraseñas y claves de API de
 * terminar en logs o en el stream de stderr (security-audit.md §3: habilitar
 * redacción en config/logging.php). La lista de claves se puede ampliar vía
 * configuración `logging.redaction`.
 */
class RedactSensitiveProcessor
{
    private const SECRET_PLACEHOLDER = '[REDACTED]';

    /**
     * @var list<string>
     */
    private const DEFAULT_SENSITIVE_KEYS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'authorization',
        'credential',
        'api_key',
        'apikey',
        'api-key',
        'access_key',
        'private_key',
        'client_secret',
        'db_password',
        'app_key',
        'recovery_code',
        'bearer',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $keys = array_merge(
            self::DEFAULT_SENSITIVE_KEYS,
            config('logging.redaction', []),
        );

        return $record->with(
            context: $this->redact($record->context, $keys),
            extra: $this->redact($record->extra, $keys),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $keys
     * @return array<mixed>
     */
    private function redact(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key, $keys)) {
                $data[$key] = self::SECRET_PLACEHOLDER;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value, $keys);
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $keys
     */
    private function isSensitiveKey(string $key, array $keys): bool
    {
        $lower = strtolower($key);

        foreach ($keys as $sensitive) {
            if ($sensitive !== '' && str_contains($lower, strtolower($sensitive))) {
                return true;
            }
        }

        return false;
    }
}
