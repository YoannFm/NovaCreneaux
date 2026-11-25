# Guide de l'utilisateur

Ce chapitre s'adresse aux personnes qui réservent. Les noms des menus sont ceux de la configuration d'origine : l'établissement peut les avoir renommés.

## Se connecter

Ouvrez l'adresse du site et saisissez votre adresse e-mail et votre mot de passe. La case « Se souvenir de moi » garde la session ouverte sur cet appareil.

- L'adresse e-mail ne tient pas compte des majuscules : `Lea.Martin@...` et `lea.martin@...` désignent le même compte.
- Après **5 essais** de connexion manqués, le formulaire bloque les tentatives pendant un court moment.
- Un compte désactivé par l'administration ne peut plus se connecter.

[[fig connexion]]

## Créer un compte

Le lien « Créer un compte » n'apparaît que si l'établissement a ouvert l'inscription. Sinon, votre compte est créé par le personnel.

Le formulaire demande le nom complet, l'adresse e-mail et un mot de passe (8 caractères au moins par défaut, selon le réglage du site). L'établissement peut aussi :

- limiter l'inscription à certains domaines d'adresse (ex. seulement les adresses `@lycee.fr`) ;
- demander d'accepter des conditions d'utilisation ;
- exiger la vérification de l'adresse e-mail. Un lien vous est alors envoyé, et le site reste inaccessible tant que vous ne l'avez pas ouvert.

## Mot de passe oublié

Sur la page de connexion, « Mot de passe oublié ? » envoie un lien de réinitialisation à votre adresse. Le lien est valable **60 minutes**. Le formulaire limite le nombre de demandes pour éviter les abus.

Si votre compte a été créé par un import, vous n'avez pas de mot de passe connu : utilisez ce lien pour en choisir un.

## La page d'accueil

Après connexion, l'accueil affiche :

- le nombre de créneaux encore libres aujourd'hui ;
- vos prochaines réservations ;
- des raccourcis vers « Réserver un créneau », « Mes réservations » et « Mon profil » ;
- un raccourci vers l'administration, si votre rôle y donne accès.

Un message de l'établissement peut s'afficher en haut de la page de réservation.

## Réserver un créneau

1. Ouvrez **Réserver**.
2. Choisissez la date : aujourd'hui, demain, puis les jours suivants jusqu'à la limite de votre rôle.
3. Pour chaque ressource ouverte ce jour-là, les créneaux s'affichent avec le nombre de places restantes.
4. Cliquez sur le créneau voulu. Le message « Réservation confirmée ! » s'affiche et un e-mail de confirmation vous est envoyé.

[[fig reservation]]

> **Exemple : Léa Martin veut travailler en salle de lecture aujourd'hui de 10 h à 11 h.**
>
> 1. Elle ouvre **Réserver** ; la carte **25 Aujourd'hui** est sélectionnée.
> 2. Dans « Salle de lecture », le créneau « Matin 2 » (10:00-11:00) affiche « 21 libres / 24 ».
> 3. Elle clique sur **Réserver** : « Réservation confirmée ! » s'affiche et l'e-mail de confirmation arrive.
> 4. Elle ne peut finalement pas venir : dans **Mes réservations**, elle annule avant l'heure limite indiquée sur la ligne. La place repasse à « 22 libres ».

Si la réservation est refusée, un message en donne la raison : créneau complet, limite atteinte, ressource fermée, compte suspendu... Le chapitre [[n regles]] détaille chaque cas.

**Quand une journée s'ouvre-t-elle ?** Chaque ressource a une heure d'ouverture des réservations. Le jour le plus lointain que vous pouvez réserver s'ouvre à cette heure-là. Exemple : avec une limite d'un jour à l'avance et une ouverture à 17:00, les réservations pour demain ouvrent aujourd'hui à 17:00. Avant cette heure, le site affiche « Ouverture des réservations aujourd'hui à 17:00. »

## Mes réservations

**Mes réservations** liste toutes vos réservations, des plus récentes aux plus anciennes : date, ressource, créneau, date de la réservation. Les réservations passées sont marquées « Passée ».

Pour annuler, utilisez le bouton de la ligne. L'annulation est possible :

- avant le début du créneau ;
- et, si votre rôle a un délai d'annulation, jusqu'à ce délai avant le début. Exemple : avec 60 minutes, un créneau de 10:00 s'annule jusqu'à 9:00. L'heure limite est indiquée sur la ligne.

Une annulation envoie un e-mail. Si le personnel annule une de vos réservations, vous recevez aussi une notification sur le site, avec le motif s'il en a donné un.

## Notifications

La cloche de l'en-tête signale les notifications non lues : messages du personnel, réservations annulées par l'administration. La page **Notifications** permet de les marquer comme lues, une par une ou toutes à la fois, et de les supprimer.

## Mon profil

**Mon profil** regroupe :

- **Informations personnelles** : nom complet et adresse e-mail. Le changement de nom peut être désactivé par l'établissement.
- **Mot de passe** : saisissez le mot de passe actuel, puis le nouveau deux fois. Après un changement de mot de passe, les autres sessions ouvertes sont fermées.
- **Préférences de thème** : clair, sombre ou celui de l'appareil, et une couleur principale personnelle si vous le souhaitez. Le bouton soleil ou lune de l'en-tête change aussi le thème.
- **Double authentification** : voir plus bas.
- **Jetons d'API** : seulement si votre rôle a accès à l'API (chapitre [[n integrations]]).
- **Zone de danger** : suppression du compte, si l'établissement l'autorise. Votre mot de passe est demandé, et la suppression est définitive. Un e-mail la confirme. Un administrateur ne peut pas supprimer son propre compte.

## Double authentification

La double authentification ajoute une vérification après le mot de passe. Même si quelqu'un connaît votre mot de passe, il ne peut pas se connecter sans ce second élément. Elle est facultative, sauf pour les administrateurs si le site l'impose.

Quatre méthodes peuvent être activées dans **Mon profil**, seules ou ensemble :

| Méthode | Fonctionnement |
|---|---|
| Application d'authentification | Une application (Google Authenticator, Aegis, Authy...) affiche un code à [[mfa digits]] chiffres qui change toutes les [[mfa period]] secondes. Pour l'activer, scannez le QR code puis saisissez un code. |
| Code par e-mail | Un code est envoyé à votre adresse. Il est valable [[mfa otp_minutes]] minutes, et un nouvel envoi est possible au bout de [[mfa resend]] secondes. |
| Code par SMS | Un code est envoyé à votre téléphone. Cette méthode n'est proposée que si l'établissement a configuré un fournisseur SMS. |
| Clé physique | Clé de sécurité (YubiKey...) ou capteur de l'appareil (Touch ID, Windows Hello, Face ID). Demande un navigateur compatible. |

À la connexion, après le mot de passe, choisissez la méthode et saisissez le code (ou touchez la clé). Après **[[mfa max]] codes faux**, il faut attendre [[mfa decay]] minutes avant de réessayer.

**Codes de récupération.** À l'activation de l'application d'authentification, le site affiche [[mfa recovery]] codes de récupération. Chacun sert une seule fois, si vous n'avez plus accès à votre téléphone. Notez-les ou imprimez-les : ils ne seront plus affichés. Le profil permet d'en produire de nouveaux, ce qui annule les anciens.

**Perte de tous les moyens.** Sans téléphone, sans clé et sans code de récupération, demandez à un administrateur ou au personnel chargé des comptes : il peut désactiver votre double authentification depuis votre fiche.
