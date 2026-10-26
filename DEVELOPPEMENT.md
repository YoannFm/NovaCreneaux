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
        UpdateController      - mise à jour par archive signée (GitHub Releases)
      ReservationController   - interface élève (index, store, destroy, my)
      InstallController       - assistant d'installation
      ProfileController       - profil utilisateur (Breeze)
  Http/Middleware/
    CheckInstallation, AdminMiddleware
    EnsureUserIsActive       - deconnecte un compte desactive a chaque requete
    EnforceMaintenanceMode   - Parametres > Maintenance : 503 pour tout sauf admins et connexion
    EnforceAdminTwoFactor    - Parametres > Securite : admin sans MFA redirige vers son profil
    EnforceEmailVerification - Parametres > Securite : e-mail a confirmer (admins dispenses)
  Rules/
    AllowedEmailDomain       - domaines e-mail autorises (inscription et changement d'e-mail du profil)
  Services/Update/           - PathPolicy, SignatureVerifier, ReleaseClient, FileInstaller, UpdateManager
  Services/
    ReservationService (transaction + verrou sur le creneau, codes d'erreur), AvailabilityService (places libres),
    ApiTokenService (jetons d'API), InstallationState (verrou d'installation)
  Support/
    CsvCell                  - neutralise les formules dans les exports CSV
  Models/
    User, Role, Sanction, Reservation, Resource, Slot, Setting
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

## Formulaires admin

- **Jamais de `<form>` dans un `<form>`** : le navigateur ignore le second et fusionne ses champs (`_method=DELETE` ecrasait `_method=PUT` et supprimait l'element en modifiant). Pour un bouton de suppression a cote d'un bouton Sauvegarder : `<button type="submit" form="delete-xxx">` dans le formulaire de modification, et le `<form id="delete-xxx">` apres lui. `tests/Feature/Admin/UserAdminFormsTest` verifie l'absence d'imbrication sur les pages admin.
- Aucun champ de confirmation de mot de passe dans les formulaires admin : ne pas utiliser la regle `confirmed` pour un mot de passe saisi par un administrateur.
- Liste des utilisateurs : cases a cocher (`form="bulk-form"`) et barre d'actions fixe en bas, traitees par `Admin\UserBulkController` (activer, desactiver, lien de reinitialisation par e-mail, export CSV, supprimer). Le compte de l'administrateur qui agit est toujours ignore.

## Mises à jour (archive signée)

Principe : Admin > Mise à jour lit la dernière release GitHub (`config('nova.repo')`), télécharge `novacreneaux-X.Y.Z.zip` et `.zip.sig`, vérifie la signature ed25519 avec `config('nova.update_public_key')`, puis installe. Aucune télémétrie, réservé aux admins, aucun `git` sur le serveur.

- Version courante : constante `config/nova.php` clé `version` (pas dans le `.env`).
- 3 étapes côté interface : télécharger et vérifier, installer (maintenance + sauvegarde des fichiers remplacés dans `storage/app`), finaliser dans une seconde requête (migrations, vidage des caches, fin de maintenance). "Revenir en arrière" restaure la dernière sauvegarde (2 gardées). La base de données n'est pas sauvegardée : l'interface le rappelle.
- `PathPolicy` est la liste blanche des chemins installables (app, bootstrap, config, database, lang, public, resources, routes, vendor, plus `artisan`, `composer.json`, `composer.lock`, `.env.example`). `.env`, `storage`, `.git`, `public/uploads` ne sont jamais touchés. Le même `PathPolicy` sert à construire l'archive.
- Sans clé publique configurée, les mises à jour sont refusées (voir la page).

### Publier une version

1. Une seule fois : `php scripts/release/keys.php`. Mettre la clé publique dans `config/nova.php` (`update_public_key`) et commiter. Enregistrer la clé secrète dans le secret GitHub `UPDATE_SIGNING_KEY`. Ne jamais commiter ni transmettre la clé secrète.
2. Monter `version` dans `config/nova.php`, commiter, pousser.
3. Créer le tag `vX.Y.Z` (identique à la constante, sinon la construction échoue). Le workflow `.github/workflows/release.yml` installe les dépendances sans dev, construit l'archive, la signe, la vérifie avec la clé publique commitée et crée la release.
4. Les assets de la release sont téléchargés uniquement depuis `https://github.com/{repo}/releases/download/{tag}/`.
5. Les fichiers supprimés depuis la version précédente sont listés dans `release.json` (`deleted`) et retirés à l'installation.

Tests : `tests/Unit/PathPolicyTest`, `SignatureVerifierTest`, `tests/Feature/Update/*` (dont `ReleaseEndToEndTest`, qui enchaîne scripts de publication puis installation). Utiliser `new UpdateManager(...)` dans les tests plutôt que `make()` si le conteneur est lié à ce type.

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
