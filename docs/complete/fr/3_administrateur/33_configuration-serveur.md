# Configuration du serveur

Ce chapitre décrit les fichiers de configuration du serveur. Ils contiennent ce que le site doit connaître avant même d'ouvrir la base de données : la connexion à la base et la clé de chiffrement. Les réglages faits dans l'administration sont décrits au chapitre [[n parametres]].

## Fichier .env

Le fichier `.env` est créé à l'installation à partir de `.env.example`. L'assistant y écrit la base de données, le nom et l'adresse du site.

[[env]]

Variables facultatives propres à NovaCreneaux, absentes de `.env.example`. Ajoutez-les seulement pour changer le comportement par défaut :

[[nova_env]]

Après une modification de `.env`, videz le cache de configuration :

```
php artisan config:clear
```

## Fichier config/nova.php

[[nova_config]]

En version [[version]], la clé publique des **extensions** est vide : l'installation d'extensions depuis le catalogue est donc refusée (chapitre [[n extensions]]). La clé des **mises à jour** est renseignée.
