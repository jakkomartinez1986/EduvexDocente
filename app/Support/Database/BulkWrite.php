<?php

declare(strict_types=1);

namespace App\Support\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Escritura en lote con el mínimo de consultas por operación (H-07).
 *
 * - caseUpdate(): un único UPDATE que asigna valores DISTINTOS por fila
 *   mediante expresiones CASE SQL (dialect-safe: PostgreSQL, MySQL, SQLite),
 *   manteniendo el `WHERE` del builder que se recibe como scope base.
 * - insertBatch(): un único INSERT para las filas nuevas, con reintento fila
 *   a fila ante carreras de unicidad.
 *
 * Por qué no `upsert()`: el índice único de `attendances` es PARCIAL
 * (WHERE deleted_at IS NULL) y Laravel no permite `ON CONFLICT ... WHERE`;
 * `activity_grades` usa índice único completo. Ninguna de las dos variantes
 * cubre ambos, así que el patrón lectura única + CASE update + INSERT batch
 * mantiene las mismas reglas de unicidad sin tocar el esquema.
 *
 * No dispara eventos de 'saved' a propósito: el sync se apoya en el watermark
 * `updated_at` (que Eloquent actualiza al usar Model::query()->update()).
 * Los borrados que publican tombstones (AttendanceObserver) siguen siendo
 * instancia a instancia, fuera de este helper.
 */
final class BulkWrite
{
    /**
     * Actualiza en una sola sentencia las filas existentes del lote.
     *
     * @param  QueryBuilder|EloquentBuilder<Model>  $query  scope base ya filtrado a las filas del lote (sin el whereIn de key)
     * @param  string  $keyColumn  columna que identifica cada fila dentro del lote (p. ej. student_id)
     * @param  array<string, array<int, mixed>>  $segments  columna => [key => valor] (valores tipados: int/float/string|null)
     * @param  array<string, mixed>  $static  columnas fijas para todas las filas del lote (se enlazan como bindings)
     */
    public static function caseUpdate(QueryBuilder|EloquentBuilder $query, string $keyColumn, array $segments, array $static = []): void
    {
        $keys = array_keys(array_values($segments)[0] ?? []);
        if ($keys === []) {
            return;
        }

        $query->whereIn($keyColumn, $keys);

        $values = $static;
        $pdo = self::pdo($query);

        foreach ($segments as $column => $byKey) {
            $values[$column] = DB::raw('CASE '.self::identifier($query, $keyColumn).' '.self::whenClauses($byKey, $pdo).' END');
        }

        $query->update($values);
    }

    /**
     * Inserta un lote en una sola sentencia. Ante una carrera de unicidad
     * reintenta fila a fila y delega en $onConflict para resolver la fila
     * que ya existe (actualizar la activa o restaurar un tombstone).
     *
     * @param  class-string<Model>  $modelClass
     * @param  list<array<string, mixed>>  $rows
     * @param  Closure(array<string, mixed>): void  $onConflict
     */
    public static function insertBatch(string $modelClass, array $rows, Closure $onConflict): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $modelClass::insert($rows);
        } catch (UniqueConstraintViolationException) {
            foreach ($rows as $row) {
                try {
                    $modelClass::create($row);
                } catch (UniqueConstraintViolationException) {
                    $onConflict($row);
                }
            }
        }
    }

    /**
     * @param  array<int, mixed>  $byKey
     */
    private static function whenClauses(array $byKey, PDO $pdo): string
    {
        $sql = '';

        foreach ($byKey as $key => $value) {
            $sql .= 'WHEN '.self::literal($key, $pdo).' THEN '.self::literal($value, $pdo).' ';
        }

        return $sql;
    }

    /**
     * Literal SQL seguro para valores del lote.
     *
     * Los números van sin comillas (el motor los compara/asigna en su tipo
     * nativo); las cadenas se citan con el driver activo (PDO::quote) y los
     * valores nulos como NULL, de modo que no hay interpolación de bindings
     * dentro de la expresión CASE.
     */
    private static function literal(mixed $value, PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $pdo->quote((string) $value);
    }

    private static function identifier(QueryBuilder|EloquentBuilder $query, string $column): string
    {
        return $query->getGrammar()->wrap($column);
    }

    private static function pdo(QueryBuilder|EloquentBuilder $query): PDO
    {
        return $query->getConnection()->getPdo();
    }
}
