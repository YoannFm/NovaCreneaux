<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Texte lu dans une requete sans passer par la validation. `(string) $request->input(...)` donnait une erreur 500
 * quand la valeur etait un tableau (`?ticket[]=x`, champ modifie a la main) : ici, un tableau vaut $default.
 */
final class RequestText
{
    public static function get(Request $request, string $key, string $default = ''): string
    {
        $value = $request->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
