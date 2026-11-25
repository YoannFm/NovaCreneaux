# Présentation

## À quoi sert NovaCreneaux

NovaCreneaux est un logiciel libre de réservation de créneaux pour un établissement : CDI, médiathèque, salle de travail, postes informatiques, matériel prêté. Les personnes qui réservent (élèves, adhérents, agents...) choisissent un jour et un créneau en quelques clics. Le personnel prépare les ressources et les créneaux, suit le planning du jour et gère les comptes depuis une administration.

C'est une application web : elle s'installe sur un serveur et s'utilise depuis un navigateur, sur ordinateur comme sur téléphone. L'interface est en français, avec un thème clair et un thème sombre.

Ce document décrit la version **[[version]]**.

![Page de réservation d'un créneau](images/reservation.png)

## Organisation de ce document

Le document est divisé en trois parties, une par public. Chacune peut se lire seule.

| Partie | Pour qui | Contenu |
|---|---|---|
| **Partie 1 - Utilisateurs** | Les personnes qui réservent (élèves, adhérents...) | Se connecter, réserver, annuler, profil, double authentification, règles de réservation, questions fréquentes |
| **Partie 2 - Personnel** | Les membres du personnel qui ont accès à l'administration | Ressources et créneaux, planning, comptes, sanctions, notifications, pages, journal, droits du personnel, cas pratiques, questions fréquentes |
| **Partie 3 - Administrateur** | La personne qui installe et règle le site, et les développeurs | Licence, installation, configuration, rôles, exploitation, API et webhooks, extensions, guide du développeur, dépannage, historique des versions, cas d'usage et synthèse |

Ce premier chapitre, commun aux trois publics, présente le logiciel et son vocabulaire.

## Fonctionnalités clés

**Pour les personnes qui réservent**

- réservation d'un créneau par jour, avec les places restantes affichées ;
- « Mes réservations » : historique et annulation jusqu'à l'heure limite indiquée ;
- e-mails de confirmation et d'annulation, notifications sur le site ;
- double authentification facultative : application, e-mail, SMS ou clé physique.

**Pour le personnel**

- ressources (salles, postes, matériel), créneaux, jours de fermeture et heure d'ouverture des réservations ;
- planning du jour par ressource et par créneau, avec export CSV et PDF ;
- réservation pour le compte d'une personne qui n'a pas accès au sien ;
- limites par rôle : réservations par jour et par semaine, délai d'annulation, jours à l'avance ;
- comptes : création, import CSV, actions groupées, avertissements et suspensions ;
- notifications, pages de contenu, barre de navigation, couleurs du site ;
- journal d'activité détaillé (qui, quand, depuis quelle adresse, valeurs avant et après).

**Pour l'administrateur**

- personnalisation sans toucher au code : nom, logo, couleurs, vocabulaire, textes et signature des e-mails ;
- mises à jour depuis l'administration, par archive signée, avec retour en arrière possible ;
- sauvegardes automatiques de la base de données ;
- API REST documentée, jetons d'accès et webhooks vers d'autres services ;
- extensions signées, installables depuis un catalogue.

## Compatibilité

| Élément | Exigence |
|---|---|
| PHP | 8.3 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `zip`, `sodium` et `dom` |
| Base de données | MySQL ou MariaDB |
| Serveur web | Apache, Nginx ou équivalent, racine du site sur le dossier `public/` |
| Navigateur | Navigateur récent ; la clé physique demande un navigateur compatible WebAuthn |
| Node.js | Inutile : les fichiers compilés sont fournis dans l'archive |

NovaCreneaux repose sur le cadriciel (framework) PHP Laravel 13.

## Vocabulaire

Les mêmes mots désignent toujours les mêmes choses dans ce document et dans l'application.

| Terme | Sens |
|---|---|
| Ressource | Ce qui se réserve : une salle, un ensemble de postes, du matériel. Elle a une heure d'ouverture des réservations, une heure de fermeture et des jours de fermeture. |
| Créneau | Une plage horaire d'une ressource (ex. « M1 », 08:00-09:00), avec une capacité : le nombre de places. Le même créneau revient chaque jour d'ouverture. |
| Réservation | Une place prise par un compte sur un créneau, pour une date. |
| Compte | L'accès d'une personne au site (nom, e-mail, mot de passe, rôle). |
| Rôle | Groupe de comptes qui partagent les mêmes limites de réservation et les mêmes permissions. |
| Permission | Droit d'accès à une partie de l'administration (ex. « Ressources et créneaux »). |
| Personnel | Comptes dont le rôle donne au moins une permission. Ils entrent dans l'administration, limitée à leurs permissions. |
| Administrateur | Compte dont le rôle est administrateur : il a toutes les permissions et règle le site. |
| Avertissement | Remarque consignée sur un compte, sans effet sur les réservations. |
| Suspension | Interdiction de réserver, jusqu'à une date ou définitive, avec un motif et une gravité. |
| Jeton (d'API) | Clé secrète qui permet à un programme d'utiliser l'API au nom d'un compte. |
| Webhook | Message envoyé automatiquement à un autre service (Discord, Teams...) quand un événement se produit. |
| Extension | Module ajouté au site pour lui donner une fonction de plus. |
| Double authentification | Deuxième vérification à la connexion (code ou clé), en plus du mot de passe. |

Le site peut remplacer « élève » et « classe » par d'autres mots (adhérent, service...) : voir le chapitre [[n parametres]]. Ce document emploie « élève » et « classe », les valeurs par défaut.

## Assistance

- Signaler un problème ou proposer une amélioration : page « Issues » du dépôt GitHub `YoannFm/NovaCreneaux`.
- Versions publiées et notes de version : page « Releases » du même dépôt.
- Documentation de l'API sur votre propre site : adresse `/api/docs`.
