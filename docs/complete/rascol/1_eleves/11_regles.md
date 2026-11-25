# Les horaires et les règles de réservation

Ce chapitre explique quand on peut réserver une place au CDI, ce que le site vérifie, et le sens de chaque message de refus.

## Les horaires de réservation du CDI

Trois réglages fixent les horaires :

- **17:00, ouverture d'un nouveau jour** : chaque jour à 17 h, une journée de plus devient réservable ;
- **11:30, fermeture du jour J** : après 11 h 30, on ne peut plus réserver pour le jour même ;
- **jours fermés** : mercredi, samedi et dimanche, aucune réservation n'est possible.

Le nombre de jours que l'on peut réserver à l'avance dépend de la limite « Réservation à l'avance » du rôle des élèves. Avec une limite d'**un jour**, réglage habituel pour réserver « la veille », le calendrier de la semaine est le suivant :

| Pour venir le... | On peut réserver... |
|---|---|
| Lundi | du dimanche 17:00 au lundi 11:30 |
| Mardi | du lundi 17:00 au mardi 11:30 |
| Mercredi | jamais : le CDI est fermé aux réservations |
| Jeudi | du mercredi 17:00 au jeudi 11:30 |
| Vendredi | du jeudi 17:00 au vendredi 11:30 |
| Samedi, dimanche | jamais |

Deux conséquences à connaître :

- **Pour le lundi**, les réservations ouvrent le **dimanche à 17 h**. Le vendredi soir, on ne peut pas encore réserver pour le lundi.
- **Le jour même**, les réservations ferment à 11 h 30, soit 25 minutes avant le premier créneau (11 h 55). Pensez à réserver la veille au soir, ou le matin avant 11 h 30.

Avant 17 h, le site affiche « Ouverture des réservations aujourd'hui à 17:00. » pour le jour suivant.

> **Exemples avec les horaires du CDI** (réservation à l'avance : 1 jour)
>
> - **Lundi, 9 h.** Léa veut réserver pour le lendemain mardi. Refus : « Ouverture des réservations aujourd'hui à 17:00. ». Elle réessaie à 17 h : la réservation passe.
> - **Lundi, 11 h 40.** Hugo veut venir au CDI à 12 h 50 le jour même. Refus : « Réservations fermées pour aujourd'hui. » (il est plus de 11 h 30).
> - **Vendredi, 18 h.** Inès veut réserver pour lundi. La page ne propose que vendredi et samedi, et samedi est refusé (« Ressource fermée ce jour. »). Le lundi n'apparaît qu'à partir du dimanche à 17 h.
> - **Mardi, 18 h.** Léa veut réserver pour mercredi. Refus : « Ressource fermée ce jour. ». Le jeudi s'ouvrira mercredi à 17 h.

## Ce qui est vérifié, dans l'ordre

Quand vous réservez, le site contrôle successivement les points suivants. Il s'arrête au premier refus.

1. **Suspension.** Un compte suspendu ne peut pas réserver.
2. **Disponibilité.** Le créneau et le CDI doivent être ouverts aux réservations.
3. **Date passée.** Impossible de réserver un jour passé.
4. **Jour fermé.** Pas de réservation le mercredi, le samedi et le dimanche.
5. **Aujourd'hui après 11 h 30.** Les réservations du jour même sont fermées.
6. **Trop tôt.** Le jour demandé n'est pas encore ouvert (il s'ouvre à 17 h).
7. **Créneau commencé.** Pour le jour même, un créneau déjà commencé n'est plus réservable.
8. **Limite par jour** de votre rôle.
9. **Limite par semaine** de votre rôle (du lundi au dimanche).
10. **Créneau complet** : plus aucune place.
11. **Déjà réservé** : vous avez déjà ce créneau à cette date.

## Les limites de votre compte

Les documentalistes fixent quatre limites pour les élèves, dans **Paramètres > Réservations**. Au CDI : **1** réservation par jour, **4** par semaine, annulation jusqu'à **30 minutes** avant le créneau, réservation **1 jour** à l'avance.

[[limits]]

La page **Réserver** applique toujours les valeurs en vigueur.

> **Exemple avec les limites du CDI (1 par jour, 4 par semaine).** Inès a réservé jeudi « 11h55 - 12h50 ». Si elle tente aussi jeudi « 12h50 - 13h45 », le site répond « Limite de 1 réservation(s) par jour atteinte. ». Si elle a déjà réservé lundi, mardi, jeudi et vendredi, une cinquième réservation la même semaine est refusée avec « Limite de 4 réservation(s) par semaine atteinte. ».

## Annuler une réservation

Vous pouvez annuler votre réservation si :

1. la date n'est pas passée ;
2. le créneau n'a pas commencé ;
3. le délai d'annulation de **30 minutes** n'est pas dépassé : le créneau de 11 h 55 s'annule jusqu'à 11 h 25, celui de 12 h 50 jusqu'à 12 h 20. L'heure limite est indiquée dans « Mes réservations ».

Annuler dès que vous savez que vous ne viendrez pas libère la place pour un autre élève.

> **Exemple.** Hugo a réservé jeudi « 12h50 - 13h45 ». Il peut annuler lui-même jusqu'à **12 h 20**. À 12 h 30, le site répond « Annulation impossible : le délai de 30 minute(s) avant le créneau est dépassé. ». Il prévient alors le personnel du CDI, qui peut annuler à sa place.

## Messages de refus

Voici les messages que le site peut afficher. Ils sont extraits du code du logiciel ; « ... » remplace une valeur calculée (date, nombre, heure).

[[failures_user]]

Pour une suspension, le message donne aussi la date de fin et le motif. Pour une date trop lointaine, le site peut aussi afficher « Vous ne pouvez pas réserver plus de ... jour(s) à l'avance. ».

## Ce que vous recevez

| Événement | E-mail | Notification sur le site |
|---|---|---|
| Réservation faite (par vous ou par un documentaliste pour vous) | oui, confirmation | non |
| Réservation annulée par vous | oui | non |
| Réservation annulée par un documentaliste | oui, avec le motif | oui |
