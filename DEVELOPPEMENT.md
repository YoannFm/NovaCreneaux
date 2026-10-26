# NovaCreneaux - notes de développement

Système de réservation de créneaux pour le CDI du Lycée Louis Rascol (Albi).

## Règles de rédaction

- Ne jamais utiliser le tiret long `-` (em dash). Utiliser uniquement `-` à la place.

## Projet

- **URL de production** : https://cdi.rascol.net
- **Répertoire serveur** : `/home/rascol-cdi/htdocs/`
- **Repo GitHub** : https://github.com/YoannFm/NovaCreneaux
- **Branche principale** : `main`

## Stack technique

- **Backend** : Laravel 13, PHP 8.3
- **Base de données** : MySQL
- **Frontend** : Bootstrap 5.3.3 + Bootstrap Icons, Vite
- **Auth** : Laravel Breeze (Blade)

## Déploiement

Le serveur de production **n'a pas Node.js**. Les assets Vite doivent être buildés localement et commités dans `public/build/`.

```bash
# En local - build + commit + push
npm run build
git add public/build/
git commit -m "build: assets"
git push origin main

# Sur le serveur
git pull origin main
php artisan migrate --force
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

Ne jamais ignorer `public/build/` dans `.gitignore` pour ce projet.

Une fois la mise à jour par archive en service (voir Mises à jour), le serveur ne se met plus à jour par `git pull` : l'installateur modifie les fichiers et Git les verrait modifiés. Utiliser Admin > Mise à jour.

## Architecture - base de données

```
users
  - id, name, email, password, classe
  - is_admin (bool, redondant - source de vérité = role.is_admin)
  - is_active (bool)
  - role_id (FK -> roles)

roles
  - id, name (slug unique), label (affiché), description
  - color (primary|secondary|success|warning|danger|info)
  - is_admin (bool), locked (bool)
  Rôles par défaut : admin (locked), user (locked)

resources
  - id, name, description, capacity, is_active

slots
  - id, resource_id, date, start_time, end_time, max_reservations, is_active

reservations
  - id, user_id, slot_id, notes

sanctions
  - id, user_id, admin_id, type (warning|ban), reason
  - banned_until (date nullable - null = ban permanent)

settings
  - key, value (paires clé/valeur génériques)
```

## Architecture - code

```
app/
  Http/
    Controllers/
      Admin/
        DashboardController   - tableau de bord admin
        ResourceController    - CRUD ressources
        SlotController        - CRUD créneaux (nestés sous resource)
        UserController        - CRUD utilisateurs + import CSV + toggle actif/inactif
        RoleController        - CRUD rôles
        SanctionController    - avertissements + bannissements
        ReservationController - liste + suppression réservations admin
        SettingController     - paramètres système
        LogController         - logs Laravel + effacement
        BackupController      - sauvegardes de la base (section de la page Mise à jour)
        UpdateController      - mise à jour par archive signée (GitHub Releases)
      ReservationController   - interface élève (index, store, destroy, my)
      InstallController       - assistant d'installation
      ProfileController       - profil utilisateur (Breeze)
  Http/Middleware/
    CheckInstallation, AdminMiddleware (administrateurs), EnsureStaff (entree de l'admin), RequirePermission (permission:<cle>)
    EnsureUserIsActive       - deconnecte un compte desactive a chaque requete
    EnforceMaintenanceMode   - Parametres > Maintenance : 503 pour tout sauf admins et connexion
    EnforceAdminTwoFactor    - Parametres > Securite : admin sans MFA redirige vers son profil
    EnforceEmailVerification - Parametres > Securite : e-mail a confirmer (admins dispenses)
  Rules/
    AllowedEmailDomain       - domaines e-mail autorises (inscription et changement d'e-mail du profil)
  Services/Update/           - PathPolicy, SignatureVerifier, ReleaseClient, FileInstaller, UpdateManager
  Services/Backup/           - DatabaseDumper, BackupManager, BackupSchedule, BackupSettings, AutomaticBackup, BackupOverview
  Services/
    ReservationService (transaction + verrou sur le creneau, codes d'erreur), AvailabilityService (places libres),
    ApiTokenService (jetons d'API), InstallationState (verrou d'installation)
  Support/
    CsvCell                  - neutralise les formules dans les exports CSV
    Permission, AdminNavigation, ThemeSettings - permissions delegables, menu admin, couleurs du theme
  Models/
    User, Role, Sanction, Reservation, Resource, Slot, Setting
    Concerns/Loggable        - journal d'activite automatique (ressources, creneaux, roles, pages)
  Mail/
    ReservationConfirmed, ReservationCancelled

resources/views/
  layouts/
    admin.blade.php   - layout admin (sidebar + topbar Mnémo-style)
    app.blade.php     - layout public (Bootstrap standard)
    install.blade.php - layout wizard
  admin/
    dashboard/, resources/, slots/, users/, roles/, sanctions/,
    reservations/, settings/, logs/, update/
  reservations/       - vues élève
  install/            - wizard d'installation (5 étapes)
  auth/               - Breeze (login, register, etc.)
```

## Layout et thème

Le layout admin (`resources/views/layouts/admin.blade.php`) reprend la structure Azuriom : `.wrapper` > `nav.sidebar.js-sidebar` + `.main` > `nav.navbar.navbar-bg`. Ordre des feuilles de style : `public/assets/vendor/admin.css` (Bootstrap 5.3 + Azuriom), `app.css` (Vite), puis le bloc `<style>` inline du layout (variables `--accent`, `--accent-text`, couleurs configurables).

### Mode clair et sombre

- Le thème est porté par `data-bs-theme` sur `<html>`. Le bouton lit et ecrit `localStorage` sous la cle `nc-theme` (partagee avec le site public, repli sur `users.theme_mode`, puis le systeme).
- `app.css` definit une palette semantique : `--white` (surface), `--light-bg`, `--border`, `--dark-text`, `--muted-text`. En mode sombre, le bloc `[data-bs-theme="dark"]` en tete du fichier les fait pointer vers les variables Bootstrap. **Un composant qui utilise ces noms s'adapte tout seul** : ne pas coder de couleur en dur et ne pas ajouter de correctif sombre par composant.
- Texte sur fond vif (boutons et badges success/warning) : `--on-solid`, identique dans les deux themes.
- Accent configurable : `--accent` pour les fonds et bordures, `--accent-text` pour le texte (assombri en mode clair, l'or par defaut est illisible sur blanc).
- `.table-light` est fige en clair dans Bootstrap : `app.css` le remet sur la palette.
- `public/build/` (CSS et JS compiles) est versionne : le serveur n'a pas Node. Apres une modification de `resources/css` ou `resources/js` : `npm run build` puis commit de `public/build/`.

## Bootstrap JS - SRI supprimé

Le CDN jsDelivr pour Bootstrap 5.3.3 retourne une taille de fichier différente de celle attendue par le hash SRI d'origine. L'attribut `integrity` a été **retiré** des deux layouts (`admin.blade.php` et `app.blade.php`).

Ne pas remettre l'attribut `integrity` sur ce `<script>` sans vérifier d'abord le hash réel du fichier servi.

## API v1

- Routes : `routes/api.php`, prefixe `/api/v1`, controleurs dans `app/Http/Controllers/Api/V1/`, reponses JSON dans `app/Http/Resources/V1/`.
- Authentification : jetons Sanctum (`ncr_...`) avec droits `resources:read` et `reservations:read`, pas de cookie. Gestion : `php artisan api:token:create|list|revoke`.
- Les regles de reservation viennent de `ReservationService::checkBookable()` (memes regles que le site) ; les echecs ont un `code` stable.
- Erreurs au format unique de `App\Http\ApiError`. Quotas : `api-ip` (120/min) puis `api` (60/min par jeton), definis dans `AppServiceProvider`.
- Documentation : `public/docs/openapi.yaml` (a la main), affichee sur `/api/docs` avec Swagger UI local (`public/vendor/swagger-ui`). `tests/Feature/Api/OpenApiContractTest` verifie que le fichier correspond aux routes et aux reponses reelles : le mettre a jour a chaque changement de l'API.
- Deploiement : `composer install --no-dev` (laravel/sanctum) puis `php artisan migrate --force` (table `personal_access_tokens`).
- Ecriture : `POST /reservations` et `DELETE /reservations/{id}` (droit `reservations:write`, quota 30/min), toujours pour le compte du jeton. Codes de refus : 403 banni, 409 conflit d'etat, 422 regle non respectee (`ApiError::fromReservationFailure`).
- Administration des jetons : Admin > Integrations API (`Admin\ApiTokenController`), logique commune dans `ApiTokenService`.
- **Interrupteur general** : reglage `api_enabled` (defaut oui), bouton dans Admin > Integrations API. Coupe : tout `/api/v1` repond `503 api_disabled` (middleware `EnsureApiEnabled`, y compris `/meta`), la section des jetons disparait du profil, la creation est refusee. Les jetons sont conserves. `ApiTokenService::enabled()` ; `User::canUseApi()` en tient compte.
- **Acces par role** : `roles.api_access` (defaut vrai, reglage "Acces a l'API" dans Admin > Roles). `User::canUseApi()` (vrai sans role). Un role sans acces : plus de section de jetons dans le profil, creation refusee (profil, administration et `api:token:create`), et les jetons existants de ses comptes recoivent `403 api_access_denied` (middleware `EnsureApiAccess`) sans etre supprimes : ils refonctionnent si l'acces revient.
- **Jetons personnels** (profil, `ProfileApiTokenController`, partial `profile/partials/api-tokens`) : mot de passe actuel exige, validite 30, 90 ou 365 jours (`ApiTokenService::PERSONAL_DURATIONS`), 5 jetons non expires au plus, revocation de ses seuls jetons (404 sinon), 10 creations par minute. Le jeton appartient au compte : l'API ne renvoie et ne modifie que ses propres reservations. Le secret n'est affiche qu'une fois (session flash).

## Permissions, menu et journal

- **Permissions par rôle** : `roles.permissions` (JSON) liste des clés de `App\Support\Permission` (ressources, réservations, utilisateurs, sanctions, notifications, pages, navigation, apparence, lecture du journal). Un administrateur (`role.is_admin`) a tout. Un rôle avec au moins une permission entre dans l'administration (`User::hasAdminAccess()`), sans aucune il n'y entre pas. `User::hasPermission($cle)` est la seule question à poser.
- **Non délégable** : réglages, rôles, mises à jour, sauvegardes, jetons d'API, purge du journal. Ces routes sont sous le middleware `admin` (administrateurs seulement). Raison : celui qui les tient pourrait s'attribuer tous les droits.
- **Personnel délégué** : ne voit, ne modifie, ne désactive, ne supprime ni ne sanctionne les administrateurs ni les autres membres du personnel, et ne peut attribuer que des rôles sans accès à l'administration (`User::canManage()`, `User::canAssign()`, appliqués par `Admin\UserController`, `UserBulkController` et `SanctionController`).
- **Routes** (`routes/web.php`) : groupe `auth` + `staff` (`EnsureStaff`), puis un sous-groupe `permission:<cle>` (`RequirePermission`) ou `admin` par section. Un refus renvoie au tableau de bord avec un message.
- **Menu** : une seule description dans `App\Support\AdminNavigation::definition()` (libellé, icône, route, permission), filtrée selon l'utilisateur et rendue par `layouts/_admin_sidebar`. Pour ajouter une page d'administration : sa route sous le bon middleware, puis son entrée dans le menu avec la même permission. `tests/Feature/Admin/PermissionsTest::test_every_menu_entry_matches_the_access_of_its_route` vérifie l'accord entre les deux pour chaque permission.
- **Journal d'activité** : le trait `Models\Concerns\Loggable` inscrit création, modification et suppression (ressources, créneaux, rôles, pages). Le modèle déclare `ACTIVITY_NOUN`, `ACTIVITY_FEMININE` et `activityContext()`. Ne pas ajouter d'appel `ActivityLogger::log` pour ces opérations dans les contrôleurs. Les actions qui ne sont pas un simple enregistrement (export, téléchargement, import, action groupée, réglages) restent journalisées à la main. Les suppressions en cascade faites par la base ne sont pas journalisées.
- **Apparence** : `App\Support\ThemeSettings` décrit les couleurs (libellé, icône, valeur par défaut) ; le formulaire, la validation et les mises en page l'utilisent. Pour ajouter une couleur : l'ajouter dans `ThemeSettings::MODES`, puis l'utiliser avec `ThemeSettings::value('cle')`.

## Notifications

- **Cloche du site** : `user_notifications` (modele `UserNotification`), creees par `Services\SiteNotifier::send()`. L'utilisateur les lit dans `/notifications` (`NotificationController`, jamais celles d'un autre compte) ; le nombre de non lues s'affiche dans l'en-tete (`User::unreadNotificationCount()`). Ne pas utiliser `notifications()` de `User` : c'est le trait `Notifiable` de Laravel (autre table). Une notification n'envoie pas d'e-mail par elle-meme : les appelants envoient le leur.
- **Reservation supprimee par un administrateur** (`ReservationService::cancelByAdmin($reservation, $motif)`) : e-mail `ReservationCancelled` (avec le motif) et notification de la cloche. Meme chose quand un creneau ou une ressource est supprime : `cancelUpcomingForSlots()` annule les reservations a venir et previent chaque eleve (les reservations passees restent dans l'historique). Une annulation faite par l'eleve ne cree pas de notification de cloche.
- **Compte supprime** (`Services\AccountDeletionNotice`, a appeler avant `delete()`) : e-mail `AccountDeleted` a l'ancien titulaire. Pour une suppression par un administrateur (une a une ou en lot), le reglage `notify_account_deletion` (Parametres > E-mail, defaut oui) peut le couper ; la suppression de son propre compte previent toujours. Un e-mail qui echoue n'empeche jamais la suppression.

## Webhooks

- **Administration** : Admin > Webhooks (`Admin\WebhookController`, reservee aux administrateurs : l'adresse choisie recoit des donnees du site). Chaque webhook : nom, adresse, evenements, actif ou non, secret (chiffre, affiche une seule fois, regenerable), historique des 30 derniers envois, bouton de test.
- **Evenements** (`Support\WebhookEvent`) : `reservation.created`, `reservation.cancelled` (champ `cancelled_by` : user ou admin ; l'API compte comme user), `user.created`, `user.deleted` (champ `deleted_by` : self, admin ou system), plus `ping` pour le test. Les reservations passent par `ReservationService`, les comptes par les evenements du modele `User`. Donnees : `Services\Webhook\WebhookPayload` (jamais d'adresse e-mail).
- **File d'envoi** : `WebhookDispatcher` ecrit des lignes `webhook_deliveries` (statut pending) sans jamais lever d'exception, puis `WebhookRunner` les envoie hors de la requete : commande `webhooks:deliver` du planificateur chaque minute, ou a defaut `DeliverWebhooksOnRequest` apres la reponse d'une requete (`NOVA_WEBHOOKS_ON_REQUEST=false` le coupe ; les tests le coupent dans `phpunit.xml`). Un verrou (`webhooks-run`) evite deux envoyeurs en meme temps. Un import de 500 comptes ne bloque donc rien : les envois s'etalent.
- **Envoi** (`WebhookSender`) : POST JSON, en-tetes `X-Nova-Event`, `X-Nova-Delivery` (identique a chaque essai : le destinataire ignore les doublons), `X-Nova-Timestamp` et `X-Nova-Signature` = `sha256=` + HMAC-SHA256 de `horodatage.corps` avec le secret. Succes = reponse 2xx. Sinon 4 nouveaux essais (1 min, 5 min, 30 min, 2 h) puis abandon ; 10 abandons consecutifs desactivent le webhook (journal d'activite). L'historique de plus de 30 jours est purge. Le suivi (compteurs, dernier succes) se fait sans evenement Eloquent pour ne pas remplir le journal.
- **Securite (SSRF)** : `Services\Webhook\UrlGuard` exige https, refuse identifiants dans l'adresse et toute adresse qui resout vers le reseau interne (prive, boucle locale, lien local et metadonnees du cloud, CGNAT, IPv6 locale, IPv4 mappee). Le controle a lieu a l'enregistrement ET a chaque envoi, et la connexion est epinglee sur l'IP controlee (`CURLOPT_RESOLVE`) : pas de seconde resolution DNS. Pas de redirection suivie, delai de 3 s de connexion et 7 s au total. `NOVA_WEBHOOKS_ALLOW_PRIVATE=true` (`nova.webhooks.allow_private_targets`) leve ces interdits (http accepte) pour un service interne de confiance.
- Pour ajouter un evenement : constante et libelle dans `WebhookEvent`, appel de `WebhookDispatcher::dispatch()` au bon endroit avec une charge utile construite par `WebhookPayload`.

## Formulaires admin

- **Jamais de `<form>` dans un `<form>`** : le navigateur ignore le second et fusionne ses champs (`_method=DELETE` ecrasait `_method=PUT` et supprimait l'element en modifiant). Pour un bouton de suppression a cote d'un bouton Sauvegarder : `<button type="submit" form="delete-xxx">` dans le formulaire de modification, et le `<form id="delete-xxx">` apres lui. `tests/Feature/Admin/UserAdminFormsTest` verifie l'absence d'imbrication sur les pages admin.
- Aucun champ de confirmation de mot de passe dans les formulaires admin : ne pas utiliser la regle `confirmed` pour un mot de passe saisi par un administrateur.
- Liste des utilisateurs : cases a cocher (`form="bulk-form"`) et barre d'actions fixe en bas, traitees par `Admin\UserBulkController` (activer, desactiver, lien de reinitialisation par e-mail, export CSV, supprimer). Le compte de l'administrateur qui agit est toujours ignore.

## Mises à jour (archive signée)

Principe : Admin > Mise à jour lit la dernière release GitHub (`config('nova.repo')`), télécharge `novacreneaux-X.Y.Z.zip` et `.zip.sig`, vérifie la signature ed25519 avec `config('nova.update_public_key')`, puis installe. Aucune télémétrie, réservé aux admins, aucun `git` sur le serveur.

- Version courante : constante `config/nova.php` clé `version` (pas dans le `.env`).
- 3 étapes côté interface : télécharger et vérifier, installer (maintenance, sauvegarde de la base de données puis des fichiers remplacés dans `storage/app`), finaliser dans une seconde requête (migrations, vidage des caches, fin de maintenance). "Revenir en arrière" restaure la dernière sauvegarde (2 gardées). La base est sauvegardée juste avant la copie des fichiers (case cochée par défaut, type `update` : on garde les 3 dernières ; un échec de cette sauvegarde arrête l'installation sans rien modifier). "Revenir en arrière" ne restaure que les fichiers, pas la base.
- `PathPolicy` est la liste blanche des chemins installables (app, bootstrap, config, database, lang, public, resources, routes, vendor, plus `artisan`, `composer.json`, `composer.lock`, `.env.example`). `.env`, `storage`, `.git`, `public/uploads` ne sont jamais touchés. Le même `PathPolicy` sert à construire l'archive.
- Sans clé publique configurée, les mises à jour sont refusées (voir la page).

### Publier une version

1. Une seule fois : `php scripts/release/keys.php`. Mettre la clé publique dans `config/nova.php` (`update_public_key`) et commiter. Enregistrer la clé secrète dans le secret GitHub `UPDATE_SIGNING_KEY`. Ne jamais commiter ni transmettre la clé secrète.
2. Monter `version` dans `config/nova.php`, commiter, pousser.
3. Créer le tag `vX.Y.Z` (identique à la constante, sinon la construction échoue). Le workflow `.github/workflows/release.yml` installe les dépendances sans dev, construit l'archive, la signe, la vérifie avec la clé publique commitée et crée la release.
4. Les assets de la release sont téléchargés uniquement depuis `https://github.com/{repo}/releases/download/{tag}/`.
5. Les fichiers supprimés depuis la version précédente sont listés dans `release.json` (`deleted`) et retirés à l'installation.

Tests : `tests/Unit/PathPolicyTest`, `SignatureVerifierTest`, `tests/Feature/Update/*` (dont `ReleaseEndToEndTest`, qui enchaîne scripts de publication puis installation). Utiliser `new UpdateManager(...)` dans les tests plutôt que `make()` si le conteneur est lié à ce type.

## Sauvegardes de la base

- Section "Sauvegardes de la base de données" de Admin > Mise à jour (`Admin\BackupController`, partial `admin/update/_backups`). Export SQL compressé (`.sql.gz`) fait en PHP avec PDO (`DatabaseDumper`, pilotes MySQL, MariaDB et SQLite), sans `mysqldump`. Les tables `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` et `failed_jobs` sont exportées sans leurs lignes. Un fichier n'est gardé que s'il se termine par le marqueur de fin (`DatabaseDumper::isComplete`).
- Fichiers dans `storage/app/database-backups` (`config('nova.backups.directory')`), mode 0600, jamais servis publiquement. Le nom porte les métadonnées : `nova-AAAAMMJJ-HHMMSS-(auto|manual)-<16 hex>.sql.gz`. `BackupManager::path()` n'accepte que ce format : aucun chemin ni nom approximatif n'est résolu.
- **Planification** (réglages `backup_enabled`, `backup_frequency` daily|weekly|monthly, `backup_time`, `backup_weekday`, `backup_keep`) : par défaut tous les jours à 03:00, 7 sauvegardes automatiques conservées (les manuelles ne sont jamais purgées). `BackupSchedule` calcule les échéances, `AutomaticBackup::isDue()` décide : une échéance est tentée une fois, puis reprise toutes les heures après un échec. État : `backup_last_attempt_at`, `backup_last_success_at`, `backup_last_error`.
- **Déclenchement** : avec le cron du serveur (`* * * * * cd /home/rascol-cdi/htdocs && php8.5 artisan schedule:run >> /dev/null 2>&1`), la commande `backup:run-due` part chaque minute et la sauvegarde a lieu à l'heure choisie. Sans cron, le middleware `RunDueBackupOnRequest` la lance après l'envoi de la réponse, à la première visite après l'heure prévue (`NOVA_BACKUPS_ON_REQUEST=false` le désactive ; les tests le désactivent dans `phpunit.xml`).
- **Sécurité** : télécharger ou supprimer exige le mot de passe de l'administrateur connecté (POST, 6 essais par minute), les refus et les actions sont dans le journal d'activité. Une sauvegarde contient toutes les données du site (comptes, adresses e-mail, empreintes de mots de passe) : ne la déposer que dans un endroit sûr. Les secrets des réglages y restent chiffrés avec `APP_KEY`, qui n'est pas dans la sauvegarde : conserver aussi une copie du `.env`.
- **Restauration** (pas d'interface, volontairement) : le fichier contient `DROP TABLE` puis `CREATE TABLE` et `INSERT` de chaque table. `gunzip -c nova-....sql.gz | mysql -u UTILISATEUR -p NOM_DE_LA_BASE` remet la base dans l'état de la sauvegarde. À essayer d'abord sur une base de test.

## Secrets des réglages

- Quatre réglages sont chiffrés en base avec `APP_KEY` : `mail_smtp_password`, `sms_ntfy_token`, `sms_android_pass`, `sms_free_mobile_key` (liste : `SettingService::SECRET_KEYS`). Pour en ajouter un, l'ajouter à cette liste et à la migration de chiffrement correspondante.
- Le chiffrement est transparent : `setting('cle')` et `SettingService::get()` renvoient la valeur en clair, `set()` chiffre. Une valeur chiffrée commence par `enc:v1:` ; une valeur en clair d'avant le chiffrement reste lisible jusqu'à la migration `encrypt_secret_settings`.
- Le cache garde la valeur chiffrée, jamais la valeur en clair. `SettingService::all()` n'inclut pas les secrets (les formulaires ne les reçoivent pas) ; `secretsStatus()` dit seulement s'ils sont renseignés.
- Formulaires : un secret n'est jamais réaffiché. Champ vide = valeur conservée ; la case `clear_secrets[]` l'efface (partial `admin/settings/_secret_input`). Enregistrement via `SettingController::storeSettings()`.
- **`APP_KEY` ne doit jamais changer** (la sauvegarder avec le `.env`) : avec une autre clé, les secrets deviennent illisibles, `get()` renvoie la valeur par défaut demandée (donc "non renseigné") et un avertissement est écrit dans les logs. L'administrateur doit alors les ressaisir.

## Proxys de confiance

Reglage dans Admin > Parametres > Securite (cle `trusted_proxies`, liste d'IP ou de reseaux separes par des virgules), applique au demarrage par `AppServiceProvider` via `TrustProxies::at()`. La page affiche l'adresse vue par le site, l'adresse du proxy et `X-Forwarded-For`, et propose la valeur a saisir. Sans ce reglage derriere un proxy, tous les visiteurs partagent la meme IP (quotas de l'API, limite de connexion, journal d'activite). Aucune modification du `.env` n'est necessaire.

## Installation - verrou

`InstallationState` considere le site installe si `APP_INSTALLED=true`, si `storage/app/installed.lock` existe ou si la table `installation` le dit (le verrou est alors cree). Une panne de base ne rouvre donc jamais `/install`.

## Rôles - comportement

- Les comptes crees par inscription, import CSV ou installation recoivent un role (`Role::defaultUser()` ou `admin`). Sans role, aucune limite de reservation ne s'applique.

- `roles.locked = true` : le rôle ne peut pas être supprimé ni renommé (admin et user par défaut).
- `users.is_admin` est un champ redondant maintenu en sync avec `role.is_admin` à chaque update utilisateur.
- `User::isAdmin()` retourne `true` si `role->is_admin` OU `is_admin` est vrai (rétrocompatibilité).

## Sanctions - comportement

- `type = 'warning'` : avertissement, n'empêche pas la connexion.
- `type = 'ban'` avec `banned_until = null` : ban permanent.
- `type = 'ban'` avec `banned_until = date` : ban temporaire (expire le soir du jour indiqué).
- `User::activeBan()` retourne le ban actif ou `null`.
- `User::warningCount()` retourne le nombre total d'avertissements.

## Commandes utiles

```bash
# Cache
php artisan route:clear && php artisan view:clear && php artisan config:clear

# Migrations
php artisan migrate --force

# Logs Laravel
tail -f storage/logs/laravel.log
```

## Périmètre fonctionnel

1. **Assistant d'installation** (5 étapes) - prérequis, DB, compte admin, terminé
2. **Interface élève** - voir les créneaux disponibles, réserver, annuler, voir ses réservations
3. **Admin - Ressources** - CRUD (salles, équipements, etc.)
4. **Admin - Créneaux** - CRUD nestés sous une ressource
5. **Admin - Utilisateurs** - CRUD complet + import CSV + activation/désactivation
6. **Admin - Rôles** - CRUD avec protection des rôles verrouillés
7. **Admin - Réservations** - liste globale, suppression
8. **Admin - Sanctions** - avertissements et bannissements par utilisateur
9. **Admin - Paramètres** - configuration runtime (clés/valeurs)
10. **Admin - Logs** - affichage et effacement du log Laravel
11. **Admin - Mise à jour** - archive signée depuis les releases GitHub (voir section Mises à jour)
