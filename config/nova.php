<?php

return [
    // Version installee. A modifier uniquement au moment d'une release (voir DEVELOPPEMENT.md, "Publier une version").
    'version' => '1.6.0',

    // Depot GitHub dont les releases servent de source aux mises a jour (Admin > Mise a jour).
    'repo' => 'YoannFm/NovaCreneaux',

    // Cle publique ed25519 (base64) qui verifie la signature des archives. Generee par
    // `php scripts/release/keys.php` ; la cle secrete reste uniquement dans les secrets GitHub.
    // Vide : les mises a jour sont refusees.
    'update_public_key' => 'ijaZgVcmX/35Asi/stoZtg26Py820QpjJ6gR7HtWPIM=',

    'plugins' => [
        // Dossier des extensions et fichier d'etat (extensions actives ou en echec). Hors de la base : lisible meme si elle plante.
        'path' => base_path('plugins'),
        'state' => storage_path('app/plugins.json'),

        // Catalogue : depot GitHub public qui publie index.json (signe) et les archives des extensions.
        'catalog_repo' => env('NOVA_PLUGINS_CATALOG_REPO', 'YoannFm/NovaCreneaux-plugins'),

        // Cle publique ed25519 (base64) des extensions, distincte de celle des mises a jour. Generee par
        // `php scripts/release/keys.php` ; la cle secrete reste chez l'editeur du catalogue.
        // Vide : l'installation d'extensions est refusee.
        'public_key' => '',

        // Developpement seulement : accepte une extension copiee a la main, sans archive signee.
        'allow_unsigned' => env('NOVA_PLUGINS_ALLOW_UNSIGNED', false),
    ],

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
