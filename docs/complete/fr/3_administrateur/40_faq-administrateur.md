# Dépannage et questions fréquentes

**Les e-mails ne partent pas.**
1. **Paramètres > E-mail** : la méthode d'envoi doit être `smtp` ou `sendmail`. Sans réglage, les e-mails sont seulement écrits dans le journal du serveur.
2. Envoyez un e-mail de test : le message d'erreur du serveur s'affiche.
3. Contrôlez le port et le chiffrement (587 avec `tls`, 465 avec `ssl`), ainsi que l'identifiant.
4. Si vous avez changé `APP_KEY`, le mot de passe SMTP est illisible : ressaisissez-le.

**Les sauvegardes automatiques ou les webhooks ne partent pas à l'heure.**
Vérifiez la tâche cron (chapitre [[n installation]]). Sans cron, ces tâches attendent la visite suivante. La page des sauvegardes affiche la dernière erreur.

**« Une sauvegarde est déjà en cours. »**
Une autre sauvegarde n'est pas terminée (le verrou dure 15 minutes au plus). Réessayez plus tard.

**La mise à jour est refusée.**
Le message en donne la raison :

- « Le dossier du site n'est pas accessible en écriture pour PHP » : donnez les droits d'écriture ou mettez à jour à la main (chapitre [[n exploitation-serveur]]) ;
- « Espace disque insuffisant » : libérez de la place, par exemple d'anciennes sauvegardes ;
- « Cette version demande PHP ... » : mettez PHP à jour d'abord ;
- signature refusée : l'archive a été modifiée ou ne vient pas du dépôt officiel. Ne contournez pas ce contrôle.

**« Une mise à jour précédente n'est pas terminée : finalisez-la d'abord. »**
Cliquez sur « Terminer la mise à jour » dans la page Mise à jour.

**Le site est resté en maintenance après une mise à jour.**
Terminez la mise à jour, ou désactivez la maintenance dans **Paramètres > Maintenance** (le personnel garde l'accès pendant la maintenance).

**Les réglages secrets semblent vides et les webhooks échouent tous.**
La clé `APP_KEY` a changé. Remettez l'ancienne depuis votre copie de `.env`. Sinon, ressaisissez chaque secret et régénérez le secret de chaque webhook.

**Le journal montre toujours la même adresse IP pour tout le monde.**
Le site est derrière un proxy non déclaré. Déclarez-le dans **Paramètres > Sécurité > Proxys de confiance**.

**La clé physique (WebAuthn) ne fonctionne pas.**
Le domaine de la clé est celui de `APP_URL` dans `.env`. Il doit correspondre exactement à l'adresse utilisée dans le navigateur, et le site doit être servi en HTTPS.

**Un webhook a été désactivé tout seul.**
Il a échoué 10 fois de suite. Consultez « Derniers envois », vérifiez l'adresse avec **Envoyer un test**, puis réactivez-le.

**« L'adresse vise le réseau interne du serveur : refusée. »**
Les webhooks vers le réseau local sont bloqués par défaut. Voir `NOVA_WEBHOOKS_ALLOW_PRIVATE` (chapitre [[n configuration-serveur]]).

**Je ne peux pas installer d'extension.**
En version [[version]], la clé publique des extensions n'est pas configurée : c'est normal (chapitre [[n extensions]]).

**L'assistant d'installation ne s'ouvre plus.**
Le site est déjà installé (`APP_INSTALLED=true` et administrateur créé). L'assistant ne peut pas être relancé sur un site en service : c'est une protection.

**Un élève a deux comptes avec la même adresse en majuscules et minuscules.**
Depuis la version 1.6.9, les adresses sont enregistrées en minuscules. La mise à jour vers 1.6.9 a converti les adresses existantes, sauf quand deux comptes ne différaient que par les majuscules. Ces comptes sont laissés tels quels et signalés dans `storage/logs/laravel.log` (« Adresses e-mail en double »). Gardez l'un des deux et supprimez l'autre.

**Où trouver les erreurs techniques ?**
Dans `storage/logs/laravel.log` sur le serveur. Le journal d'activité de l'administration ne contient que les actions des personnes.
