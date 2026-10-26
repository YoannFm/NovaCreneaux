<?php

return [
    // Version installee. A modifier uniquement au moment d'une release (voir DEVELOPPEMENT.md, "Publier une version").
    'version' => '1.4.0',

    // Depot GitHub dont les releases servent de source aux mises a jour (Admin > Mise a jour).
    'repo' => 'YoannFm/NovaCreneaux',

    // Cle publique ed25519 (base64) qui verifie la signature des archives. Generee par
    // `php scripts/release/keys.php` ; la cle secrete reste uniquement dans les secrets GitHub.
    // Vide : les mises a jour sont refusees.
    'update_public_key' => 'ijaZgVcmX/35Asi/stoZtg26Py820QpjJ6gR7HtWPIM=',

    'backups' => [
        // Sauvegardes de la base (Admin > Mise a jour). Hors de public/ : jamais servies directement.
        'directory' => storage_path('app/database-backups'),

        // Sans tache cron (`php artisan schedule:run` chaque minute), une sauvegarde due se lance
        // a la premiere visite apres l'heure prevue.
        'on_request' => env('NOVA_BACKUPS_ON_REQUEST', true),
    ],

    'webhooks' => [
        // Sans tache cron, les webhooks en attente partent apres la reponse de la requete suivante.
        'on_request' => env('NOVA_WEBHOOKS_ON_REQUEST', true),

        // Par defaut, une adresse de webhook doit etre en https et ne jamais viser le reseau interne du serveur.
        // A mettre a true seulement pour un service interne de confiance (http et adresses privees acceptes).
        'allow_private_targets' => env('NOVA_WEBHOOKS_ALLOW_PRIVATE', false),
    ],
];
