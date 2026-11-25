# Synthèse

Le CDI du lycée Louis Rascol utilise NovaCreneaux [[version]], application web libre (licence MIT) de réservation de créneaux, hébergée sur PHP 8.3 et MySQL ou MariaDB.

| Public | Ce qu'il faut retenir | Chapitres |
|---|---|---|
| Élèves | Réserver la veille à partir de 17 h, le jour même jusqu'à 11 h 30, jamais le mercredi, le samedi et le dimanche. Annuler dès qu'on ne vient pas. | [[n guide-eleve]], [[n regles]], [[n faq-eleves]] |
| Documentalistes | Comptes Administrateur. La ressource CDI et ses créneaux fixent les horaires ; Paramètres > Réservations fixe les limites des élèves. Le planning permet de réserver ou d'annuler pour un élève sans contrainte d'horaire. | [[n prise-en-main]] à [[n faq-documentalistes]] |
| Sécurité | Un compte par documentaliste, double authentification activée, journal d'activité de toutes les modifications. | [[n prise-en-main]], [[n parametres]] |
| Entretien | Mise à jour signée depuis l'administration, sauvegarde automatique de la base, purge du journal à 90 jours, procédure de fin d'année. | [[n exploitation-site]] |
| Technique | Racine du site sur `public/`, tâche cron chaque minute, copie de `.env` et de `APP_KEY`, restauration de la base en ligne de commande. | [[n installation]], [[n configuration-serveur]], [[n exploitation-serveur]] |

**Points d'attention relevés dans la version [[version]] :**

- l'horaire du lundi s'ouvre le dimanche à 17 h (avec une réservation à l'avance d'un jour) : à expliquer aux élèves ;
- la suppression d'un créneau ou de la ressource annule les réservations à venir : désactiver plutôt que supprimer ;
- la base de données ne se restaure qu'en ligne de commande ;
- la clé publique des extensions est vide : aucune extension n'est installable dans cette version.
