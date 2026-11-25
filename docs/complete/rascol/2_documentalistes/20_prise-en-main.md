# Prise en main de l'administration

Ce chapitre s'adresse aux documentalistes. Les comptes des documentalistes ont le rôle **Administrateur** : ils ouvrent toute l'administration du site.

## Entrer dans l'administration

Connectez-vous avec votre compte de documentaliste, puis cliquez sur « Panel administrateur » sur la page d'accueil (ou ouvrez l'adresse `/admin`). Le tableau de bord affiche des compteurs (comptes, ressources, créneaux, réservations du jour et total) et les dernières réservations : élève, classe, ressource, créneau, date et heure de la réservation.

[[fig rascol-tableau-de-bord]]

## Le menu

Le menu de gauche regroupe toutes les pages de l'administration. Liste lue dans le code du logiciel :

[[admin_menu]]

| Entrée | À quoi elle sert au CDI | Chapitre |
|---|---|---|
| Ressources | Le CDI, ses horaires et ses créneaux | [[n configurer-cdi]] |
| Réservations | Le planning du jour, réserver ou annuler pour un élève, export CSV et PDF | [[n guide-documentaliste]] |
| Utilisateurs, Sanctions | Les comptes des élèves, l'import en début d'année, les avertissements et suspensions | [[n guide-documentaliste]] |
| Notifications, Pages, Navigation | Informer les élèves, publier le règlement du CDI | [[n guide-documentaliste]] |
| Activité | Le journal de tout ce qui se fait sur le site | [[n guide-documentaliste]] |
| Paramètres | Nom du site, e-mails, sécurité, limites de réservation des élèves | [[n parametres]] |
| Rôles | Les rôles Administrateur et Utilisateur, et d'éventuels rôles intermédiaires | [[n roles]] |
| Mise à jour | Mises à jour du logiciel et sauvegardes de la base | [[n exploitation-site]] |
| Intégrations API, Webhooks, Extensions | Liens avec d'autres logiciels : usage technique | [[n integrations]], [[n extensions]] |

En haut de la page, deux boutons ouvrent des ressources externes : **Support** (serveur Discord d'assistance de NovaCreneaux) et **Documentation** (documentation générale du logiciel).

## Bonnes pratiques pour les comptes des documentalistes

- **Un compte par documentaliste**, jamais de compte partagé : le journal d'activité indique alors qui a fait quoi.
- **Activez la double authentification** sur votre compte (chapitre [[n guide-eleve]], section « Double authentification »). Un compte de documentaliste ouvre les données de tous les élèves. Pour l'imposer à tous les administrateurs : **Paramètres > Sécurité**, « Double authentification obligatoire pour les administrateurs ».
- **Déconnectez-vous** sur les postes partagés du CDI.
- Un administrateur ne peut pas supprimer son propre compte : un autre documentaliste doit le faire.

## Ce que les documentalistes peuvent faire en plus des élèves

- Réserver pour un élève, même en dehors des horaires d'ouverture des réservations, et annuler n'importe quelle réservation (chapitre [[n guide-documentaliste]]).
- Utiliser le site pendant le mode maintenance, qui bloque les élèves.
- Modifier tous les comptes, y compris ceux des autres documentalistes.
