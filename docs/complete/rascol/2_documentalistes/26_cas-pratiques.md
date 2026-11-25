# Cas pratiques au CDI

Ces fiches suivent les situations courantes au CDI, pas à pas. Elles peuvent servir d'exercices de formation.

## Préparer la journée

1. Ouvrez **Réservations** : le planning du jour s'affiche, créneau par créneau.
2. Cliquez sur **PDF** pour imprimer la liste des élèves attendus, à garder à l'accueil. **CSV** donne la même liste pour un tableur.
3. Pour préparer le lendemain, changez la date. Après 17 h, la liste du lendemain se remplit.

## Accueillir un élève sans réservation

1. Dans **Réservations**, vérifiez qu'il reste une place sur le créneau en cours.
2. Cherchez l'élève par son nom ou sa classe, choisissez le créneau et validez.

Cela fonctionne même après 11 h 30 et même si le créneau a commencé : seules la place libre et le créneau non terminé comptent.

## Libérer la place d'un absent

1. Dans **Réservations**, repérez la ligne de l'élève.
2. **Supprimer la réservation**, avec le motif « Absent au début du créneau ».
3. L'élève est prévenu par e-mail et par une notification. La place redevient disponible aussitôt.

## Fermer le CDI une journée (réunion, inventaire, examen)

1. Dans **Réservations**, choisissez la date et supprimez chaque réservation avec le motif « CDI fermé ce jour-là ».
2. Prévenez tous les élèves avec **Notifications** (destinataires : tous les comptes).
3. Pour éviter de nouvelles réservations ce jour-là, désactivez la ressource CDI la veille avant 17 h, puis réactivez-la le lendemain.

Ne supprimez jamais la ressource ni ses créneaux : la suppression annule **toutes** les réservations à venir.

## Fermer le CDI pendant les vacances

Désactivez la ressource CDI (**Ressources**, « Ressource active ») au début des vacances : elle disparaît de la page de réservation. Réactivez-la la veille de la rentrée, avant 17 h, pour que les élèves puissent réserver le premier jour.

## Changer un horaire de réservation

Exemple : ouvrir les réservations du lendemain à 16 h au lieu de 17 h, pour que les élèves puissent réserver avant de quitter le lycée.

1. **Ressources**, crayon de la ligne CDI.
2. « Ouverture d'un nouveau jour » : 16:00. **Enregistrer**.
3. Mettez à jour le message d'accueil (**Paramètres > Accueil**) s'il cite l'heure.

Le changement s'applique tout de suite ; aucune réservation n'est touchée.

## Suspendre un élève après un incident

1. **Utilisateurs**, fiche de l'élève, suspension.
2. Choisissez la gravité, la date de fin (ou « Définitive ») et rédigez la raison : elle sera montrée à l'élève quand il tentera de réserver.
3. La suspension empêche de nouvelles réservations, mais n'annule pas celles déjà prises : supprimez-les depuis le planning si nécessaire.
4. Pour lever la suspension avant son terme : **Sanctions**.

Pour une simple remarque sans conséquence, donnez plutôt un **avertissement** : il est conservé dans l'historique de l'élève.

## Rentrée : créer les comptes des élèves

1. Demandez au secrétariat ou à la vie scolaire la liste des élèves, puis préparez un fichier CSV : nom, prénom, classe, e-mail (`prenom.nom@e.rascol.net`).
2. **Utilisateurs**, bouton **Import CSV**, choisissez le fichier. Le bilan indique les comptes créés et les lignes ignorées (adresse invalide ou déjà connue).
3. Cochez les nouveaux comptes dans la liste, puis lancez l'action groupée d'envoi d'un lien de réinitialisation du mot de passe.
4. Chaque élève ouvre le lien reçu et choisit son mot de passe. Expliquez-leur le guide de l'élève (partie 1).

## Fin d'année : supprimer les comptes des élèves sortants

La procédure complète, avec la sauvegarde préalable, est au chapitre [[n exploitation-site]], section « Fin d'année scolaire ». Pensez à décocher l'e-mail d'avertissement de suppression avant de supprimer les comptes en masse.

## Retrouver qui a modifié un réglage

1. **Activité**, filtrez par période ou par type d'action (« Paramètres modifiés », « Ressource modifiée »...).
2. Ouvrez la ligne : le détail montre l'auteur, l'heure, l'adresse IP et les valeurs avant et après.

[[fig rascol-journal]]
