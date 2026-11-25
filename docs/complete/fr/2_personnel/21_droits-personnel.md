# Vos droits et les règles du personnel

Ce chapitre précise ce qu'un membre du personnel peut faire, et ce qui change dans les règles de réservation quand il agit pour le compte d'une autre personne.

## Ce que vos permissions ouvrent

Votre rôle vous donne une ou plusieurs permissions. Chacune ouvre une section de l'administration. Les autres sections ne s'affichent pas : si vous ouvrez leur adresse, le site répond « Vous n'avez pas accès à cette section. »

[[permissions]]

Pour obtenir une permission de plus, adressez-vous à un administrateur : lui seul modifie les rôles.

## Ce que le personnel ne peut pas faire

- Gérer un administrateur ou un autre membre du personnel : vous ne pouvez ni modifier, ni sanctionner, ni supprimer ces comptes.
- Attribuer un rôle qui donne accès à l'administration.
- Vous sanctionner vous-même, ou vous inclure dans une action groupée.
- Ouvrir les sections réservées aux administrateurs : paramètres du site, rôles, jetons d'API, webhooks, mises à jour, sauvegardes, extensions.

## Réservation et annulation par le personnel

Le personnel qui a la permission « Réservations de tous les comptes » peut réserver pour le compte d'une personne, par exemple un élève qui n'a pas accès au sien (voir « Planning du jour », chapitre [[n guide-personnel]]). Dans ce cas, **seuls ces contrôles s'appliquent** :

- créneau et ressource actifs ;
- date non passée ;
- pour le jour même, créneau non terminé (il peut avoir commencé) ;
- pas de doublon ;
- une place libre.

Les limites du rôle, la fenêtre d'ouverture, les jours de fermeture et la suspension **ne s'appliquent pas**.

Le personnel peut aussi annuler n'importe quelle réservation, sans condition de délai, avec un motif facultatif. La personne est prévenue par e-mail et par une notification sur le site.

## Suppression d'une ressource ou d'un créneau

Supprimer un créneau, ou une ressource avec tous ses créneaux, **annule les réservations à venir** de ces créneaux, avec un motif automatique (« Le créneau ... n'existe plus. »). Les personnes concernées sont prévenues. Les réservations passées restent dans l'historique.

Pour fermer temporairement une ressource sans rien annuler, désactivez-la plutôt que de la supprimer.

## Maintenance et double authentification

- Pendant le mode maintenance, le personnel continue d'utiliser tout le site. Les autres comptes voient le message de maintenance.
- Si l'administrateur impose la double authentification (réglage « Double authentification obligatoire pour les administrateurs »), l'obligation s'applique aussi au personnel. Tant qu'aucune méthode n'est activée, le site vous renvoie vers votre profil pour en activer une.
- Vos actions dans l'administration sont inscrites au journal d'activité, avec votre nom, l'heure et votre adresse IP.
