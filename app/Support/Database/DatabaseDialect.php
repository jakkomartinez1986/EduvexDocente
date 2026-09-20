<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Abstracción portable de diferencias entre motores de BD.
 *
 * Toda lógica que dependa de un motor específico DEBE pasar por esta clase
 * en lugar de dispersar condicionales driver-specific por el proyecto.
 *
 * Motores soportados: PostgreSQL, MySQL, MariaDB.
 */
final class DatabaseDialect
{
    public const DRIVER_PGSQL = 'pgsql';

    public const DRIVER_MYSQL = 'mysql';

    public const DRIVER_MARIADB = 'mariadb';

    /**
     * Búsqueda case-insensitive portable (reemplazo de ILIKE).
     *
     * Comportamiento:
     * - Case-insensitive: SÍ (en los tres motores)
     * - Accent-sensitive: SÍ (depende de collation en MySQL/MariaDB)
     *
     * PostgreSQL: LOWER(column) LIKE LOWER('%value%')
     * MySQL/MariaDB: LOWER(column) LIKE LOWER('%value%') (funciona con utf8mb4_unicode_ci)
     *
     * NOTA: En MySQL/MariaDB con utf8mb4_unicode_ci, LIKE ya es case-insensitive,
     * pero LOWER() garantiza portabilidad explícita sin depender de la collation.
     *
     * @param  QueryBuilder|EloquentBuilder<Model>  $query
     */
    public static function ilike(QueryBuilder|EloquentBuilder $query, string $column, string $value): QueryBuilder|EloquentBuilder
    {
        return $query->whereRaw(
            'LOWER('.$query->getGrammar()->wrap($column).') LIKE LOWER(?)',
            [$value],
        );
    }

    /**
     * Búsqueda case-insensitive con OR (para búsquedas con múltiples columnas).
     *
     * @param  QueryBuilder|EloquentBuilder<Model>  $query
     */
    public static function orIlike(QueryBuilder|EloquentBuilder $query, string $column, string $value): QueryBuilder|EloquentBuilder
    {
        return $query->orWhereRaw(
            'LOWER('.$query->getGrammar()->wrap($column).') LIKE LOWER(?)',
            [$value],
        );
    }

    /**
     * NULLS LAST portable: ordena con NULLs al final en ASC, al inicio en DESC.
     *
     * PostgreSQL soporta NULLS LAST nativo.
     * MySQL/MariaDB: NULLs se consideran el valor más bajo (NULLS FIRST en ASC).
     * Solución portable: ORDER BY (column IS NULL) ASC, column ASC
     * - (column IS NULL) retorna 1 para NULL, 0 para no-NULL
     * - ASC con este término pone NULLs al final
     */
    public static function nullsLastRaw(string $column, string $direction = 'asc'): string
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $nullSort = strtoupper($direction) === 'DESC' ? 'ASC' : 'ASC';

        return "({$column} IS NULL) {$nullSort}, {$column} {$dir}";
    }

    /**
     * Detecta el driver de BD activo.
     *
     * MariaDB se detecta en runtime: PDO sigue reportando "mysql" (el driver
     * PHP es el mismo), pero la versión del servidor contiene "MariaDB".
     */
    public static function driver(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === self::DRIVER_MYSQL) {
            $serverVersion = (string) DB::connection()->getServerVersion();

            if (str_contains(strtolower($serverVersion), 'mariadb')) {
                return self::DRIVER_MARIADB;
            }
        }

        return $driver;
    }

    public static function isPostgres(): bool
    {
        return self::driver() === self::DRIVER_PGSQL;
    }

    public static function isMySQL(): bool
    {
        return self::driver() === self::DRIVER_MYSQL;
    }

    public static function isMariaDB(): bool
    {
        return self::driver() === self::DRIVER_MARIADB;
    }

    public static function isMySQLFamily(): bool
    {
        return self::isMySQL() || self::isMariaDB();
    }
}
