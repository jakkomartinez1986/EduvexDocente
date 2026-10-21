<?php

namespace App\Imports\Concerns;

/**
 * Detecta cédulas repetidas dentro del mismo archivo Excel.
 *
 * La regla unique:users,dni solo mira la base de datos, así que en la capa de
 * previsualización dos filas con la misma cédula aparecen ambas como válidas y
 * la segunda revienta con una violación de unicidad al confirmar la importación
 * (dejando el archivo a medias). Este registro es por instancia del import, así
 * que previsualización y confirmación dan exactamente el mismo resultado.
 */
trait TracksDnisInFile
{
    /**
     * @var array<string, int> cédula => número de fila (1-based) donde apareció por primera vez
     */
    protected array $dniFirstRow = [];

    /**
     * @return string|null mensaje de error, o null si la cédula es nueva en el archivo
     */
    protected function duplicateDniInFile(string $dni, int $rowNumber): ?string
    {
        if ($dni === '') {
            return null;
        }

        if (isset($this->dniFirstRow[$dni])) {
            return 'DNI repetido en el archivo: ya aparece en la fila '.$this->dniFirstRow[$dni].'.';
        }

        $this->dniFirstRow[$dni] = $rowNumber;

        return null;
    }
}
