# Questions fréquentes des documentalistes

**Un élève dit qu'il ne peut pas réserver pour demain.**
Avant 17 h, c'est normal : la journée de demain s'ouvre à 17 h. Le vendredi, le lundi ne s'ouvre que le dimanche à 17 h. Pour l'aider, réservez à sa place depuis **Réservations** : les horaires d'ouverture ne s'appliquent pas aux documentalistes.

**Les élèves ne peuvent pas réserver le jour même pour le second créneau.**
C'est l'effet de « Heure fermeture (jour J) » à 11:30 : après 11 h 30, plus aucune réservation pour le jour même, y compris pour 12 h 50. Pour l'autoriser, passez cette heure à 12:40 dans la fiche de la ressource CDI (chapitre [[n configurer-cdi]]).

**Un élève n'a pas de compte.**
L'inscription libre est désactivée : créez son compte dans **Utilisateurs** avec son adresse `prenom.nom@e.rascol.net` (mot de passe laissé vide : un mot de passe aléatoire est choisi), puis envoyez-lui un lien de réinitialisation. Il choisira son mot de passe en ouvrant le lien.

**Un élève n'a jamais reçu son mot de passe.**
Les comptes importés ont un mot de passe aléatoire. Cochez son compte dans **Utilisateurs** et lancez l'action groupée d'envoi d'un lien de réinitialisation, ou dites-lui d'utiliser « Mot de passe oublié ? ».

**Les élèves ne reçoivent aucun e-mail.**
Vérifiez **Paramètres > E-mail** : la méthode d'envoi doit être `smtp` ou `sendmail`, puis utilisez **E-mail de test**. Le message d'erreur affiché indique la cause (identifiant, port, chiffrement). Les réservations restent enregistrées même sans e-mail.

**Un élève a perdu son téléphone et ne peut plus se connecter.**
Ouvrez sa fiche dans **Utilisateurs** et désactivez sa double authentification. Il se connectera avec son seul mot de passe.

**Un élève a deux comptes.**
Gardez celui qui porte ses réservations et supprimez l'autre. Depuis la version 1.6.9, une même adresse écrite avec ou sans majuscules désigne un seul compte.

**Un élève suspendu peut-il venir à ses réservations déjà prises ?**
Oui : la suspension bloque les nouvelles réservations, sans annuler les anciennes. Supprimez-les depuis le planning si nécessaire.

**J'ai supprimé un créneau par erreur.**
Les réservations à venir de ce créneau ont été annulées : leur nombre s'est affiché après la suppression, et chaque élève concerné a reçu un e-mail et une notification avec le motif. **Activité** note la suppression du créneau, mais pas la liste des élèves. Recréez le créneau, puis prévenez les élèves (**Notifications**) pour qu'ils réservent à nouveau.

**Le menu affiche « Une nouvelle version est disponible ».**
Une mise à jour du logiciel est publiée. Suivez la procédure du chapitre [[n exploitation-site]], de préférence en dehors des heures d'ouverture du CDI.

**Le site affiche le message de maintenance aux élèves.**
Le mode maintenance est actif (**Paramètres > Maintenance**), parfois laissé par une mise à jour interrompue. Terminez la mise à jour dans **Mise à jour**, ou désactivez la maintenance.

**Qui a modifié les horaires du CDI ?**
**Activité**, type d'action « Ressource modifiée » : le détail montre l'auteur, l'heure et les valeurs avant et après.

**Un problème technique persiste (site inaccessible, erreur).**
Notez l'heure et le message, puis contactez la personne qui héberge le site. La partie 3 de ce document lui est destinée.
