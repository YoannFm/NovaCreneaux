# Paramètres du site

Ce chapitre décrit les réglages faits depuis l'administration, menu **Paramètres** et pages voisines. Les valeurs sont stockées en base de données (table `settings`) et s'appliquent aussitôt. Les réglages du serveur (fichier `.env`, `config/nova.php`) sont décrits au chapitre [[n configuration-serveur]].

Les tableaux de ce chapitre sont produits à partir du code : clés enregistrées par l'administration, valeurs par défaut lues dans les sources, intitulés lus dans les formulaires. La documentation couvre [[settings_count]] réglages.

**Lire les tableaux.** « Réglage » est le nom de la clé en base. « Défaut » est la valeur d'un site neuf. Pour un réglage oui / non, `1` signifie oui et `0` non. « *vide* » signifie que rien n'est saisi : le site applique alors le comportement décrit dans la colonne « Rôle ».

## Réglages secrets

Ces réglages sont **chiffrés en base** avec la clé `APP_KEY` : [[secret_keys]].

- Ils ne sont jamais réaffichés. Le formulaire indique seulement s'ils sont renseignés.
- Laisser le champ vide conserve la valeur enregistrée. Pour effacer un secret, cochez la case d'effacement prévue.
- Le journal d'activité note qu'un secret a changé, jamais sa valeur.
- Si `APP_KEY` change, ils deviennent illisibles et sont traités comme vides : ressaisissez-les.

## Général

*Paramètres > Général.* Identité du site, vocabulaire et logo.

[[settings general]]

**Vocabulaire.** Les réglages `user_term`, `user_term_plural`, `user_term_feminine` et `group_label` adaptent les textes du site : « un élève », « l'élève », « les élèves », « Classe ». Exemple pour une médiathèque : « adhérent », « adhérents », masculin, « Section ». Un champ vide reprend la valeur par défaut, affichée en grisé dans le formulaire.

## Accueil

*Paramètres > Accueil.*

[[settings home]]

## Authentification

*Paramètres > Authentification.* Inscription, profil et mots de passe.

[[settings auth]]

## Sécurité

*Paramètres > Sécurité.* Vérification des adresses, double authentification, proxys et SMS.

[[settings security]]

**Proxys de confiance.** Si le site est servi derrière un proxy inverse (Nginx devant PHP, Cloudflare, répartiteur de charge), le site voit l'adresse du proxy au lieu de celle des visiteurs. Le journal et les limites de débit en sont faussés. La page **Sécurité** affiche l'adresse vue, celle annoncée par le proxy, et propose l'adresse à déclarer. Ne déclarez que des proxys que vous contrôlez.

**Fournisseurs SMS.** Les codes SMS de double authentification passent par l'un de ces fournisseurs : [[sms_drivers]]. `disabled` retire la méthode SMS des profils.

## E-mail

*Paramètres > E-mail.* Envoi des e-mails : confirmations, annulations, mots de passe, codes, avis de suppression.

[[settings mail]]

Après chaque modification, utilisez **E-mail de test** pour envoyer un message à une adresse de votre choix. En cas d'échec, l'erreur du serveur s'affiche.

Exemple pour une messagerie Gmail : hôte `smtp.gmail.com`, port `587`, chiffrement `tls`, identifiant = adresse complète, mot de passe = mot de passe d'application créé dans le compte Google.

## Maintenance

*Paramètres > Maintenance.*

[[settings maintenance]]

Pendant la maintenance, la connexion, la déconnexion, la double authentification, le mot de passe oublié et l'assistant d'installation restent accessibles. Le personnel et les administrateurs continuent d'utiliser tout le site. Une mise à jour active elle-même la maintenance pendant l'installation, puis remet l'état précédent.

## Réservations

*Paramètres > Réservations.* Les quatre limites de chaque rôle. Elles sont décrites au chapitre [[n regles]].

## Compte de l'établissement

*Paramètres > Compte de l'établissement.* Permet de se connecter avec le compte de l'établissement (ENT, Google Workspace, Microsoft 365...) au lieu du mot de passe du site. Deux protocoles sont pris en charge : **OpenID Connect** (OIDC) et **CAS**. La connexion par mot de passe reste toujours possible.

[[settings sso]]

**Mise en place.**

1. Choisissez la méthode, puis déclarez le site auprès du service de l'établissement avec l'**adresse de retour** affichée sur la page (`https://<votre-site>/login/etablissement/retour`).
2. Recopiez l'émetteur, l'identifiant et le secret fournis (OIDC), ou l'adresse du serveur (CAS). Enregistrez.
3. Un bouton « Se connecter avec le compte de l'établissement » (texte modifiable) apparaît sur la page de connexion.

**Comment le compte est retrouvé.** Le site reconnaît la personne à son **adresse e-mail** fournie par l'établissement. Un compte existant avec cette adresse se connecte ; un compte désactivé est refusé ; un compte qui a activé la double authentification doit encore saisir son code. Une adresse inconnue est refusée, avec le message « Aucun compte ne correspond à l'adresse ... », sauf si « Créer le compte d'une adresse inconnue » est coché : le compte est alors créé avec le rôle par défaut, si le domaine de l'adresse est autorisé (**Paramètres > Authentification**).

> **Exemple.** Un lycée utilise l'ENT de l'académie en CAS. L'administrateur choisit « CAS », saisit l'adresse du serveur CAS de l'ENT et garde l'attribut `mail`. Les élèves dont le compte a été importé avec leur adresse de l'ENT cliquent sur le bouton de l'établissement et entrent sans mot de passe propre au site.

## Configuration : export, import et modèles

*Paramètres > Configuration.* Un **fichier de configuration** (JSON) transporte le réglage d'un site vers un autre, ou sert de sauvegarde du paramétrage.

| Contenu du fichier | Jamais dans le fichier |
|---|---|
| Ressources et leurs créneaux, rôles non administrateurs (limites, permissions, accès à l'API), textes de l'établissement (vocabulaire, qui contacter...), couleurs | Mots de passe et secrets, comptes, réservations, adresse du site, proxys, réglages de sécurité |

- **Exporter** télécharge la configuration actuelle.
- **Importer** applique un fichier. L'import **fusionne** : un rôle est retrouvé par son nom interne, une ressource par son nom, un créneau par ses horaires dans sa ressource. Ce qui existe est mis à jour, ce qui manque est créé, **rien n'est supprimé** : aucune réservation ne peut être perdue. Un rôle administrateur n'est jamais modifié. En cas d'erreur, rien n'est appliqué.
- **Modèles prêts à importer** : configurations de départ, à ajuster ensuite.

Ces actions demandent votre mot de passe administrateur.

[[config_templates]]

> **Exemple.** Un établissement ouvre un deuxième site pour son annexe. Sur le premier site : **Exporter**. Sur le nouveau : **Importer** ce fichier. Les ressources, créneaux, rôles et textes sont repris ; il reste à créer les comptes et à saisir les réglages propres au site (e-mail, sécurité).

## Intégrations API et sauvegardes

Ces réglages ont leur propre page (chapitres [[n exploitation-site]] et [[n integrations]]) :

[[settings api]]

[[settings backup]]

## Couleurs du site

*Paramètres > Apparence.* Voir le chapitre [[n guide-personnel]], section Apparence.
