<?php

namespace App\Support;

/** Neutralise les "formules" : un nom commencant par = + - ou @ serait execute par Excel a l'ouverture d'un export. */
class CsvCell
{
    public static function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }
}
