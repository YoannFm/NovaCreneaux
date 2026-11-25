# Cas d'usage et synthèse

## Cas 1 : ouvrir le CDI aux réservations

*Objectif : le CDI accueille 30 élèves par heure, du lundi au vendredi, de 8 h à 17 h. Les élèves réservent la veille à partir de 17 h, deux heures par jour au plus.*

1. **Paramètres > Général** : nom du site « CDI du lycée », qui contacter « le CDI », fuseau `Europe/Paris`.
2. **Ressources > Nouvelle ressource** :
   - nom « CDI » ;
   - heure d'ouverture des réservations **17:00** ;
   - heure de fermeture **17:00** ;
   - jours de fermeture : samedi, dimanche ;
   - active.
3. **Créneaux** de la ressource : « 8 h » (08:00-09:00), « 9 h » (09:00-10:00), et ainsi de suite jusqu'à « 16 h » (16:00-17:00). Capacité **30** pour chacun.
4. **Paramètres > Réservations**, rôle Utilisateur : **2** réservations par jour, **8** par semaine, délai d'annulation **30** minutes, **1** jour à l'avance.
5. **Paramètres > Accueil** : « Réservez votre place au CDI la veille à partir de 17 h. »
6. Créez les comptes par import CSV, puis envoyez un lien de réinitialisation du mot de passe par action groupée (chapitre [[n guide-personnel]]).

**Résultat :** chaque jour à 17:00, le lendemain s'ouvre. Un élève réserve au plus deux heures par jour et peut annuler jusqu'à 30 minutes avant le début.

## Cas 2 : déléguer le planning à un surveillant

*Objectif : un surveillant consulte le planning, réserve pour un élève sans accès à son compte et annule en cas d'absence, sans toucher au reste.*

1. **Rôles > Nouveau rôle** : nom interne `surveillant`, libellé « Surveillant », permissions **Réservations de tous les comptes** et **Journal d'activité (lecture)**. Ne cochez pas « Administrateur ».
2. **Utilisateurs** : ouvrez la fiche du surveillant et donnez-lui le rôle « Surveillant ».
3. Le surveillant se connecte. Il voit « Panel administrateur » avec seulement **Réservations** et **Activité**.
4. Pour réserver pour un élève : **Réservations**, recherche de l'élève, choix du créneau. Les limites du rôle de l'élève ne s'appliquent pas.
5. Pour imprimer la liste du jour : bouton **PDF** du planning.

**Sécurité :** le surveillant ne peut ni créer de ressource, ni modifier un compte, ni voir les réglages. Si la double authentification est imposée au personnel (**Paramètres > Sécurité**), il devra l'activer à sa première connexion.

## Cas 3 : annoncer les réservations dans Discord

*Objectif : un message arrive dans un salon Discord à chaque réservation et à chaque annulation, sans le nom des élèves.*

1. Dans Discord : paramètres du salon > Intégrations > Webhooks > Nouveau webhook > « Copier l'URL du webhook ».
2. **Webhooks > Nouveau webhook** :
   - nom « Discord CDI » ;
   - format des envois : **Discord** ;
   - adresse : l'URL copiée ;
   - événements `reservation.created` et `reservation.cancelled` ;
   - « Afficher les noms dans les messages » décoché ;
   - actif.
3. Cliquez sur **Envoyer un test** : un message de test arrive dans le salon.
4. Faites une réservation d'essai : le message « Nouvelle réservation » apparaît avec la ressource, le créneau et la date, et « un élève » à la place du nom.

**Si rien n'arrive :** vérifiez la tâche cron (chapitre [[n installation]]) et la liste « Derniers envois » du webhook. Après 10 échecs de suite, le webhook se désactive (chapitre [[n integrations]]).

## Cas 4 : afficher les places libres sur une borne

*Objectif : un écran à l'entrée du CDI affiche les places restantes de la journée.*

1. Créez un compte de service, par exemple `borne@lycee.fr`, avec un rôle sans permission.
2. Créez un jeton en lecture seule :

   ```
   php artisan api:token:create borne@lycee.fr "Borne d'accueil" --ability=resources:read
   ```

3. Copiez le secret affiché : il ne sera plus montré.
4. Programmez l'écran pour appeler toutes les minutes `GET /api/v1/availability?date=<aujourd'hui>`, avec l'en-tête `Authorization: Bearer <secret>`. Ce rythme reste bien sous la limite de 60 requêtes par minute.

## Synthèse

NovaCreneaux [[version]] est une application web de réservation de créneaux. Elle est libre (licence MIT), en français, installable sur un hébergement PHP 8.3 et MySQL ou MariaDB, sans Node.js en production.

| Domaine | Ce qu'il faut retenir | Chapitre |
|---|---|---|
| Installation | Racine du site sur `public/`, assistant en 5 étapes, cron chaque minute, copie de `.env` et de `APP_KEY` | [[n installation]] |
| Réservation | Une ressource a des créneaux avec capacité ; un jour s'ouvre à l'heure d'ouverture de la ressource ; 11 contrôles avant d'accepter | [[n guide-utilisateur]], [[n regles]] |
| Limites | Par rôle : par jour, par semaine, délai d'annulation, jours à l'avance (`-1` = sans limite) | [[n regles]] |
| Personnel | 9 permissions délégables ; réglages, rôles, mises à jour, sauvegardes, API, webhooks et extensions restent aux administrateurs | [[n guide-personnel]], [[n droits-personnel]], [[n roles]] |
| Configuration | [[settings_count]] réglages dans l'administration, dont [[secret_count]] secrets chiffrés ; `.env` pour la base et la clé | [[n parametres]], [[n configuration-serveur]] |
| Exploitation | Mise à jour signée en un clic, avec retour possible des fichiers ; sauvegardes `.sql.gz` automatiques ; journal purgé à 90 jours | [[n exploitation-site]], [[n exploitation-serveur]] |
| Intégrations | API REST `/api/v1` avec jetons et droits ; webhooks signés vers [[presets_count]] services ; 4 fournisseurs SMS | [[n integrations]] |
| Extensions | Système en place, installation bloquée en [[version]] faute de clé publique des extensions | [[n extensions]] |
| Développement | Laravel 13, règles centralisées, [[tests_count]] tests automatisés, publication par étiquette `vX.Y.Z` | [[n developpeur]] |

**Points d'attention relevés en rédigeant cette documentation (version [[version]]) :**

- le nombre maximal de réservations par jour demandé par l'assistant d'installation n'est pas appliqué : seules les limites par rôle comptent ;
- les réglages « Description du site » et « Mots-clés » sont enregistrés, mais aucune page ne les utilise ;
- la clé publique des extensions est vide, donc aucune extension n'est installable ;
- la méthode SMS `ntfy` envoie une notification et non un SMS ; `free_mobile` n'envoie qu'à la ligne propriétaire de la clé ;
- la passerelle SMS Android ne vérifie pas le certificat HTTPS ;
- la base de données ne se restaure qu'en ligne de commande.
