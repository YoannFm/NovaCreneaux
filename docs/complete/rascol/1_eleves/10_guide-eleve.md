# Guide de l'élève

Ce chapitre explique comment utiliser le site pour venir au CDI : se connecter, réserver une place, annuler, gérer son compte.

## Se connecter

Ouvrez l'adresse du site de réservation du CDI, `https://cdi.rascol.net`. Saisissez votre adresse e-mail du lycée, de la forme **`prenom.nom@e.rascol.net`**, et votre mot de passe. La case « Se souvenir de moi » garde la session ouverte sur votre appareil personnel ; ne la cochez pas sur un ordinateur partagé du lycée.

- L'adresse e-mail ne tient pas compte des majuscules : `Lea.Martin@e.rascol.net` et `lea.martin@e.rascol.net` désignent le même compte.
- Après **5 essais** de connexion manqués, le formulaire bloque les tentatives pendant un court moment.
- Un compte désactivé par les documentalistes ne peut plus se connecter.

## Votre premier mot de passe

Votre compte est créé par les documentalistes, en général en début d'année, avec votre adresse `prenom.nom@e.rascol.net`. Vous recevez alors un e-mail avec un lien pour choisir votre mot de passe. Si vous n'avez rien reçu, ou si vous avez oublié votre mot de passe :

1. sur la page de connexion, cliquez sur « Mot de passe oublié ? » ;
2. saisissez votre adresse e-mail du lycée (`prenom.nom@e.rascol.net`) ;
3. ouvrez le lien reçu, valable **60 minutes**, et choisissez un mot de passe (8 caractères au moins).

Si aucun e-mail n'arrive, regardez dans les courriers indésirables, puis demandez au CDI.

> **Exemple.** Hugo, en 1re 5, a été inscrit à la rentrée mais n'a jamais choisi de mot de passe. Sur la page de connexion, il clique sur « Mot de passe oublié ? », saisit `hugo.durand@e.rascol.net`, ouvre le lien reçu dans l'heure et choisit son mot de passe. Il peut ensuite se connecter normalement.

**L'inscription libre est désactivée au lycée** : il n'y a pas de lien « Créer un compte » sur la page de connexion. Tous les comptes sont créés par le personnel du CDI. Si vous n'avez pas de compte, adressez-vous au CDI.

## La page d'accueil

Après connexion, l'accueil affiche :

- le nombre de créneaux encore libres aujourd'hui ;
- vos prochaines réservations ;
- des raccourcis vers « Réserver un créneau », « Mes réservations » et « Mon profil ».

## Réserver une place au CDI

1. Ouvrez **Réserver**.
2. Choisissez la date parmi les jours proposés.
3. Les créneaux du CDI s'affichent, avec le nombre de places restantes.
4. Cliquez sur **Réserver** sous le créneau voulu. Le message « Réservation confirmée ! » s'affiche et vous recevez un e-mail de confirmation.

[[fig rascol-reservation]]

Les captures viennent du site du CDI (`cdi.rascol.net`), affiché depuis le compte documentaliste. Un élève voit la même page, avec seulement aujourd'hui et demain.

> **Exemple : Léa, en 2nde 3, veut venir au CDI jeudi pendant la première heure du midi.**
>
> 1. Mercredi, à partir de **17 h**, elle se connecte et ouvre **Réserver**.
> 2. Elle choisit **jeudi**, repère le créneau « 11h55 - 12h50 », qui affiche le nombre de places libres, par exemple « 33 libres / 40 ».
> 3. Elle clique sur **Réserver** : « Réservation confirmée ! » s'affiche et un e-mail arrive dans sa messagerie.
> 4. Jeudi matin, un professeur la convoque à midi : elle ouvre **Mes réservations** et clique sur **Annuler** avant **11 h 25** (30 minutes avant 11 h 55). La place est rendue à un autre élève.

**Quand peut-on réserver ?** Au CDI, chaque jour à **17 h**, la journée suivante s'ouvre aux réservations. Le jour même, on peut réserver jusqu'à **11 h 30**, juste avant le premier créneau de midi. Le CDI est fermé aux réservations le **mercredi**, le **samedi** et le **dimanche**. Le chapitre suivant détaille ces horaires jour par jour.

Si la réservation est refusée, un message en donne la raison : créneau complet, limite atteinte, réservations pas encore ouvertes, compte suspendu... Le chapitre [[n regles]] explique chaque message.

## Mes réservations

**Mes réservations** liste toutes vos réservations, des plus récentes aux plus anciennes : date, créneau, date de la réservation. Les réservations passées sont marquées « Passée ».

Vous ne pouvez plus venir ? **Annulez** pour libérer la place pour un autre élève. L'annulation est possible avant le début du créneau, et jusqu'à l'heure limite indiquée sur la ligne si un délai d'annulation est réglé. Un e-mail confirme l'annulation.

Si un documentaliste annule une de vos réservations, vous recevez un e-mail et une notification sur le site, avec le motif.

## Notifications

La cloche en haut de la page signale les messages non lus : informations du CDI, réservations annulées par un documentaliste. La page **Notifications** permet de les marquer comme lues et de les supprimer.

## Mon profil

**Mon profil** regroupe :

- **Informations personnelles** : nom complet et adresse e-mail. Le changement de nom peut être désactivé.
- **Mot de passe** : saisissez le mot de passe actuel, puis le nouveau deux fois. Vos autres sessions ouvertes (par exemple sur un autre appareil) sont alors fermées.
- **Préférences de thème** : clair, sombre ou celui de l'appareil. Le bouton soleil ou lune en haut de la page change aussi le thème.
- **Double authentification** : voir plus bas.
- **Zone de danger** : suppression de votre compte, si elle est autorisée. Elle est définitive et demande votre mot de passe.

## Double authentification

La double authentification ajoute une vérification après le mot de passe : même si quelqu'un connaît votre mot de passe, il ne peut pas se connecter à votre place. Elle est facultative pour les élèves.

| Méthode | Fonctionnement |
|---|---|
| Application d'authentification | Une application (Google Authenticator, Aegis...) affiche un code à [[mfa digits]] chiffres qui change toutes les [[mfa period]] secondes. Pour l'activer, scannez le QR code puis saisissez un code. |
| Code par e-mail | Un code est envoyé à votre adresse, valable [[mfa otp_minutes]] minutes. |
| Code par SMS | Proposé seulement si un fournisseur SMS est configuré. |
| Clé physique | Clé de sécurité ou capteur de l'appareil (empreinte, visage). |

À l'activation de l'application, le site affiche [[mfa recovery]] **codes de récupération**. Notez-les : chacun permet une connexion si vous perdez votre téléphone. Après [[mfa max]] codes faux, il faut attendre [[mfa decay]] minutes.

Si vous avez perdu tous vos moyens de connexion, venez au CDI : un documentaliste peut désactiver votre double authentification.
