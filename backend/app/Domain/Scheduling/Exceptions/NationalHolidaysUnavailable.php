<?php

namespace App\Domain\Scheduling\Exceptions;

use RuntimeException;

final class NationalHolidaysUnavailable extends RuntimeException
{
    public static function make(): self
    {
        return new self('No se pudo traer la lista de feriados nacionales. Probá más tarde o cargalos a mano.');
    }
}
