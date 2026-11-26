# Guide du personnel

Ce chapitre décrit l'administration, section par section. Un membre du personnel ne voit que les sections de ses permissions (chapitre [[n droits-personnel]]). L'administrateur les voit toutes.

## Entrer dans l'administration

Le lien « Panel administrateur » de l'accueil, ou l'adresse `/admin`, ouvre le tableau de bord. Il affiche des compteurs (comptes, ressources, créneaux, réservations du jour et total) et les dernières réservations. Le menu de gauche ne montre que les sections permises. Un compte sans permission est renvoyé vers l'accueil.

[[fig tableau-de-bord]]

## Ressources et créneaux

*Permission : Ressources et créneaux.*

**Créer une ressource.** Dans **Ressources**, choisissez « Nouvelle ressource » et renseignez :

| Champ | Sens |
|---|---|
| Nom | 100 caractères au plus (ex. « CDI - salle de travail »). |
| Description | Texte libre, 500 caractères au plus. |
| Ouverture d'un nouveau jour | Heure à laquelle s'ouvre chaque jour la journée la plus lointaine réservable (chapitre [[n regles]]). |
| Heure fermeture (jour J) | Heure à laquelle les réservations du jour même ferment. |
| Jours fermés | Jours de la semaine sans réservation (ex. samedi, dimanche). |
| Ressource active | Une ressource inactive disparaît de la page de réservation, sans rien annuler. |

**Ajouter des créneaux.** Depuis la ressource, ouvrez ses créneaux et ajoutez-en autant que nécessaire :

| Champ | Sens |
|---|---|
| Nom | 50 caractères au plus (ex. « M1 », « Midi »). |
| Heure début, Heure fin | Heures au format HH:MM ; la fin doit suivre le début. |
| Capacité maximale | Nombre de places, de 1 à 500. |
| Créneau actif | Un créneau inactif n'est plus réservable. |

**Supprimer.** La suppression d'un créneau ou d'une ressource annule les réservations à venir et prévient les personnes concernées. Désactivez plutôt pour une fermeture temporaire.

## Planning du jour

*Permission : Réservations de tous les comptes.*

**Réservations** affiche le planning d'une date : pour chaque ressource et chaque créneau, la liste des personnes inscrites. Un filtre limite l'affichage à une ressource.

[[fig planning]]

> **Exemple.** À 10 h 05, Inès Robert (Tle G2) arrive aux postes informatiques sans réservation. La surveillante ouvre **Réservations**, voit « 3 / 12 » sur le créneau Récréation 10:00-10:15, clique sur le **+** de ce créneau, tape « Robert » et valide. Le créneau a commencé, mais il n'est pas terminé : la réservation est acceptée, Inès reçoit l'e-mail et apparaît dans la liste.

- **Réserver pour une personne** : bouton **Ajouter** (ou **+** sur la ligne d'un créneau), cherchez le compte par son nom, son e-mail ou sa classe, choisissez le créneau, validez. Les limites du rôle et la fenêtre d'ouverture ne s'appliquent pas (chapitre [[n droits-personnel]]).
- **Supprimer la réservation** (icône corbeille) : un motif facultatif est transmis à la personne par e-mail et par notification.
- **Exporter** le planning de la date : en **CSV** (`reservations_AAAA-MM-JJ.csv`, séparateur point-virgule, colonnes Nom, Email, Classe, Ressource, Créneau, Date, Réservé le) ou en **PDF** (`reservations_AAAA-MM-JJ.pdf`), à imprimer pour l'accueil.

  Le bouton **PDF** propose trois mises en page : **Une seule liste** (tous les inscrits, classés par créneau puis par nom), **Un bloc par créneau**, ou **Une page par créneau** : une feuille d'appel par créneau, avec une case de présence à cocher à la main.

## Comptes

*Permission : Utilisateurs (comptes non administrateurs).*

**Utilisateurs** liste les comptes, avec une recherche par nom ou e-mail.

**Créer un compte** : nom, e-mail, classe, rôle, mot de passe et état actif. Un compte créé ici a son adresse considérée comme vérifiée. Si le mot de passe est laissé vide, un mot de passe aléatoire est choisi : la personne utilisera « Mot de passe oublié ».

**Fiche d'un compte** : modifier les informations et le rôle ; activer ou désactiver le compte ; marquer l'adresse e-mail comme vérifiée ; désactiver la double authentification de la personne (perte de téléphone) ; donner un avertissement ou suspendre ; supprimer.

**Importer des comptes** (bouton **Import CSV**). Le fichier a quatre colonnes, dans cet ordre : nom, prénom, classe, e-mail. Une première ligne commençant par « nom » est ignorée.

```
nom,prenom,classe,email
Martin,Léa,2nde B,lea.martin@lycee.fr
Durand,Hugo,1re A,hugo.durand@lycee.fr
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

**Limite du personnel.** Un membre du personnel ne gère que les comptes ordinaires : il ne peut ni modifier, ni sanctionner, ni supprimer un administrateur ou un autre membre du personnel, ni attribuer un rôle qui donne accès à l'administration.

## Avertissements et suspensions

*Permission : Sanctions.*

Depuis la liste des comptes ou une fiche, deux sanctions sont possibles :

- **Avertissement** : remarque consignée avec sa raison. Elle n'empêche pas de réserver.
- **Suspension** : interdit de réserver jusqu'à une date ou définitivement. Elle demande une raison (1000 caractères au plus) et une gravité :

[[sanctions]]

Un compte ne peut avoir qu'une suspension en cours. La raison est montrée à la personne quand elle tente de réserver. Personne ne peut se sanctionner soi-même.

> **Exemple.** Un élève réserve tous les jours sans venir. Premier temps : **avertissement**, raison « Absences répétées sans annulation ». Deuxième temps : **suspension** jusqu'au vendredi suivant, gravité « Moyenne ». Quand il tente de réserver, il lit le motif et la date de fin, suivis de « Pour toute question, contactez ... » (réglage « Qui contacter »).

La page **Sanctions** liste l'historique : qui, quand, par qui, pourquoi. Lever une suspension ou retirer un avertissement se fait depuis cette liste.

## Notifications

*Permission : Notifications aux utilisateurs.*

**Envoyer une notification** : titre, contenu (5000 caractères au plus), niveau (info, succès, attention, danger) et destinataires : tous les comptes, certains comptes ou tous les comptes de certains rôles. La notification s'affiche sous la cloche de chaque destinataire.

## Pages du site

*Permission : Pages du site.*

Une page est un contenu libre (règlement intérieur, horaires, aide), publié à l'adresse `/p/<adresse>`.

| Champ | Sens |
|---|---|
| Titre, description | Titre affiché et résumé. |
| Adresse (slug) | Partie finale de l'adresse, transformée en minuscules et tirets. |
| Contenu | Éditeur de texte. Le HTML est nettoyé : les scripts et attributs dangereux sont retirés. |
| Publiée | Une page non publiée répond « introuvable ». |
| Accès restreint | Réserve la page aux comptes connectés et, si des rôles sont cochés, à ces rôles seulement. |

## Barre de navigation

*Permission : Barre de navigation du site.*

Chaque lien a un libellé, un type, une icône (nom Bootstrap Icons, ex. `bi-house`), une cible (même onglet ou nouvel onglet), un état actif, et peut être réservé à l'administration. L'ordre se change par glisser-déposer.

[[nav_types]]

## Apparence

*Permission : Apparence (couleurs).*

**Paramètres > Apparence** règle les couleurs du site, pour le thème sombre et pour le thème clair. Les couleurs sont au format `#RRGGBB`.

[[theme]]

Chaque personne peut en plus choisir sa propre couleur principale dans son profil.

## Journal d'activité

*Permission : Journal d'activité (lecture).*

**Activité** (menu Système) enregistre les actions importantes : connexions et déconnexions, créations, modifications et suppressions (ressources, créneaux, comptes, rôles, réglages), réservations, sanctions, exports, appels d'API refusés, webhooks abandonnés, mises à jour, sauvegardes.

Chaque ligne indique qui, quand, depuis quelle adresse IP, et pour une modification les valeurs **avant et après**. Les réglages secrets n'apparaissent jamais en clair : seul le fait qu'ils ont changé est noté.

Filtres : nom de la personne, période (du ... au ...), type d'action. 50 lignes par page.

[[fig journal]]

Les lignes de plus de **90 jours** sont supprimées chaque jour par la tâche planifiée. L'administrateur peut aussi lancer cette purge depuis la page.
