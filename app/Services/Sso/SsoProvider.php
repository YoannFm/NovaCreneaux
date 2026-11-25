<?php

namespace App\Services\Sso;

use Illuminate\Http\Request;

interface SsoProvider
{
    /** Adresse du service de l'etablissement ou envoyer la personne ; $state est propre a cette tentative. */
    public function redirectUrl(string $callback, array $state): string;

    /**
     * Identite renvoyee au retour sur $callback.
     * @throws SsoException
     */
    public function identity(Request $request, string $callback, array $state): SsoIdentity;
}
