<?php

namespace App\Services\Sso;

/** Ce que le service de l'etablissement dit de la personne : son adresse e-mail (cle de rapprochement) et son nom. */
final class SsoIdentity
{
    public function __construct(public readonly string $email, public readonly ?string $name = null) {}
}
