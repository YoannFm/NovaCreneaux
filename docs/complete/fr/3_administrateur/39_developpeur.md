# Guide du développeur

Ce chapitre s'adresse à qui veut faire évoluer NovaCreneaux. Il décrit l'organisation du code, la mise en route en local, les tests et la publication d'une version, à partir des sources de la version [[version]].

## Technologies

| Couche | Outil |
|---|---|
| Langage, cadriciel | PHP 8.3, Laravel 13 |
| Pages | Gabarits Blade (`resources/views`), Tailwind CSS et Vite pour les fichiers du site public |
| Administration | Fichiers compilés fournis (`public/assets/vendor/admin.css`, `admin.js`), Bootstrap Icons |
| Base de données | MySQL ou MariaDB en production, SQLite pour les tests |
| API | Laravel Sanctum (jetons), description OpenAPI 3.1 (`public/docs/openapi.yaml`) |
| PDF, QR codes | Dompdf, Bacon QR Code |

## Organisation du code

| Dossier | Contenu |
|---|---|
| `app/Http/Controllers` | Contrôleurs : site (`ReservationController`, `ProfileController`, `MfaController`...), `Admin/` (administration), `Api/V1/` (API), `Auth/` (connexion, inscription, mot de passe). |
| `app/Http/Middleware` | Filtres des requêtes : installation, maintenance, compte actif, permissions, double authentification obligatoire, en-têtes de sécurité, tâches de fond sans cron. |
| `app/Models` | Modèles de données : `User`, `Role`, `Resource`, `Slot`, `Reservation`, `Sanction`, `Setting`, `Page`, `NavItem`, `UserNotification`, `ActivityLog`, `Webhook`, `WebhookDelivery`, `MfaCode`. |
| `app/Services` | Logique métier : `ReservationService` (règles de réservation), `AvailabilityService`, `SettingService`, `ActivityLogger`, `Backup/`, `Update/`, `Webhook/`, double authentification (`TotpService`, `OtpService`, `SmsService`, `WebAuthnService`). |
| `app/Support` | Sources uniques de vérité : `Permission`, `ReservationLimits`, `Customization`, `ThemeSettings`, `WebhookEvent`, nettoyage HTML (`HtmlSanitizer`, `SafeMarkdown`). |
| `app/Plugins` | Système d'extensions : manifeste, catalogue, installation, chargement. |
| `app/Console/Commands` | Commandes `php artisan` du chapitre [[n exploitation-serveur]]. |
| `database/migrations` | Structure de la base, dans l'ordre chronologique. |
| `resources/webhook-presets` | Un fichier JSON par service préréglé de webhook. |
| `routes` | `web.php` (site et administration), `auth.php`, `api.php`, `console.php` (tâches planifiées). |
| `scripts/release`, `scripts/plugins` | Construction et signature des versions et des extensions. Ces dossiers ne sont pas livrés dans l'archive. |
| `tests` | Tests automatisés (voir plus bas). |
| `docs` | Cette documentation et les captures d'écran. |

**Principes à respecter**

- **Une seule source par règle.** Les règles de réservation sont dans `ReservationService`, partagé par le site et l'API. Chaque refus renvoie un code stable (chapitre [[n regles]]), que l'API transforme en statut HTTP dans `app/Http/ApiError.php`. Les limites par rôle sont décrites une seule fois dans `ReservationLimits` (formulaire, validation, enregistrement). Il en va de même pour `Customization`, `ThemeSettings` et `Permission`.
- **Réglages.** On lit un réglage avec `setting('cle', 'défaut')` et on l'écrit avec `SettingService::set()`. Une clé ajoutée à `SettingService::SECRET_KEYS` est chiffrée automatiquement et masquée dans le journal.
- **Journal.** Les modèles qui utilisent le trait `Loggable` journalisent eux-mêmes leurs créations, modifications (avant et après) et suppressions. Pour une action, on appelle `ActivityLogger::log()`.
- **Contenus HTML.** Tout HTML saisi dans l'administration passe par `HtmlSanitizer::clean()` avant d'être enregistré.
- **Pas de service externe implicite.** Les QR codes sont produits sur le serveur, les avatars sont des initiales, et les webhooks n'envoient jamais d'adresse e-mail.

## Installer un poste de développement

Prérequis : PHP 8.3, Composer, Node.js et npm. SQLite suffit pour commencer.

```
git clone https://github.com/YoannFm/NovaCreneaux.git
cd NovaCreneaux
composer setup
```

Le script `setup` de `composer.json` installe les dépendances PHP, copie `.env.example`, crée la clé, lance les migrations, installe les dépendances JavaScript et compile les fichiers. Pour travailler :

```
composer dev
```

Cette commande lance ensemble le serveur PHP, la file d'attente, l'affichage du journal technique et la compilation en direct de Vite.

Pour tester une extension non signée en local, ajoutez `NOVA_PLUGINS_ALLOW_UNSIGNED=true` à `.env`.

## Tests automatisés

Les tests utilisent PHPUnit, avec une base SQLite en mémoire et des e-mails capturés (`phpunit.xml`). Ils ne touchent ni à la base réelle ni au réseau.

```
composer test
```

ou directement `php artisan test`. Pour un seul fichier : `php artisan test tests/Feature/ReservationRulesTest.php`.

Inventaire au moment de cette documentation ([[tests_files]] fichiers, [[tests_count]] méthodes de test). Le décompte est fait par la documentation, pas par une exécution :

[[tests_table]]

Les dossiers couvrent : `Admin` (pages d'administration), `Api` (API et jetons), `Auth` (connexion, inscription, mots de passe), `Backup` (sauvegardes), `Plugins` (extensions), `Update` (mises à jour signées), `Webhook` (envoi, signature, protection du réseau interne), `Unit` (fonctions isolées : signature, nettoyage HTML, calendrier des sauvegardes...).

Les fichiers de `tests/Support` (`BuildsPlugins`, `BuildsReleases`, `CreatesStaff`) aident à fabriquer des extensions, des archives de version et des comptes du personnel pour les tests. `tests/Fixtures/plugins` contient des extensions d'exemple.

**Procédure avant toute publication**

1. `composer test` : tous les tests passent.
2. `composer audit` : aucune vulnérabilité connue dans les dépendances.
3. Vérification manuelle des parcours principaux sur une copie du site : réserver, annuler, réserver pour un élève, exporter le planning, mettre à jour depuis la version précédente.

## Intégration continue

Deux processus GitHub Actions sont définis dans `.github/workflows` :

| Fichier | Déclenchement | Travail |
|---|---|---|
| `laravel.yml` | Envoi sur `main` et demande de fusion vers `main` | `composer audit`, puis toute la suite de tests sur SQLite. |
| `release.yml` | Étiquette `v*` sur le dépôt public | Construction, signature et publication d'une version. |

## Publier une version

1. Mettez à jour la version dans `config/nova.php` (`'version' => 'X.Y.Z'`). C'est elle que l'outil de mise à jour compare à la dernière version publiée.
2. Validez le changement, puis posez l'étiquette `vX.Y.Z` et envoyez-la sur GitHub.
3. Le processus `release.yml` :
   - installe les dépendances de production ;
   - liste les fichiers supprimés depuis l'étiquette précédente, pour que la mise à jour les retire aussi des sites ;
   - construit l'archive avec `scripts/release/build.php`. Elle ne contient que les fichiers que l'outil de mise à jour accepte d'installer ;
   - la signe avec `scripts/release/sign.php` et la clé secrète `UPDATE_SIGNING_KEY` des secrets GitHub ;
   - vérifie la signature avec la clé publique de `config/nova.php` (`scripts/release/verify.php`), ce qui empêche de publier avec une mauvaise clé ;
   - publie `novacreneaux-X.Y.Z.zip` et sa signature `.sig` sur la page Releases.

**Clés de signature.** `php scripts/release/keys.php` crée une paire de clés ed25519, une seule fois. La clé publique va dans `config/nova.php`, la clé secrète dans les secrets GitHub, et nulle part ailleurs. Les extensions ont leur propre paire, distincte.

## Ajouter une fonction : points d'entrée

| Besoin | Où intervenir |
|---|---|
| Nouvelle permission délégable | Constante et libellé dans `app/Support/Permission.php`, puis groupe de routes `permission:` dans `routes/web.php`. |
| Nouveau réglage | Validation et enregistrement dans `Admin/SettingController`, champ dans la vue `admin/settings/...`, description dans `docs/build/sources.py` (sinon la documentation refuse de se construire). |
| Nouvelle règle de réservation | `ReservationService::checkBookable()`, nouveau code d'échec, statut dans `ApiError`, description dans `openapi.yaml`. |
| Nouveau service de webhook | Un fichier JSON dans `resources/webhook-presets` (identifiant, nom, catégorie, hôtes autorisés, méthode, corps avec variables). |
| Nouvel événement de webhook | Constante dans `app/Support/WebhookEvent.php` et appel de `WebhookDispatcher::dispatch()`. |
| Nouvelle commande | Classe dans `app/Console/Commands`, planification éventuelle dans `routes/console.php`. |

## Reconstruire cette documentation

La documentation est produite à partir des chapitres en Markdown de `docs/complete/fr/` et des tableaux générés par `docs/build/sources.py`. Les chapitres sont rangés par partie : `0_intro`, `1_utilisateur`, `2_personnel` et `3_administrateur`. Ils sont numérotés dans l'ordre des noms de fichiers. Un renvoi s'écrit `chapitre [[!n cle]]`, où la clé est le nom du fichier sans son numéro (`11_regles.md` donne `regles`) : le numéro est calculé à la construction.

```
python3 -m venv docs/build/.venv
docs/build/.venv/bin/pip install pymupdf pyyaml
docs/build/.venv/bin/python docs/build/build_complete.py
```

Il faut aussi `pandoc` et le navigateur Brave.

Le script produit deux **éditions**, décrites dans la liste `EDITIONS` de `build_complete.py` :

| Édition | Chapitres | Fichier produit dans `docs/pdf/` |
|---|---|---|
| `generique` | `docs/complete/fr/` | `NovaCreneaux - Documentation complete.pdf` |
| `rascol` | `docs/complete/rascol/` et une partie des chapitres génériques | `NovaCreneaux - Lycee Louis Rascol - Documentation du CDI.pdf` |

Une édition fixe ses parties, la liste de leurs chapitres, sa couverture et son en-tête. Quand elle reprend un chapitre générique qui renvoie à un chapitre absent de l'édition, la table `alias` indique le chapitre qui le remplace. Pour ne construire qu'une édition, donnez son nom : `build_complete.py rascol`.
