# Rôles et permissions

## Principe

Chaque compte a un **rôle**. Le rôle fixe deux choses :

- les **limites de réservation** de ses comptes (chapitre [[n regles]]) ;
- les **permissions** dans l'administration, c'est-à-dire les sections que ses comptes peuvent ouvrir.

Trois cas se présentent :

| Rôle | Accès |
|---|---|
| Sans permission (ex. Utilisateur, Élève) | Le site seulement : réserver, profil, notifications. |
| Avec au moins une permission (le **personnel**) | Le site, plus les sections de l'administration correspondant à ses permissions. |
| Administrateur | Tout, y compris les réglages réservés (voir plus bas). |

## Rôles d'origine

L'installation crée deux rôles **système**. Ils ne peuvent pas être supprimés, et leur statut d'administrateur ne peut pas être modifié :

[[default_roles]]

Le rôle `user` est le **rôle par défaut** : il est donné aux comptes importés ou inscrits, et ses limites servent de modèle aux nouveaux rôles.

## Créer et ordonner les rôles

*Réservé aux administrateurs : **Rôles**.*

Un rôle a :

| Champ | Sens |
|---|---|
| Nom interne | Lettres minuscules, chiffres et `_` (ex. `surveillant`), unique. |
| Libellé | Nom affiché (ex. « Surveillant »). |
| Icône, couleur | Icône Bootstrap Icons et couleur `#RRGGBB` du badge. |
| Administrateur | Donne toutes les permissions et l'accès aux réglages. |
| Accès à l'API | Autorise les comptes de ce rôle à créer des jetons d'API (chapitre [[n integrations]]). Coché par défaut. |
| Permissions | Cases à cocher, voir le tableau ci-dessous. |

Un nouveau rôle reprend les limites de réservation du rôle `user`, à ajuster ensuite dans **Paramètres > Réservations**. La liste des rôles s'ordonne par glisser-déposer. Un rôle ne peut être supprimé que s'il n'est plus attribué à aucun compte.

> **Exemple : un rôle « Délégué » pour les élèves élus.** Créez le rôle `delegue`, libellé « Délégué », sans permission (ce sont des élèves). Dans **Paramètres > Réservations**, donnez-lui 3 réservations par jour au lieu de 2. Attribuez-le aux délégués depuis leur fiche. Ils réservent comme les autres élèves, avec une limite plus large, sans entrer dans l'administration.
>
> **Exemple : un rôle « Surveillant ».** Rôle `surveillant` avec les seules permissions « Réservations de tous les comptes » et « Journal d'activité (lecture) ». Le surveillant voit le planning, réserve et annule pour les élèves, consulte le journal ; il ne voit ni les comptes, ni les réglages.

## Permissions délégables

Les permissions se donnent rôle par rôle. Ce tableau est produit à partir de `app/Support/Permission.php` et des routes du site :

[[permissions]]

Les extensions installées peuvent ajouter leurs propres permissions, sous la forme `identifiant-extension.cle`. Elles apparaissent dans un groupe à part du formulaire. Une permission d'extension cesse d'agir quand l'extension est désactivée, mais reste enregistrée dans le rôle et revient avec elle.

## Ce qui reste réservé aux administrateurs

Ces sections ne se délèguent pas. Celui qui les tiendrait pourrait s'attribuer tous les droits ou lire les secrets du site :

[[admin_only]]

Cela couvre les paramètres du site, les rôles, les jetons d'API, les webhooks, les mises à jour, les sauvegardes, les extensions et la purge manuelle du journal.

## Règles de sécurité entre comptes

- Un membre du personnel ne gère que les comptes ordinaires. Il ne peut ni modifier, ni sanctionner, ni supprimer un administrateur ou un autre membre du personnel.
- Il ne peut attribuer que des rôles sans accès à l'administration.
- Le mode maintenance laisse entrer le personnel et les administrateurs.
- Si le réglage « Double authentification obligatoire pour les administrateurs » est actif, tout compte qui a accès à l'administration (administrateur **et personnel**) est renvoyé vers son profil tant qu'il n'a pas activé une méthode de double authentification.
