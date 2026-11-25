# Installation

Ce chapitre s'adresse à la personne qui installe le site sur un serveur.

## Avant de commencer

Il vous faut :

- un hébergement avec PHP 8.3 ou plus et les extensions `pdo_mysql`, `mbstring`, `openssl`, `zip`, `sodium` et `dom` ;
- une base de données MySQL ou MariaDB vide, avec un utilisateur qui a tous les droits sur elle ;
- la possibilité de faire pointer la racine du site (le dossier servi par le serveur web) sur un dossier précis ;
- de préférence, un accès à la tâche planifiée du serveur (cron) ;
- une adresse e-mail d'expédition et ses paramètres SMTP, pour les e-mails de confirmation.

## Étapes

1. Téléchargez l'archive `novacreneaux-X.Y.Z.zip` de la dernière version publiée et décompressez-la sur le serveur, par exemple dans `/var/www/novacreneaux`.
2. Faites pointer la racine du site sur le dossier **`public/`**, jamais sur le dossier du projet : les autres dossiers (configuration, sauvegardes) ne doivent pas être accessibles depuis le Web.
3. Créez le fichier de configuration et la clé de l'application :

   ```
   cp .env.example .env
   php artisan key:generate
   ```

4. Donnez au serveur web le droit d'écrire dans `storage/` et `bootstrap/cache/`.
5. Ouvrez le site dans un navigateur. L'assistant d'installation s'affiche (adresse `/install`).
6. Ajoutez la tâche planifiée (voir plus bas).
7. Faites une copie du fichier `.env` et rangez-la en lieu sûr.

## L'assistant d'installation

L'assistant enchaîne cinq étapes. Il ne s'ouvre que tant que le site n'est pas installé.

**1. Prérequis.** L'assistant vérifie le serveur :

[[prereq]]

Si un contrôle échoue, corrigez le serveur et rechargez la page.

**2. Base de données.** Saisissez le serveur, le port, le nom de la base, l'utilisateur et le mot de passe. L'assistant teste la connexion, enregistre ces valeurs dans `.env`, puis crée les tables. Il crée aussi deux rôles (Administrateur et Utilisateur) et trois liens dans la barre de navigation (Accueil, Réserver, Mes réservations).

**3. Site.** Saisissez le nom du site, son adresse (URL), le fuseau horaire et le choix d'ouvrir ou non l'inscription libre. Le formulaire demande aussi un nombre maximal de réservations par jour. En version [[version]], cette valeur est enregistrée mais ne s'applique pas : les limites réelles se règlent par rôle (chapitre [[n regles]]).

**4. Compte administrateur.** Saisissez le nom, l'adresse e-mail et le mot de passe (8 caractères au moins) du premier administrateur.

**5. Fin.** L'installation est verrouillée dès la création de l'administrateur, et `/install` redirige ensuite vers le site. Vous êtes envoyé vers la page de connexion.

## Tâche planifiée (cron)

Ajoutez cette ligne à la table cron de l'utilisateur du serveur web :

```
* * * * * cd /chemin/vers/novacreneaux && php artisan schedule:run >> /dev/null 2>&1
```

Elle lance chaque minute les tâches de fond :

[[schedule]]

**Sans cron**, le site rattrape ces tâches à la visite suivante : une sauvegarde due part à la première visite après l'heure prévue, et les webhooks en attente partent après la réponse d'une requête. Ce mode de secours se désactive avec les variables `NOVA_BACKUPS_ON_REQUEST` et `NOVA_WEBHOOKS_ON_REQUEST` (chapitre [[n configuration-serveur]]). Avec cron, les horaires sont respectés à la minute près.

## La clé APP_KEY

La valeur `APP_KEY` du fichier `.env` chiffre les sessions, les réglages secrets (mot de passe SMTP, jetons SMS) et les secrets des webhooks.

- Elle ne doit **jamais changer** une fois le site en service. Sans elle, les réglages secrets deviennent illisibles et sont traités comme vides.
- Gardez une copie du fichier `.env` hors du serveur.
- Ne relancez pas `php artisan key:generate` sur un site déjà installé.

## Après l'installation

Dans l'ordre conseillé :

1. **Paramètres > E-mail** : configurez l'envoi, puis envoyez un e-mail de test (chapitre [[n parametres]]).
2. **Paramètres > Général** : nom, logo, vocabulaire, fuseau horaire.
3. **Ressources** : créez les ressources et leurs créneaux (chapitre [[n guide-personnel]]).
4. **Paramètres > Réservations** : réglez les limites de chaque rôle (chapitre [[n regles]]).
5. **Comptes** : importez les comptes ou ouvrez l'inscription (chapitre [[n guide-personnel]]).
6. **Mise à jour > Sauvegardes** : vérifiez la sauvegarde automatique (chapitre [[n exploitation-site]]).
7. **Paramètres > Sécurité** : imposez la double authentification aux administrateurs. Si le site est derrière un proxy, déclarez-le (chapitre [[n parametres]]).
