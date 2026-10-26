<?php

return [
    // Version installee. A modifier uniquement au moment d'une release (voir DEVELOPPEMENT.md, "Publier une version").
    'version' => '1.0.0',

    // Depot GitHub dont les releases servent de source aux mises a jour (Admin > Mise a jour).
    'repo' => 'YoannFm/NovaCreneaux',

    // Cle publique ed25519 (base64) qui verifie la signature des archives. Generee par
    // `php scripts/release/keys.php` ; la cle secrete reste uniquement dans les secrets GitHub.
    // Vide : les mises a jour sont refusees.
    'update_public_key' => 'ijaZgVcmX/35Asi/stoZtg26Py820QpjJ6gR7HtWPIM=',
];
