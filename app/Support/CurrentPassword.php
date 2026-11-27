<?php

namespace App\Support;

/**
 * Regles du mot de passe actuel, redemande avant une action sensible. `bail` arrete la validation au premier echec :
 * sans lui, un tableau envoye a la place du texte arrivait jusqu'a la verification du mot de passe (erreur 500).
 */
final class CurrentPassword
{
    public static function rules(): array
    {
        return ['bail', 'required', 'string', 'current_password'];
    }
}
