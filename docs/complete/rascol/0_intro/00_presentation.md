# Présentation

## Le site de réservation du CDI

Le CDI du lycée Louis Rascol, à Albi, accueille les élèves pour lire, travailler et faire des recherches. Le nombre de places est limité. Pour venir au CDI, un élève réserve donc un créneau sur le site de réservation, depuis un ordinateur ou un téléphone.

Le site s'appuie sur le logiciel libre **NovaCreneaux**. Ce document en décrit la version **[[version]]**, telle qu'elle est configurée au CDI.

![Page de réservation du site du CDI](images/rascol-reservation.png)

## Deux types de comptes

| Compte | Qui | Ce qu'il permet |
|---|---|---|
| **Élève** | Chaque élève du lycée | Réserver une place au CDI, consulter et annuler ses réservations, gérer son profil. |
| **Documentaliste** | Les professeurs documentalistes | Tout ce que fait un élève, plus l'administration complète du site : le CDI et ses créneaux, le planning, les comptes des élèves, les réglages, les sauvegardes et les mises à jour. |

Dans le logiciel, les documentalistes ont le rôle **Administrateur** et les élèves le rôle **Utilisateur**. Le logiciel permet de créer d'autres rôles, par exemple pour confier le planning à un assistant d'éducation sans lui donner tous les droits (chapitre [[n roles]]). Ce n'est pas le cas aujourd'hui au CDI.

## Organisation de ce document

Ce guide est divisé en trois parties, une par public. Chacune se lit seule.

| Partie | Pour qui | Contenu |
|---|---|---|
| **Partie 1 - Élèves** | Tous les élèves | Se connecter, réserver une place, annuler, profil et double authentification, horaires de réservation du CDI, questions fréquentes |
| **Partie 2 - Documentalistes** | Les documentalistes | Prise en main de l'administration, configuration du CDI, planning et comptes des élèves, paramètres du site, rôles, sauvegardes et mises à jour, cas pratiques, questions fréquentes |
| **Partie 3 - Technique** | La personne qui héberge et maintient le site | Licence, installation, serveur, intégrations, extensions, guide du développeur, dépannage, historique des versions |

## La configuration du CDI

Les exemples de ce document reprennent les réglages actuels du CDI :

| Réglage | Valeur au CDI |
|---|---|
| Ressource | « CDI » : espace de lecture et de travail |
| Ouverture d'un nouveau jour | **17:00** : chaque jour à 17 h, une journée de plus devient réservable |
| Heure fermeture (jour J) | **11:30** : après 11 h 30, on ne réserve plus pour le jour même |
| Jours fermés | **mercredi, samedi et dimanche** |
| Inscription | **désactivée** : les comptes sont créés par les documentalistes, avec l'adresse `prenom.nom@e.rascol.net` |
| Créneaux | **11h55 - 12h50** et **12h50 - 13h45**, pendant la pause de midi, **40 places** chacun |
| Limites d'un élève | **1** réservation par jour, **4** par semaine, annulation jusqu'à **30 minutes** avant, réservation **1 jour** à l'avance |

Ces réglages peuvent changer en cours d'année : la page **Réserver** du site fait toujours foi. Le nom affiché du site est « CDI - Lycée louis rascol ».

## Vocabulaire

| Terme | Sens |
|---|---|
| Ressource | Ce qui se réserve. Au lycée, c'est le CDI. |
| Créneau | Une plage horaire du CDI, avec un nombre de places. Au lycée : « 11h55 - 12h50 » et « 12h50 - 13h45 ». Le même créneau revient chaque jour d'ouverture. |
| Réservation | Une place prise par un élève sur un créneau, pour une date. |
| Rôle | Groupe de comptes qui partagent les mêmes droits et les mêmes limites de réservation (Administrateur, Utilisateur). |
| Avertissement | Remarque inscrite sur le compte d'un élève, sans effet sur ses réservations. |
| Suspension | Interdiction de réserver, jusqu'à une date ou définitive, avec un motif. |
| Double authentification | Deuxième vérification à la connexion (code ou clé), en plus du mot de passe. |
| Administration | Partie du site réservée aux documentalistes, ouverte par le bouton « Panel administrateur ». |

## Assistance

- Élèves : pour toute question sur une réservation ou un compte, adressez-vous aux documentalistes au CDI.
- Documentalistes : le bouton **Support** de l'administration ouvre le serveur Discord d'assistance de NovaCreneaux. Le bouton **Documentation** ouvre la documentation générale du logiciel.
- Les problèmes techniques se signalent sur la page « Issues » du dépôt GitHub `YoannFm/NovaCreneaux`.
