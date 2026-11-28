<?php

namespace App\Support;

final class Color
{
    /** Texte lisible (noir ou blanc) sur un fond de la couleur donnee (#abc ou #aabbcc). */
    public static function contrastText(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 128 ? '#000000' : '#ffffff';
    }
}
