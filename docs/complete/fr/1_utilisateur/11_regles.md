# Règles de réservation

Ce chapitre explique précisément ce que le site vérifie avant d'accepter ou d'annuler une réservation, et le sens de chaque message de refus.

## Ressources, créneaux et places

- Une **ressource** a une heure d'ouverture des réservations (`open_time`), une heure de fermeture (`close_time`), des jours de fermeture (ex. samedi et dimanche) et un état actif ou inactif.
- Un **créneau** appartient à une ressource. Il a un nom, une heure de début, une heure de fin, une capacité (1 à 500 places) et un état actif ou inactif.
- Le nombre de **places restantes** d'un créneau, pour une date, est sa capacité moins le nombre de réservations déjà prises ce jour-là.

## Ce qui est vérifié, dans l'ordre

Quand une personne réserve un créneau pour une date, le site contrôle successivement les points suivants. Il s'arrête au premier refus.

1. **Suspension.** Un compte suspendu ne peut pas réserver. Le message donne la date de fin (ou « définitivement ») et le motif, puis « Pour toute question, contactez ... ».
2. **Disponibilité.** Le créneau et sa ressource doivent être actifs.
3. **Date passée.** Impossible de réserver hier.
4. **Jour de fermeture.** La ressource doit être ouverte ce jour de la semaine.
5. **Aujourd'hui après la fermeture.** Pour le jour même, les réservations ferment à l'heure de fermeture de la ressource.
6. **Fenêtre à l'avance.** La date ne doit pas dépasser la limite « à l'avance » du rôle. Le jour le plus lointain ne s'ouvre qu'à l'heure d'ouverture de la ressource (voir l'exemple ci-dessous).
7. **Créneau commencé.** Pour le jour même, un créneau déjà commencé n'est plus réservable.
8. **Limite par jour** du rôle : nombre de réservations déjà prises par ce compte à cette date.
9. **Limite par semaine** du rôle : réservations du lundi au dimanche de la semaine visée.
10. **Créneau complet** : plus aucune place.
11. **Doublon** : le compte a déjà réservé ce créneau à cette date.

Le créneau est verrouillé pendant l'enregistrement : deux demandes simultanées ne peuvent pas dépasser la capacité.

**Exemple de fenêtre.** Une ressource ouvre ses réservations à 17:00 et le rôle « Élève » a une limite d'un jour à l'avance.

| Moment | Dates réservables |
|---|---|
| Lundi 16:30 | lundi seulement (jusqu'à l'heure de fermeture) |
| Lundi 17:00 | lundi et mardi |
| Mardi 08:00 | mardi seulement (mercredi ouvre à 17:00) |

> **Exemples de refus avec cette ressource** (ouverture à 17:00, fermeture du jour même à 18:00, fermée le samedi et le dimanche, rôle limité à 1 jour à l'avance)
>
> - **Lundi 9 h**, réservation pour mardi : « Ouverture des réservations aujourd'hui à 17:00. ».
> - **Lundi 18 h 30**, réservation pour le jour même : « Réservations fermées pour aujourd'hui. ».
> - **Vendredi 18 h**, réservation pour samedi : « Ressource fermée ce jour. ».
> - **Mardi 10 h 15**, réservation du créneau 10:00-11:00 du jour : « Ce créneau a déjà commencé. ».
> - **Jeudi**, troisième réservation de la journée avec une limite de 2 par jour : « Limite de 2 réservation(s) par jour atteinte. ».

## Limites par rôle

Chaque rôle a quatre limites, réglées dans **Paramètres > Réservations** :

[[limits]]

La valeur `-1` en base signifie « sans limite ». Un compte sans rôle n'a pas de limite par jour ou par semaine, et peut réserver 30 jours à l'avance.

[[fig limites]]

## Annulation par la personne

Une personne peut annuler sa propre réservation si :

1. la date n'est pas passée ;
2. le créneau n'a pas commencé ;
3. le délai d'annulation de son rôle n'est pas dépassé. Exemple : avec 60 minutes, un créneau de 10:00 s'annule jusqu'à 9:00. Avec « Toujours possible », l'annulation reste ouverte jusqu'au début du créneau.

> **Exemple.** Hugo a réservé le créneau 14:00-15:00, et son rôle a un délai d'annulation de 60 minutes. À 12 h 50, il annule sans problème. À 13 h 20, le site répond « Annulation impossible : le délai de 60 minute(s) avant le créneau est dépassé. ». Il doit alors demander au personnel, qui peut annuler sans délai.

## Messages et codes de refus

Voici les messages que le site peut afficher quand une réservation ou une annulation est refusée. Ils sont extraits du code ; « ... » remplace une valeur calculée (date, nombre, heure).

[[failures_user]]

Deux codes ont un second message : `banned` existe aussi en version datée (« suspendu jusqu'au ... »), et `advance_limit` donne aussi « Vous ne pouvez pas réserver plus de ... jour(s) à l'avance. ».

## Notifications envoyées

| Événement | E-mail | Notification sur le site |
|---|---|---|
| Réservation créée (par vous ou par le personnel pour vous) | oui, confirmation | non |
| Réservation annulée par vous | oui | non |
| Réservation annulée par le personnel | oui, avec le motif | oui |

Si l'envoi d'un e-mail échoue, la réservation ou l'annulation est quand même enregistrée.
