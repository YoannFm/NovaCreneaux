# Guide des documentalistes

Ce chapitre décrit le travail courant dans l'administration : planning du jour, comptes des élèves, sanctions, communication et journal d'activité. La configuration du CDI est au chapitre [[n configurer-cdi]].

## Planning du jour

**Réservations** affiche le planning d'une date : pour chaque créneau du CDI, la liste des élèves inscrits.

[[fig rascol-planning]]

- **Réserver pour un élève** : bouton **Ajouter** (ou **+** sur la ligne d'un créneau), cherchez le compte par son nom, son e-mail ou sa classe, choisissez le créneau, validez. L'élève reçoit l'e-mail de confirmation.
- **Supprimer la réservation** (icône corbeille) : un motif facultatif (« Absent », « CDI fermé pour réunion ») est transmis à l'élève par e-mail et par notification. Aucun délai ne s'applique.
- **Exporter** le planning de la date : en **CSV** (`reservations_AAAA-MM-JJ.csv`, séparateur point-virgule, colonnes Nom, Email, Classe, Ressource, Créneau, Date, Réservé le) ou en **PDF** (`reservations_AAAA-MM-JJ.pdf`), à imprimer pour l'accueil du CDI.

**Ce qui s'applique quand un documentaliste réserve pour un élève.** Seuls ces contrôles restent :

- créneau et CDI actifs ;
- date non passée ;
- pour le jour même, créneau non terminé (il peut avoir commencé) ;
- l'élève n'est pas déjà inscrit sur ce créneau ;
- une place libre.

Les horaires d'ouverture (17 h la veille, 11 h 30 le jour même), les jours fermés, les limites du rôle de l'élève et sa suspension **ne s'appliquent pas**. Un documentaliste peut donc inscrire un élève à 12 h 30 pour le créneau de 12 h 50, ou un mercredi si besoin.

> **Exemple.** Jeudi à 12 h 05, Hugo Durand (1re 5) arrive au CDI sans réservation ; il est trop tard pour qu'il réserve lui-même (après 11 h 30). Le documentaliste ouvre **Réservations**, vérifie que le créneau « 11h55 - 12h50 » affiche « 7 / 40 », clique sur le **+** de ce créneau, tape « Durand », choisit Hugo et valide. Le créneau a commencé mais n'est pas terminé : la réservation est acceptée. Hugo reçoit l'e-mail de confirmation.

## Comptes des élèves

**Utilisateurs** liste les comptes, avec une recherche par nom ou e-mail. L'inscription libre étant désactivée au lycée, c'est ici que se créent tous les comptes, avec l'adresse de l'élève au format `prenom.nom@e.rascol.net`. En début d'année, la méthode la plus rapide est l'import CSV ci-dessous.

**Créer un compte** : nom, e-mail, classe, rôle, mot de passe et état actif. Un compte créé ici a son adresse considérée comme vérifiée. Si le mot de passe est laissé vide, un mot de passe aléatoire est choisi : la personne utilisera « Mot de passe oublié ».

**Fiche d'un compte** : modifier les informations et le rôle ; activer ou désactiver le compte ; marquer l'adresse e-mail comme vérifiée ; désactiver la double authentification de la personne (perte de téléphone) ; donner un avertissement ou suspendre ; supprimer.

**Importer des comptes** (bouton **Import CSV**). Le fichier a quatre colonnes, dans cet ordre : nom, prénom, classe, e-mail. Une première ligne commençant par « nom » est ignorée.

```
nom,prenom,classe,email
Martin,Léa,2nde 3,lea.martin@e.rascol.net
Durand,Hugo,1re 5,hugo.durand@e.rascol.net
```

- Le fichier fait 2 Mo au plus.
- Une ligne sans adresse valide, ou dont l'adresse existe déjà, est ignorée. Le bilan affiche le nombre de comptes créés et ignorés.
- Les comptes importés reçoivent le rôle par défaut des utilisateurs et un mot de passe aléatoire. Envoyez-leur ensuite un lien de réinitialisation avec l'action groupée ci-dessous.

**Actions groupées.** Cochez des comptes dans la liste (500 au plus) puis choisissez :

| Action | Effet |
|---|---|
| `activate` | Active les comptes. |
| `deactivate` | Désactive les comptes : ils ne peuvent plus se connecter. |
| `reset_password` | Envoie à chacun un lien de réinitialisation du mot de passe. |
| `export` | Télécharge les comptes sélectionnés en CSV. |
| `delete` | Supprime les comptes. |

Votre propre compte n'est jamais touché par une action groupée.

**Exporter tous les comptes** (bouton **Exporter ZIP**) : archive ZIP contenant `utilisateurs.csv` (Nom, Email, Classe, Rôle, Actif, Réservations, Inscrit le).

## Avertissements et suspensions

Depuis la liste des comptes ou une fiche, deux sanctions sont possibles :

- **Avertissement** : remarque consignée avec sa raison. Elle n'empêche pas de réserver.
- **Suspension** : interdit de réserver jusqu'à une date ou définitivement. Elle demande une raison (1000 caractères au plus) et une gravité :

[[sanctions]]

Un compte ne peut avoir qu'une suspension en cours. La raison est montrée à la personne quand elle tente de réserver. Personne ne peut se sanctionner soi-même.

> **Exemple.** Un élève a été absent sans prévenir à trois réservations en deux semaines. Le documentaliste lui donne d'abord un **avertissement** (raison : « 3 absences sans annulation »). À la récidive, il le **suspend** jusqu'au vendredi suivant, gravité « Moyenne ». Quand l'élève tente de réserver, il lit : « Votre accès aux réservations est suspendu jusqu'au ... Motif : 3 absences sans annulation. Pour toute question, contactez les documentalistes du CDI. »

La page **Sanctions** liste l'historique : qui, quand, par qui, pourquoi. Lever une suspension ou retirer un avertissement se fait depuis cette liste.

## Notifications

**Envoyer une notification** : titre, contenu (5000 caractères au plus), niveau (info, succès, attention, danger) et destinataires : tous les comptes, certains comptes ou tous les comptes de certains rôles. La notification s'affiche sous la cloche de chaque destinataire.

## Pages du site

Une page est un contenu libre, publié à l'adresse `/p/<adresse>` : règlement du CDI, horaires, aide à la réservation.

| Champ | Sens |
|---|---|
| Titre, description | Titre affiché et résumé. |
| Adresse (slug) | Partie finale de l'adresse, transformée en minuscules et tirets. |
| Contenu | Éditeur de texte. Le HTML est nettoyé : les scripts et attributs dangereux sont retirés. |
| Publiée | Une page non publiée répond « introuvable ». |
| Accès restreint | Réserve la page aux comptes connectés et, si des rôles sont cochés, à ces rôles seulement. |

## Barre de navigation

Chaque lien a un libellé, un type, une icône (nom Bootstrap Icons, ex. `bi-house`), une cible (même onglet ou nouvel onglet), un état actif, et peut être réservé à l'administration. L'ordre se change par glisser-déposer.

[[nav_types]]

## Apparence

**Paramètres > Apparence** règle les couleurs du site, pour le thème sombre et pour le thème clair. Les couleurs sont au format `#RRGGBB`.

[[theme]]

Chaque personne peut en plus choisir sa propre couleur principale dans son profil.

## Journal d'activité

**Activité** (menu Système) enregistre les actions importantes : connexions et déconnexions, créations, modifications et suppressions (ressources, créneaux, comptes, rôles, réglages), réservations, sanctions, exports, appels d'API refusés, webhooks abandonnés, mises à jour, sauvegardes.

Chaque ligne indique qui, quand, depuis quelle adresse IP, et pour une modification les valeurs **avant et après**. Les réglages secrets n'apparaissent jamais en clair : seul le fait qu'ils ont changé est noté.

Filtres : nom de la personne, période (du ... au ...), type d'action. 50 lignes par page.

[[fig rascol-journal]]

Les lignes de plus de **90 jours** sont supprimées chaque jour par la tâche planifiée. L'administrateur peut aussi lancer cette purge depuis la page.
