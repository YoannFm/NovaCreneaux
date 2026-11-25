# NovaCreneaux

Logiciel libre de réservation de créneaux pour un établissement : CDI, médiathèque, salle de travail, postes informatiques, matériel. Les élèves (ou adhérents, agents…) réservent en quelques clics ; le personnel gère les ressources, les créneaux et les présences depuis une administration complète.

Interface en français, thème clair et sombre, utilisable sur téléphone.

## Fonctionnalités

**Pour les personnes qui réservent**
- Réservation d'un créneau par jour, avec les places restantes en direct
- « Mes réservations » : historique et annulation jusqu'à l'heure limite affichée
- E-mail de confirmation et d'annulation, notifications sur le site
- Double authentification facultative (application, e-mail, SMS, clé physique)

**Pour le personnel**
- Ressources (salles, postes, matériel) et créneaux, jours de fermeture, ouverture des réservations la veille à l'heure choisie
- Planning du jour par ressource et par créneau, export CSV et PDF
- Réservation pour le compte d'un élève qui n'a pas accès au sien
- Limites par rôle : réservations par jour et par semaine, délai d'annulation, nombre de jours à l'avance
- Comptes : import CSV, actions groupées, suspension (raison, gravité, durée ou définitive)
- Rôles et permissions délégables : un membre du personnel n'accède qu'à ce qu'on lui confie
- Journal d'activité détaillé (qui, quand, depuis quelle adresse, avant / après de chaque modification)

**Pour l'administrateur**
- Personnalisation sans toucher au code : nom, logo, couleurs, vocabulaire (« élève », « classe »…), textes et signature des e-mails
- Mises à jour en un clic depuis l'administration, par archive signée
- Sauvegardes automatiques de la base de données
- API REST documentée (jetons, droits par rôle) et webhooks vers d'autres services
- Extensions signées, installables depuis un catalogue

## Prérequis

- PHP 8.3 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `zip`, `sodium` et `dom`
- MySQL ou MariaDB
- Un serveur web dont la racine pointe sur le dossier `public/`
- Node.js n'est **pas** nécessaire : les fichiers compilés sont fournis

## Installation

1. Téléchargez l'archive `novacreneaux-X.Y.Z.zip` de la [dernière version](https://github.com/YoannFm/NovaCreneaux/releases/latest) et décompressez-la sur le serveur.
2. Faites pointer la racine du site sur le dossier `public/`.
3. Créez le fichier de configuration et la clé de l'application :

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Donnez au serveur web le droit d'écrire dans `storage/` et `bootstrap/cache/`.
5. Ouvrez le site dans un navigateur : l'assistant d'installation vérifie les prérequis, configure la base de données et crée le compte administrateur.
6. Ajoutez la tâche planifiée (sauvegardes automatiques, envoi des webhooks) :

   ```
   * * * * * cd /chemin/vers/novacreneaux && php artisan schedule:run >> /dev/null 2>&1
   ```

   Sans elle, ces tâches se lancent à la visite suivante du site.

Conservez une copie du fichier `.env` : la clé `APP_KEY` chiffre certains réglages (mot de passe SMTP, jetons SMS) et ne doit jamais changer.

## Mises à jour

Dans **Administration > Mise à jour** : le site télécharge la nouvelle version, vérifie sa signature, sauvegarde la base et les fichiers remplacés, puis installe. Un retour en arrière est possible. Aucune donnée n'est envoyée.

## API

La documentation interactive de l'API est disponible sur votre site, à l'adresse `/api/docs`. Les jetons se créent dans **Administration > Intégrations API** ou, pour chaque compte autorisé, depuis son profil.

## Licence

NovaCreneaux est distribué sous [licence MIT](LICENSE).
