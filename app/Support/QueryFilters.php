<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Filtres lus dans l'adresse d'une liste (journal, comptes, planning). Un filtre invalide est ignore et la page
 * s'affiche avec un avertissement, sans redirection : $request->validate() renvoie vers l'adresse precedente, qui pour
 * une page ouverte directement (lien, favori, rechargement) est la meme adresse refusee, d'ou une boucle sans fin.
 */
final class QueryFilters
{
    /** @param array<string, mixed> $rules regles par filtre @return array<string, mixed> filtres valides seulement */
    public static function valid(Request $request, array $rules): array
    {
        $valid = [];
        $ignored = false;
        foreach ($rules as $key => $rule) {
            $value = $request->query($key);
            if ($value === null || $value === '') {
                continue;
            }
            if (Validator::make([$key => $value], [$key => $rule])->fails()) {
                $ignored = true;
                continue;
            }
            $valid[$key] = $value;
        }

        if ($ignored) {
            session()->now('warning', "Un filtre de l'adresse n'est pas valide : il a été ignoré.");
        }

        return $valid;
    }
}
