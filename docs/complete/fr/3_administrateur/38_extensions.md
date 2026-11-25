# Extensions

Une extension ajoute une fonction au site (par exemple des statistiques) sans modifier son code. Ce chapitre s'adresse d'abord à l'administrateur. La dernière section décrit le format d'une extension pour les développeurs.

## État en version [[version]]

La gestion des extensions est en place : catalogue, installation, activation, désactivation, suppression et commande `plugin:manage`. En revanche, la **clé publique des extensions** de `config/nova.php` est **vide** dans cette version. Par conséquent :

- l'installation depuis le catalogue est refusée avec le message « La clé de signature des extensions n'est pas configurée : installation refusée. » ;
- une extension copiée à la main est refusée, sauf en développement avec `NOVA_PLUGINS_ALLOW_UNSIGNED=true`.

Les extensions deviendront installables quand une version publiée contiendra cette clé.

## Gérer les extensions

*Réservé aux administrateurs : **Extensions**.*

**Catalogue.** La page lit la liste des extensions publiées dans le dépôt GitHub du catalogue (`YoannFm/NovaCreneaux-plugins` par défaut). Cette liste est signée, et une liste dont la signature est fausse est refusée. Le bouton d'actualisation la relit.

**Installer.** Le site télécharge l'archive, vérifie sa signature et son contenu (taille, nombre de fichiers, chemins interdits, liens symboliques refusés), puis la décompresse dans `plugins/<identifiant>/`. Une extension déjà installée est remplacée par la nouvelle version.

**Activer.** Le site vérifie que l'extension est compatible : même version de l'API des extensions ([[plugin_api]] pour ce site) et version minimale de NovaCreneaux. Si l'extension crée des tables, **le site sauvegarde d'abord la base**. Si cette sauvegarde échoue, rien n'est modifié. Les permissions et les entrées de menu de l'extension apparaissent alors dans l'administration.

**Désactiver.** L'extension cesse d'être chargée. Ses permissions n'accordent plus rien, mais restent enregistrées dans les rôles.

**Supprimer.** Possible seulement pour une extension désactivée. Ses fichiers sont supprimés. **Ses tables restent en base**, pour que ses données ne soient pas perdues par erreur.

**Sécurité de fonctionnement.** L'état des extensions (actives, en échec) est un fichier, `storage/app/plugins.json`, lu avant la base de données. Une extension qui plante au chargement est désactivée automatiquement et le site continue de fonctionner. Une extension qui rendrait la base inutilisable peut toujours être désactivée en ligne de commande :

```
php artisan plugin:manage list
php artisan plugin:manage disable <identifiant>
```

## Format d'une extension (développeurs)

Une extension est un dossier qui contient au minimum un fichier `plugin.json`. Exemple tiré de `app/Plugins/Manifest.php` :

```
{
[[manifest]]
}
```

| Clé | Règle de validation |
|---|---|
| `id` | Lettres minuscules, chiffres et tirets, 3 à 40 caractères, commence par une lettre. |
| `name`, `description` | 80 et 300 caractères au plus. |
| `version` | Forme `1.2.3`. |
| `api` | Numéro entier de l'API des extensions visée ([[plugin_api]]). |
| `requires_nova` | Version minimale de NovaCreneaux, forme `1.6.0`. |
| `url` | Commence par `https://`. |
| `authors` | Liste de noms (100 caractères au plus chacun). |
| `autoload` | Espaces de noms PHP vers dossiers. L'espace de noms commence par `NovaPlugin\` : une extension ne peut pas se faire passer pour le code du site. |
| `providers` | Classes de démarrage, prises dans les espaces de noms déclarés. Chacune étend `App\Plugins\PluginProvider`. |
| `permissions` | Clé en minuscules vers libellé (100 caractères au plus). Elles deviennent `id.cle` dans les rôles. |
| `menu` | Entrées du menu de l'administration : libellé, icône, route, permission (ou `admin`). |

**Ce que fournit `PluginProvider` :**

- `loadViews()` : vues du dossier `resources/views`, utilisables avec le préfixe de l'extension (`view('stats::index')`) ;
- `adminRoutes($fichier, $permission)` : routes servies sous `/admin/p/<id>/...`, nommées `admin.plugin.<id>....`, réservées au personnel qui a la permission donnée (déclarée dans `plugin.json`), ou aux administrateurs avec `admin`.

Les migrations du dossier `database/migrations` de l'extension sont lancées par le site à l'activation, et à chaque mise à jour de l'extension active. Une mise à jour du site ne les lance jamais.

**Publier une extension.** Les scripts `scripts/plugins/pack.php` (construire l'archive) et `scripts/plugins/catalog.php` (produire l'index du catalogue) servent à l'éditeur du catalogue, qui signe archives et index avec sa clé secrète.
