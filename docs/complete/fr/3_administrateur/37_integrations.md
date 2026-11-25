# Intégrations

NovaCreneaux communique avec d'autres programmes de trois façons :

- l'**API**, qu'un programme interroge pour lire les créneaux ou réserver ;
- les **webhooks**, que le site envoie de lui-même quand un événement se produit ;
- les **fournisseurs SMS**, qui acheminent les codes de double authentification.

## API REST

Une API (interface de programmation) permet à un autre logiciel d'utiliser le site sans passer par les pages web : une borne d'accueil qui affiche les places libres, un ENT qui réserve pour un élève, un tableur qui récupère le planning. Celle de NovaCreneaux suit le style REST : chaque adresse correspond à une donnée, et les réponses sont au format JSON.

La documentation interactive, avec les schémas complets et un bouton d'essai, se trouve sur votre site à l'adresse **`/api/docs`**. Elle est lue dans `public/docs/openapi.yaml`.

### Points d'accès

Adresse de base : `/api/v1`. Tableau produit à partir de `openapi.yaml` et de `routes/api.php` :

[[api]]

Une API ne donne accès qu'aux réservations **du compte auquel appartient le jeton**, jamais à celles des autres. Les règles de réservation sont exactement celles du site (chapitre [[n regles]]).

### Jetons et droits

Chaque requête, sauf `GET /meta`, porte un jeton dans l'en-tête HTTP :

```
Authorization: Bearer 12|ncr_xxxxxxxxxxxxxxxxxxxx
```

Un jeton n'a que les droits choisis à sa création :

[[abilities]]

Deux façons d'obtenir un jeton :

| Qui | Où | Durée | Particularités |
|---|---|---|---|
| Administrateur | **Intégrations API**, ou `php artisan api:token:create` | 1 à 730 jours, 1 an par défaut | Pour un compte de service (ex. `borne@lycee.fr`) |
| Chaque compte autorisé | **Mon profil > Jetons d'API** | [[tok_durations]] jours | Mot de passe demandé, [[tok_limit]] jetons actifs au plus |

Le secret du jeton n'est affiché **qu'une seule fois**, à sa création. Copiez-le aussitôt. Un jeton se révoque depuis la même page ou avec `php artisan api:token:revoke`. Il cesse alors de fonctionner immédiatement.

L'accès à l'API dépend aussi :

- de l'interrupteur général de la page **Intégrations API** (réglage `api_enabled`), qui coupe l'API pour tout le monde sans supprimer les jetons ;
- de la case « Accès à l'API » du rôle du compte (chapitre [[n roles]]) ;
- de l'état du compte : un compte désactivé reçoit `403 account_disabled`.

Les refus d'accès sont notés dans le journal d'activité (« Requête API refusée »).

### Limites de débit

[[rate_limits]]

Au-delà, l'API répond `429` avec l'en-tête `Retry-After`, qui donne le nombre de secondes à attendre.

### Format des réponses

- Dates au format `AAAA-MM-JJ`, heures `HH:MM`, dans le fuseau du site (donné par `GET /meta`).
- Listes paginées : paramètres `per_page` (1 à 100, 25 par défaut) et `page`. Réponse en trois parties : `data`, `links` et `meta`.
- Erreurs : `{"error": {"code": "...", "message": "...", "details": ...}}`. Le `code` est stable et sert aux programmes ; le `message` s'adresse à une personne et peut changer.
- Refus de réservation : `403` (`banned`), `409` (conflit : complet, doublon...) ou `422` (règle non respectée). Voir le tableau ci-dessous.
- Renvoyer deux fois la même réservation donne `409 already_booked` : aucun doublon n'est possible.

### Codes de refus

Codes renvoyés par l'API quand une règle de réservation refuse la demande (chapitre [[n regles]] pour le détail des règles) :

[[failures]]

### Versions de l'API

La version est dans l'adresse (`/api/v1`). Dans une même version, des champs, des paramètres facultatifs et des adresses peuvent s'ajouter, mais rien n'est retiré ni changé de sens. Un client doit donc ignorer les champs qu'il ne connaît pas. Un changement incompatible sera publié sous `/api/v2`.

### Exemple

```
curl -H "Authorization: Bearer 12|ncr_..." \
     "https://reservations.lycee.fr/api/v1/availability?date=2026-12-01"

curl -X POST -H "Authorization: Bearer 12|ncr_..." \
     -H "Content-Type: application/json" \
     -d '{"slot_id": 3, "date": "2026-12-01"}' \
     https://reservations.lycee.fr/api/v1/reservations
```

Le nom exact des champs de chaque requête est donné par `/api/docs`.

## Webhooks

*Réservé aux administrateurs : **Webhooks**.*

Un webhook est un message que le site envoie automatiquement à une adresse web quand un événement se produit. Exemples : un message dans un salon Discord ou Teams à chaque réservation, ou le déclenchement d'un scénario d'automatisation (Make, n8n, Zapier).

### Événements

[[events]]

### Créer un webhook

| Champ | Sens |
|---|---|
| Nom | Nom interne (ex. « Discord du CDI »). |
| Adresse | URL fournie par le service destinataire. Elle doit commencer par `https://` et ne jamais viser le réseau interne du serveur. |
| Événements | Un ou plusieurs événements du tableau ci-dessus. |
| Format des envois | Un service préréglé, « Standard (JSON signé) » ou « Personnalisé (modèle libre) » (voir plus bas). |
| Afficher les noms dans les messages | Pour un service préréglé ou un format personnalisé : inclut le nom et la classe de la personne. Sinon, le message dit « un élève ». |
| Actif | Un webhook inactif n'envoie rien. |

Dans la partie « Test et secret », le bouton **Envoyer un test** envoie un événement `ping` et affiche la réponse du service. **Régénérer le secret** change la clé de signature : le service destinataire doit alors recevoir la nouvelle. La liste **Derniers envois** donne pour chaque envoi la date, l'événement, le nombre d'essais, la réponse et l'état (Réussi, Échec, En attente).

### Formats

**Services préréglés.** [[presets_count]] services sont prêts à l'emploi. Choisissez le service, collez l'adresse qu'il fournit, et le message est mis en forme pour lui. L'adresse est contrôlée : avec Discord, par exemple, elle doit être sur `discord.com`.

[[presets]]

**Standard (JSON signé).** Le site envoie l'événement complet, au format JSON :

```
{
  "id": "identifiant unique de l'envoi",
  "event": "reservation.created",
  "created_at": "2026-12-01T08:02:11+01:00",
  "data": {
    "reservation": {"id": 42, "date": "2026-12-01",
                    "slot": {"id": 3, "name": "M1", "start_time": "08:00:00", "end_time": "09:00:00"},
                    "resource": {"id": 1, "name": "CDI"}},
    "user": {"id": 7, "name": "Léa Martin", "classe": "2nde B", "role": "user"}
  }
}
```

Aucune adresse e-mail n'est jamais envoyée.

**Personnalisé (modèle libre).** Vous choisissez la méthode (`POST` ou `PUT`), le type de contenu (`json`, `form` ou `text`), le corps (5000 caractères au plus) et des en-têtes. Le corps et les en-têtes acceptent ces variables :

[[variables]]

### Signature et livraison

Chaque envoi porte ces en-têtes :

| En-tête | Contenu |
|---|---|
| `X-Nova-Event` | Nom de l'événement |
| `X-Nova-Delivery` | Identifiant de l'envoi, identique d'un essai à l'autre (le destinataire peut ignorer un doublon) |
| `X-Nova-Timestamp` | Horodatage de l'envoi |
| `X-Nova-Signature` | `sha256=` suivi du HMAC-SHA256 de `<horodatage>.<corps>`, calculé avec le secret du webhook |

Un programme destinataire vérifie la signature pour s'assurer que le message vient bien du site. Il refuse aussi les horodatages trop anciens.

**En cas d'échec** (réponse autre que 2xx, délai dépassé), l'envoi est retenté jusqu'à [[wh attempts]] essais au total, après [[wh backoff]] minutes. Un envoi abandonné est noté dans le journal. Après [[wh disable]] échecs de suite, le webhook est **désactivé automatiquement** et le journal le signale. L'historique des envois est conservé [[wh keep]] jours.

Les envois partent par la tâche planifiée (chaque minute) ou, sans cron, après la réponse de la requête suivante.

**Réseau interne.** Par défaut, une adresse `http://` ou une adresse du réseau local est refusée. Cela protège le serveur contre l'utilisation d'un webhook pour atteindre ses services internes. Pour un service interne de confiance, mettez `NOVA_WEBHOOKS_ALLOW_PRIVATE=true` dans `.env`.

## Fournisseurs SMS

*Paramètres > Sécurité.* La double authentification par SMS passe par l'un de ces fournisseurs :

| Fournisseur | Fonctionnement d'après le code |
|---|---|
| `ntfy` | Envoie le code en notification à un « sujet » ntfy (serveur public `ntfy.sh` ou serveur auto-hébergé). Ce n'est pas un vrai SMS : la personne reçoit la notification dans l'application ntfy. |
| `android_gw` | Passerelle SMS installée sur un téléphone Android (adresse, identifiant, mot de passe). Le certificat HTTPS de la passerelle n'est pas vérifié, pour accepter un téléphone du réseau local. |
| `free_mobile` | API de notification de Free Mobile. Le numéro du destinataire n'est pas transmis : le message part vers la ligne Free Mobile propriétaire de la clé. Ne convient que pour un seul compte. |
| `webhook` | Appel d'une adresse quelconque (`GET` ou `POST`). Les mots `{to}` et `{message}` de l'adresse ou du corps sont remplacés par le numéro et le message. Convient aux services SMS qui ont une API HTTP. |
