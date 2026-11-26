# Configurer le CDI

Ce chapitre décrit les réglages qui définissent quand et comment les élèves réservent : la ressource « CDI », ses créneaux et les limites du rôle des élèves.

## La ressource CDI

*Menu Ressources, puis le crayon de la ligne « CDI » (page « Modifier une ressource »).*

[[fig ressource-cdi]]

| Champ | Valeur au CDI | Sens |
|---|---|---|
| Nom | CDI | Nom affiché aux élèves (100 caractères au plus). |
| Description | Espace de lecture et de travail du CDI du lycée Louis Rascol... | Texte affiché aux élèves sous le nom (500 caractères au plus). |
| Ouverture d'un nouveau jour | **17:00** | Chaque jour à cette heure, une journée de plus devient réservable, dans la limite « Réservation à l'avance » du rôle. |
| Heure fermeture (jour J) | **11:30** | Après cette heure, on ne réserve plus pour le jour même. |
| Jours fermes | **Mer, Sam, Dim** | Jours sans aucune réservation. |
| Ressource active | activée | Désactivée, la ressource disparaît de la page de réservation, sans annuler les réservations déjà prises. |

**Effet de ces réglages.** Les élèves réservent la veille à partir de 17 h, et le jour même jusqu'à 11 h 30. Pour le lundi, les réservations ouvrent le dimanche à 17 h. Le calendrier complet est au chapitre [[n regles]].

**Modifier les horaires.** Changer l'heure d'ouverture ou de fermeture s'applique immédiatement et n'annule aucune réservation. Les élèves déjà inscrits gardent leur place.

> **Exemple : permettre de réserver le matin même pour le second créneau.** Aujourd'hui, la fermeture à 11:30 bloque aussi le créneau de 12 h 50 dès 11 h 30. Si vous passez « Heure fermeture (jour J) » à **12:40**, un élève peut réserver à 12 h 15 le créneau « 12h50 - 13h45 » du jour même. Le créneau « 11h55 - 12h50 », lui, n'est plus réservable après 11 h 55, car il a commencé.

## Les créneaux du CDI

*Menu Ressources, puis le bouton des créneaux de la ligne « CDI ».*

Chaque créneau est une plage horaire avec un nombre de places. Il revient chaque jour d'ouverture du CDI.

| Champ | Sens |
|---|---|
| Nom | 50 caractères au plus. Au CDI, le nom reprend l'horaire : « 11h55 - 12h50 ». |
| Heure début, Heure fin | Format HH:MM ; la fin doit suivre le début. |
| Capacité maximale | Nombre de places, de 1 à 500 : en général, le nombre de places assises du CDI. |
| Créneau actif | Un créneau inactif n'est plus réservable, sans que les réservations existantes soient annulées. |

**Les créneaux du CDI** : « 11h55 - 12h50 » (11:55-12:50) et « 12h50 - 13h45 » (12:50-13:45), avec une capacité maximale de **40 places** chacun. Ils couvrent les deux heures de la pause de midi.

Comme les réservations du jour même ferment à 11 h 30, avant le premier créneau, les élèves réservent en pratique la veille à partir de 17 h ou le matin même avant 11 h 30.

**Attention :** supprimer un créneau, ou la ressource CDI, **annule toutes les réservations à venir** de ce créneau et prévient les élèves. Pour une fermeture temporaire, désactivez plutôt.

## Les limites des élèves

*Paramètres > Réservations.* Chaque rôle a quatre limites. Les élèves ont le rôle **Utilisateur**.

[[fig rascol-limites]]

[[limits]]

Réglages actuels du CDI, et pourquoi :

| Limite | Conseil | Pourquoi |
|---|---|---|
| Réservation à l'avance | **1 jour** (réglage actuel) | Les élèves réservent la veille à partir de 17 h ; les horaires du chapitre [[n regles]] supposent cette valeur. |
| Réservations par jour | **1** (réglage actuel) | Avec deux créneaux par jour, un élève ne bloque pas les deux heures du midi. |
| Réservations par semaine | **4** (réglage actuel) | Le CDI est ouvert aux réservations 4 jours par semaine : un élève peut venir chaque jour, une fois. |
| Délai d'annulation | **30 minutes** (réglage actuel) | Un délai oblige à annuler tôt, ce qui laisse le temps à un autre élève de réserver. |

Le rôle **Administrateur** (documentalistes) a aussi des limites, mais elles ne s'appliquent pas quand un documentaliste réserve pour un élève.

> **Exemple : la semaine des examens blancs.** L'affluence double. Pour partager les places, passez temporairement les élèves à **2 réservations par semaine**, puis **Enregistrer**. Les réservations déjà prises restent valables ; seules les nouvelles sont comptées. Revenez à 4 la semaine suivante.

## Les textes vus par les élèves

| Où | Réglage | Exemple au CDI |
|---|---|---|
| Paramètres > Général | Nom du site, sous-titre | « CDI - Lycée louis rascol », « Bienvenue sur CDI - Lycée louis rascol. Reservez vos creneaux en quelques clics. » |
| Paramètres > Général | Qui contacter | « le personnel du CDI », repris dans les messages de refus |
| Paramètres > Général | Vocabulaire | « élève », « élèves », « Classe » (valeurs par défaut, à garder) |
| Paramètres > Général | Exemple d'adresse e-mail | « prenom.nom@e.rascol.net », le format des adresses des élèves |
| Paramètres > Compte de l'établissement | Méthode | **Désactivée** : les élèves se connectent avec leur adresse e-mail et le mot de passe du site |
| Paramètres > Authentification | Autoriser l'inscription des utilisateurs | **désactivé** : seuls les documentalistes créent les comptes (un à un ou par import CSV) |
| Paramètres > Accueil | Message en haut de la page de réservation | vide aujourd'hui. Suggestion : « Réservez votre place la veille à partir de 17 h. Le CDI est fermé aux réservations le mercredi. » |
| Paramètres > E-mail | Signature des e-mails | « L'équipe du CDI - Lycée Louis Rascol, Albi » |

Le détail de tous les réglages est au chapitre [[n parametres]].
