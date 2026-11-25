"""Tableaux de la documentation, GENERES depuis les sources de NovaCreneaux (jamais recopies a la main).

Chaque fonction lit le code (contrôleurs, modèles, vues, migrations, routes, openapi.yaml, git) et renvoie du Markdown.
Les descriptions rédigées (colonne « Rôle ») sont dans ROLES : le générateur échoue si le code contient un réglage
non décrit ou si une description vise un réglage disparu, ce qui garde la documentation alignée sur le code.
"""
import glob, json, os, re, subprocess
import yaml

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))


def read(rel):
    with open(os.path.join(ROOT, rel), encoding='utf-8') as f:
        return f.read()


def cell(s):
    return str(s).replace('|', '│').replace('\n', ' ').strip()


def code(s):
    s = str(s)
    return '`""`' if s == '' else '`%s`' % s.replace('`', "'").replace('|', '│')


def table(head, rows):
    out = ['| ' + ' | '.join(head) + ' |', '|' + '---|' * len(head)]
    out += ['| ' + ' | '.join(r) + ' |' for r in rows]
    return '\n'.join(out)


def version():
    return re.search(r"'version'\s*=>\s*'([^']+)'", read('config/nova.php')).group(1)


# --------------------------------------------------------------------------------------------- réglages du site

# Écran de l'administration -> réglages enregistrés par cet écran (contrôlé contre SettingController ci-dessous)
SECTIONS = {
    'general': ('Paramètres > Général', ['app_name', 'site_url', 'site_description', 'site_keywords', 'timezone', 'locale', 'site_logo',
                                         'app_tagline', 'email_placeholder', 'contact_label', 'user_term', 'user_term_plural',
                                         'user_term_feminine', 'group_label', 'group_placeholder', 'default_theme']),
    'home': ("Paramètres > Page d'accueil", ['home_message']),
    'auth': ('Paramètres > Authentification', ['registration_open', 'registration_terms', 'allowed_email_domain', 'allow_name_change',
                                               'allow_account_deletion', 'password_min_length']),
    'security': ('Paramètres > Sécurité', ['force_email_verification', 'require_2fa_admin', 'trusted_proxies', 'sms_driver', 'sms_ntfy_url',
                                          'sms_ntfy_token', 'sms_android_url', 'sms_android_login', 'sms_android_pass', 'sms_free_mobile_user',
                                          'sms_free_mobile_key', 'sms_webhook_url', 'sms_webhook_method', 'sms_webhook_body']),
    'mail': ('Paramètres > E-mail', ['mail_driver', 'mail_from_address', 'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_encryption',
                                     'mail_smtp_username', 'mail_smtp_password', 'mail_color', 'mail_signature', 'notify_account_deletion']),
    'maintenance': ('Paramètres > Maintenance', ['maintenance_mode', 'maintenance_message']),
    'api': ('Intégrations API', ['api_enabled']),
    'backup': ('Mise à jour > Sauvegardes', ['backup_enabled', 'backup_frequency', 'backup_time', 'backup_weekday', 'backup_keep']),
}

ROLES = {
    'app_name': "Nom affiché sur le site, dans les e-mails et dans l'application de double authentification.",
    'site_url': 'Adresse publique du site.',
    'site_description': "Texte de présentation enregistré pour le référencement. En 1.6.9, aucune page ne l'affiche encore.",
    'site_keywords': "Mots-clés séparés par des virgules. En 1.6.9, aucune page ne les affiche encore.",
    'timezone': "Fuseau horaire choisi dans la liste (Europe/Paris...). Sert aux heures d'ouverture, aux créneaux et aux dates de l'API.",
    'locale': "Langue (fr ou en). L'interface n'existe qu'en français en 1.6.9.",
    'site_logo': "Chemin du logo envoyé (JPG, PNG, GIF ou WebP, 2 Mo au plus). Rempli par le bouton d'envoi, pas à la main.",
    'app_tagline': "Phrase sous le nom du site (page de connexion, en-tête).",
    'email_placeholder': "Exemple d'adresse affiché dans les champs e-mail.",
    'contact_label': 'Qui contacter, repris dans les messages de refus : « Pour toute question, contactez ... ».',
    'user_term': 'Nom donné à une personne qui réserve (élève, adhérent, agent...).',
    'user_term_plural': 'Le même nom au pluriel.',
    'user_term_feminine': "1 si le nom est féminin : « une adhérente », « la stagiaire ».",
    'group_label': 'Nom du champ de regroupement des comptes (Classe, Service, Section...).',
    'group_placeholder': "Exemple affiché dans ce champ.",
    'default_theme': "Thème tant que le visiteur n'a pas choisi : system (celui de l'appareil), light ou dark.",
    'home_message': "Message en haut de la page de réservation, pour tous les comptes connectés. Le HTML est nettoyé. Vide : rien n'est affiché.",
    'registration_open': "1 : n'importe qui peut créer un compte. 0 : seuls l'administration et l'import CSV créent des comptes.",
    'registration_terms': "Conditions à accepter à l'inscription. Vide : aucune case à cocher.",
    'allowed_email_domain': "Domaines autorisés à l'inscription, séparés par des virgules (ex. lycee.fr). Vide : tous les domaines.",
    'allow_name_change': 'Permet à chacun de modifier son nom dans son profil.',
    'allow_account_deletion': 'Permet à chacun de supprimer son propre compte.',
    'password_min_length': 'Longueur minimale des nouveaux mots de passe (8 à 64). Les mots de passe existants restent valables.',
    'force_email_verification': "Renvoie vers la page de vérification les comptes dont l'adresse n'est pas confirmée.",
    'require_2fa_admin': "Oblige tout compte qui a accès à l'administration (administrateurs et personnel) à activer la double authentification avant d'y entrer.",
    'trusted_proxies': "Adresses ou réseaux des proxys de confiance (ex. 127.0.0.1, 10.0.0.0/8). Nécessaire derrière un proxy pour journaliser la vraie adresse des visiteurs.",
    'sms_driver': 'Fournisseur des codes SMS : disabled, ntfy, android_gw, free_mobile ou webhook.',
    'sms_ntfy_url': 'ntfy : adresse du serveur.',
    'sms_ntfy_token': "ntfy : jeton d'accès, si le serveur en exige un. Chiffré en base.",
    'sms_android_url': "Passerelle Android : adresse de l'API.",
    'sms_android_login': 'Passerelle Android : identifiant.',
    'sms_android_pass': 'Passerelle Android : mot de passe. Chiffré en base.',
    'sms_free_mobile_user': 'Free Mobile : identifiant.',
    'sms_free_mobile_key': "Free Mobile : clé d'identification. Chiffrée en base.",
    'sms_webhook_url': 'Webhook SMS : adresse appelée pour envoyer le code.',
    'sms_webhook_method': 'Webhook SMS : GET ou POST.',
    'sms_webhook_body': 'Webhook SMS : corps de la requête POST. Vide : formulaire standard.',
    'mail_driver': "Méthode d'envoi : disabled, sendmail ou smtp. Sans réglage, les e-mails sont seulement écrits dans le journal du serveur (log).",
    'mail_from_address': "Adresse de l'expéditeur des e-mails.",
    'mail_smtp_host': 'SMTP : serveur.',
    'mail_smtp_port': 'SMTP : port (587 en général, 465 en SSL).',
    'mail_smtp_encryption': 'SMTP : chiffrement tls, ssl ou starttls.',
    'mail_smtp_username': 'SMTP : identifiant.',
    'mail_smtp_password': 'SMTP : mot de passe. Chiffré en base, jamais réaffiché.',
    'mail_color': "Couleur du bandeau et des boutons des e-mails (#RRGGBB). Vide : couleur d'accent du site.",
    'mail_signature': 'Signature ajoutée en bas de chaque e-mail (500 caractères au plus).',
    'notify_account_deletion': "Prévient par e-mail un compte supprimé par l'administration. À décocher avant une suppression en masse.",
    'maintenance_mode': "1 : le site affiche le message de maintenance (et l'API répond 503), sauf pour le personnel et les administrateurs.",
    'maintenance_message': 'Message affiché pendant la maintenance.',
    'api_enabled': "Interrupteur général de l'API : 0 la coupe pour tout le monde sans supprimer les jetons.",
    'backup_enabled': 'Active la sauvegarde automatique de la base.',
    'backup_frequency': 'daily (tous les jours), weekly (chaque semaine) ou monthly (le 1er du mois).',
    'backup_time': 'Heure de la sauvegarde (HH:MM).',
    'backup_weekday': 'Jour de la sauvegarde hebdomadaire (1 = lundi ... 0 = dimanche).',
    'backup_keep': 'Nombre de sauvegardes automatiques conservées (1 à 90). Les sauvegardes manuelles ne sont jamais supprimées automatiquement.',
}

SECRET_KEYS = re.findall(r"'([a-z_]+)'", re.search(r'SECRET_KEYS = \[(.*?)\]', read('app/Services/SettingService.php')).group(1))


def _code_setting_keys():
    """Réglages que l'administration enregistre, lus dans les contrôleurs et services."""
    keys = set()
    ctrl = read('app/Http/Controllers/Admin/SettingController.php')
    for m in re.finditer(r"public function update\w+\(.*?\n    \}", ctrl, re.S):
        body = m.group(0)
        keys |= set(re.findall(r"'([a-z][a-z0-9_]+)'\s*=>\s*(?:'|\[|Rule)", body))
        keys |= set(re.findall(r"->set\('([a-z0-9_]+)'", body))
        km = re.search(r"\$keys = \[(.*?)\]", body)
        if km:
            keys |= set(re.findall(r"'([a-z0-9_]+)'", km.group(1)))
    keys |= set(re.findall(r"->set\('([a-z0-9_]+)'", ctrl))
    keys |= set(re.findall(r"^\s*'([a-z_]+)' => \[", re.search(r'GENERAL = \[(.*?)\];', read('app/Support/Customization.php'), re.S).group(1), re.M))
    keys |= set(re.findall(r"->set\('(backup_[a-z]+)'", read('app/Services/Backup/BackupSettings.php')))
    keys |= set(re.findall(r"->set\('([a-z_]+)'", read('app/Http/Controllers/Admin/ApiTokenController.php')))
    keys -= {'clear_secrets', 'mail_use_site_color', 'test_email', 'logo', 'roles', 'unlimited'}
    return keys


def _defaults():
    d = {}
    for f in glob.glob(os.path.join(ROOT, 'app', '**', '*.php'), recursive=True) + glob.glob(os.path.join(ROOT, 'resources', 'views', '**', '*.php'), recursive=True):
        for k, v in re.findall(r"setting\(\s*'([a-z0-9_]+)'\s*,\s*('(?:[^'\\]|\\.)*'|\d+)\s*\)", open(f, encoding='utf-8').read()):
            d.setdefault(k, set()).add(v.strip("'"))
    for k, v in re.findall(r"^\s*'([a-z_]+)' => \[(\"[^\"]*\"|'[^']*')", re.search(r'GENERAL = \[(.*?)\];', read('app/Support/Customization.php'), re.S).group(1), re.M):
        d[k] = {v[1:-1]}
    bs = read('app/Services/Backup/BackupSettings.php')
    d['backup_time'] = {re.search(r"DEFAULT_TIME = '([^']+)'", bs).group(1)}
    d['backup_weekday'] = {re.search(r'DEFAULT_WEEKDAY = (\d+)', bs).group(1)}
    d['backup_keep'] = {re.search(r'DEFAULT_KEEP = (\d+)', bs).group(1)}
    d['backup_frequency'] = {'daily'}
    d['backup_enabled'] = {re.search(r"backup_enabled', '(\d)'", bs).group(1)}
    pm = re.search(r"PASSWORD_MIN_LENGTH = \['default' => (\d+)", read('app/Support/Customization.php')).group(1)
    d['password_min_length'] = {pm}
    d.setdefault('mail_smtp_port', {'587'})
    # valeurs écrites par l'assistant d'installation : ce sont elles qu'un site neuf contient
    inst = re.search(r'\$defaults = \[(.*?)\];', read('app/Http/Controllers/InstallController.php'), re.S).group(1)
    for k, v in re.findall(r"'([a-z_]+)'\s*=>\s*'([^']*)'", inst):
        d[k] = {v}
    return d


def _labels():
    """Intitulé affiché à l'écran pour chaque champ : dernier <label> avant name="...", ou 'label' des listes d'interrupteurs."""
    labels = {}
    files = glob.glob(os.path.join(ROOT, 'resources', 'views', 'admin', 'settings', '*.blade.php')) + [os.path.join(ROOT, 'resources', 'views', 'admin', 'update', '_backups.blade.php')]
    for f in files:
        s = open(f, encoding='utf-8').read()
        # 1. <label for="cle">Texte</label> (backup-time -> backup_time)
        for k, lab in re.findall(r'<label[^>]*for="([a-z_-]+)"[^>]*>\s*([^<{]{3,120}?)\s*<', s):
            labels.setdefault(k.replace('-', '_'), lab.strip())
        # 2. listes d'interrupteurs : 'cle' => ['label' => '...'] et 'cle' => ['Libellé', 'aide']
        for k, lab in re.findall(r"'([a-z_0-9]+)'\s*=>\s*\[\s*(?:'label'\s*=>\s*)?(\"[^\"]+\"|'(?:[^'\\]|\\.)+')", s):
            labels.setdefault(k, lab[1:-1])
        # 3. <label>Texte</label> suivi du champ name="cle" ou de l'include d'un secret
        last = None
        for m in re.finditer(r"<label[^>]*>\s*([^<{]{3,120}?)\s*<|name=\"([a-z_]+)|'name' => '([a-z_]+)'", s):
            if m.group(1):
                last = m.group(1).strip()
            else:
                k = m.group(2) or m.group(3)
                if last and k not in labels:
                    labels[k] = last
                last = None
    return labels


def check_settings():
    described = {k for _, keys in SECTIONS.values() for k in keys}
    found = _code_setting_keys()
    missing = found - described
    gone = described - found
    undocumented = described - set(ROLES)
    if missing or gone or undocumented:
        raise SystemExit('Réglages non alignés sur le code : non décrits %s, disparus %s, sans rôle %s' % (sorted(missing), sorted(gone), sorted(undocumented)))
    return len(described)


def settings_table(section):
    check_settings()
    defaults, labels = _defaults(), _labels()
    rows = []
    for k in SECTIONS[section][1]:
        dv = sorted(defaults.get(k, {''}), key=len, reverse=True)[0]
        dv = code(dv) if dv != '' else '*vide*'
        role = ROLES[k] + (' **Secret.**' if k in SECRET_KEYS and 'hiffr' not in ROLES[k] else '')
        rows.append((code(k), cell(labels.get(k, '-')), dv, cell(role)))
    return table(['Réglage', 'Intitulé à l\'écran', 'Défaut', 'Rôle'], rows)


def secret_keys_list():
    return ', '.join(code(k) for k in SECRET_KEYS)


# --------------------------------------------------------------------------------------------- .env et config/nova.php

ENV_ROLES = {
    'APP_NAME': "Nom de l'application avant l'installation (l'assistant le remplace).",
    'APP_ENV': 'Environnement : production sur un vrai site.',
    'APP_KEY': "Clé de chiffrement (sessions, réglages secrets, webhooks). Créée par php artisan key:generate. Ne doit JAMAIS changer.",
    'APP_DEBUG': 'false en production : true afficherait le détail des erreurs aux visiteurs.',
    'APP_URL': "Adresse du site (l'assistant la remplace).",
    'APP_INSTALLED': "Passe à true à la fin de l'assistant : /install est alors fermé.",
    'COMPOSER_PATH': 'Chemin de Composer, pour les outils qui en ont besoin.',
    'APP_LOCALE': "Langue de l'interface (fr).",
    'APP_FALLBACK_LOCALE': 'Langue de secours (fr).',
    'APP_FAKER_LOCALE': 'Langue des données de test (développement).',
    'APP_MAINTENANCE_DRIVER': 'Mode maintenance de Laravel (distinct du mode maintenance de Paramètres).',
    'BCRYPT_ROUNDS': 'Coût du hachage des mots de passe.',
    'LOG_CHANNEL': 'Journal technique du serveur (storage/logs).',
    'LOG_STACK': 'Canaux du journal technique.',
    'LOG_DEPRECATIONS_CHANNEL': 'Journal des fonctions obsolètes (null : désactivé).',
    'LOG_LEVEL': 'Niveau minimal écrit dans le journal technique.',
    'DB_CONNECTION': 'Type de base : mysql (MySQL ou MariaDB).',
    'DB_HOST': 'Serveur de la base (rempli par l\'assistant).',
    'DB_PORT': 'Port de la base.',
    'DB_DATABASE': 'Nom de la base.',
    'DB_USERNAME': 'Utilisateur de la base.',
    'DB_PASSWORD': 'Mot de passe de la base.',
    'SESSION_DRIVER': 'Stockage des sessions (file).',
    'SESSION_LIFETIME': "Durée d'inactivité avant déconnexion, en minutes.",
    'SESSION_ENCRYPT': 'Chiffrement des sessions.',
    'SESSION_PATH': 'Chemin du cookie de session.',
    'SESSION_DOMAIN': 'Domaine du cookie de session.',
    'BROADCAST_CONNECTION': 'Non utilisé par NovaCreneaux (log).',
    'FILESYSTEM_DISK': 'Disque de fichiers par défaut (local).',
    'QUEUE_CONNECTION': "File d'attente : sync, les tâches s'exécutent tout de suite.",
    'CACHE_STORE': 'Cache (file). Les réglages du site y sont mis en cache.',
    'MAIL_MAILER': "Valeurs d'origine de Laravel : la configuration des e-mails se fait dans Paramètres > E-mail.",
    'MAIL_SCHEME': 'Voir MAIL_MAILER.', 'MAIL_HOST': 'Voir MAIL_MAILER.', 'MAIL_PORT': 'Voir MAIL_MAILER.',
    'MAIL_USERNAME': 'Voir MAIL_MAILER.', 'MAIL_PASSWORD': 'Voir MAIL_MAILER.',
    'MAIL_FROM_ADDRESS': "Expéditeur par défaut si Paramètres > E-mail n'en donne pas.",
    'MAIL_FROM_NAME': 'Nom d\'expéditeur par défaut.',
    'VITE_APP_NAME': 'Nom transmis aux fichiers compilés (développement).',
    'NOVA_PLUGINS_CATALOG_REPO': 'Dépôt GitHub du catalogue d\'extensions.',
    'NOVA_PLUGINS_ALLOW_UNSIGNED': 'Développement seulement : accepte une extension copiée à la main, sans archive signée.',
    'NOVA_BACKUPS_ON_REQUEST': 'Sans cron, lance une sauvegarde due à la première visite après l\'heure prévue.',
    'NOVA_WEBHOOKS_ON_REQUEST': 'Sans cron, envoie les webhooks en attente après la requête suivante.',
    'NOVA_WEBHOOKS_ALLOW_PRIVATE': 'true : accepte http et les adresses du réseau interne pour les webhooks. Seulement pour un service interne de confiance.',
}


def env_table():
    rows = []
    for ln in read('.env.example').splitlines():
        m = re.match(r'^([A-Z_]+)=(.*)$', ln)
        if m:
            k, v = m.groups()
            if k not in ENV_ROLES:
                raise SystemExit('Variable .env non décrite : ' + k)
            rows.append((code(k), code(v.strip('"')) if v else '*vide*', cell(ENV_ROLES[k])))
    return table(['Variable', 'Valeur de .env.example', 'Rôle'], rows)


def nova_env_table():
    rows = []
    for k, v in re.findall(r"env\('([A-Z_]+)',\s*([^)]+)\)", read('config/nova.php')):
        if k not in ENV_ROLES:
            raise SystemExit('Variable non décrite : ' + k)
        rows.append((code(k), code(v.strip("'")), cell(ENV_ROLES[k])))
    return table(['Variable (facultative)', 'Défaut', 'Rôle'], rows)


def nova_config_table():
    s = read('config/nova.php')
    rows = [(code("version"), code(version()), 'Version installée. Change seulement avec une mise à jour.'),
            (code('repo'), code(re.search(r"'repo' => '([^']+)'", s).group(1)), 'Dépôt GitHub dont les versions publiées servent de source aux mises à jour.'),
            (code('update_public_key'), 'renseignée' if re.search(r"'update_public_key' => '[^']+'", s) else '*vide*', 'Clé publique qui vérifie la signature des archives de mise à jour. Vide : mises à jour refusées.'),
            (code('plugins.public_key'), 'renseignée' if re.search(r"'public_key' => '[^']+'", s) else '*vide*', 'Clé publique des extensions. Vide : installation d\'extensions refusée.'),
            (code('plugins.path'), code('plugins/'), 'Dossier des extensions installées.'),
            (code('plugins.state'), code('storage/app/plugins.json'), 'Extensions actives ou en échec (hors base de données).'),
            (code('backups.directory'), code('storage/app/database-backups'), 'Dossier des sauvegardes de la base, jamais servi par le serveur web.')]
    return table(['Clé de config/nova.php', 'Valeur en %s' % version(), 'Rôle'], rows)


# --------------------------------------------------------------------------------------------- rôles, permissions, limites

def permissions_table():
    s = read('app/Support/Permission.php')
    consts = dict(re.findall(r"const (\w+) = '([^']+)'", s))
    core = re.search(r'function core\(\).*?return \[(.*?)\];', s, re.S).group(1)
    routes = read('routes/web.php')
    rows = []
    for const, label in re.findall(r'self::(\w+)\s*=>\s*(\'(?:[^\'\\]|\\.)*\'|"[^"]*")', core):
        key = consts[const]
        group = re.search(r"permission:'\.Permission::%s\)->group\(function \(\) \{(.*?)\n    \}\);" % const, routes, re.S)
        body = group.group(1) if group else ''
        segs = re.findall(r"^        Route::(?:get|post|put|patch|delete|resource|prefix)\('/?([a-z-]+)", body, re.M)
        urls = sorted({'/admin/' + x for x in segs})
        rows.append((code(key), cell(label[1:-1].replace("\\'", "'")), ', '.join(code(u) for u in urls[:4])))
    return table(['Permission', 'Libellé dans le formulaire du rôle', 'Pages ouvertes'], rows)


def admin_only_list():
    routes = read('routes/web.php')
    block = routes[routes.index("Route::middleware('admin')"):]
    pref = sorted(set(re.findall(r"^        Route::(?:prefix|resource|get|post|put|delete)\('/?([a-z-]+)", block, re.M)))
    return ', '.join(code('/admin/' + p) for p in pref)


def limits_table():
    s = read('app/Support/ReservationLimits.php')
    mig = read('database/migrations/2026_06_15_000001_add_limits_to_roles_table.php')
    rows = []
    for key, label, help_, unit, mn, mx, unl in re.findall(r"'(\w+)' => \[(\"[^\"]+\"|'[^']+'), '[^']+', (\"[^\"]+\"|'[^']+'), '([^']+)', (\d+), (\d+), ('[^']+'|null)\]", s):
        d = re.search(r"'%s'\)->default\((-?\d+)\)" % key, mig).group(1)
        rows.append((code(key), cell(label[1:-1]), cell(help_[1:-1]), '%s à %s %s' % (mn, mx, unit), 'non' if unl == 'null' else cell(unl[1:-1]), code(d)))
    return table(['Colonne', 'Limite', 'Sens', 'Valeurs', '« Sans limite »', 'Défaut (-1 = sans limite)'], rows)


def default_roles_table():
    s = read('database/migrations/2026_06_12_000003_create_roles_table.php')
    rows = []
    for name, label, desc, adm in re.findall(r"'name' => '(\w+)',\s*'label' => '([^']+)',\s*'description' => (\"[^\"]+\"|'[^']+').*?'is_admin' => (true|false)", s):
        rows.append((code(name), label, cell(desc[1:-1]), 'oui' if adm == 'true' else 'non'))
    return table(['Nom interne', 'Libellé', 'Description d\'origine', 'Administrateur'], rows)


def sanctions_table():
    s = read('app/Models/Sanction.php')
    rows = [(code(k), lab) for k, lab in re.findall(r"'(\w+)' => \['([^']+)'", re.search(r'SEVERITIES = \[(.*?)\];', s, re.S).group(1))]
    return table(['Gravité (valeur)', 'Libellé'], rows)


# --------------------------------------------------------------------------------------------- réservations

def failure_codes_table(with_status=True):
    files = ['app/Services/ReservationService.php', 'app/Models/Resource.php']
    found = {}
    for f in files:
        s = read(f)
        for c, msg in re.findall(r"failure\('(\w+)',\s*((?:\"[^\"]*\"|'(?:[^'\\]|\\.)*')(?:\s*\.\s*[^;]+)?)\)", s):
            found.setdefault(c, msg)
        for c, msg in re.findall(r"'code'=>'(\w+)','reason'=>((?:\"[^\"]*\"|'(?:[^'\\]|\\.)*')[^\]]*)\]", s):
            found.setdefault(c, msg)
    api = read('app/Http/ApiError.php')
    m409 = re.search(r"'banned' => 403,\s*(.*?) => 409", api, re.S).group(1)
    c409 = set(re.findall(r"'(\w+)'", m409))
    doc = re.search(r'Codes : (.*?)\.\n', read('app/Services/ReservationService.php'), re.S).group(1)
    order = [c.strip() for c in re.sub(r'\s*\*\s*', ' ', doc).split(',')]
    rows = []
    for c in order:
        parts = []
        for dq, sq, var in re.findall(r"\"([^\"]*)\"|'((?:[^'\\]|\\.)*)'|(\$\w+(?:->\w+)*(?:\([^)]*\))?(?:->\w+\([^)]*\))?|self::\w+\(\)|substr\([^)]*\))", found.get(c, '')):
            if var:
                parts.append('' if 'contactHint' in var else '...')
            else:
                parts.append(re.sub(r'\{\$[^}]+\}', '...', dq if dq else sq.replace("\\'", "'")))
        msg = re.sub(r'\s+', ' ', ' '.join(p for p in parts if p)).replace(' ...', ' ...').strip()
        msg = re.sub(r"(\S)\s*\.\.\.", r'\1 ...', msg).replace('... .', '...')
        status = '403' if c == 'banned' else '409' if c in c409 else '422'
        rows.append((code(c), cell(msg), status) if with_status else (cell(msg), code(c)))
    if not with_status:
        return table(['Message affiché', 'Code'], rows)
    return table(['Code', 'Message affiché (extrait du code)', 'Statut HTTP (API)'], rows)


# --------------------------------------------------------------------------------------------- commandes

CMD_ACCENTS = {'Cree': 'Crée', 'Revoque': 'Révoque', 'immediatement': 'immédiatement', 'affiche': 'affiché', 'integration': 'intégration',
               'defaut': 'défaut', 'Duree': 'Durée', 'validite': 'validité'}


def accents(t):
    for a, b in CMD_ACCENTS.items():
        t = re.sub(r'\b%s\b' % a, b, t)
    return t


def artisan_table():
    rows = []
    for f in sorted(glob.glob(os.path.join(ROOT, 'app', 'Console', 'Commands', '*.php'))):
        s = open(f, encoding='utf-8').read()
        sig = re.search(r"\$signature\s*=\s*'((?:[^'\\]|\\.)*)'", s, re.S).group(1).replace("\\'", "'")
        desc = re.search(r"\$description\s*=\s*'((?:[^'\\]|\\.)*)'", s).group(1).replace("\\'", "'")
        name = sig.split()[0]
        args = re.findall(r'\{([^}]+)\}', sig)
        syn = ' '.join(('[%s]' % a.split(':')[0].strip().rstrip('?=*')) if ('?' in a.split(':')[0] or a.strip().startswith('--')) else '<%s>' % a.split(':')[0].strip() for a in args)
        rows.append((code(('php artisan %s %s' % (name, syn)).strip()), cell(accents(desc))))
    return table(['Commande', 'Rôle (description de la commande)'], rows)


def artisan_args():
    """Détail des arguments et options de chaque commande."""
    out = []
    for f in sorted(glob.glob(os.path.join(ROOT, 'app', 'Console', 'Commands', '*.php'))):
        s = open(f, encoding='utf-8').read()
        sig = re.search(r"\$signature\s*=\s*'((?:[^'\\]|\\.)*)'", s, re.S).group(1).replace("\\'", "'")
        args = re.findall(r'\{([^}]+)\}', sig)
        if not args:
            continue
        out.append('**%s**\n' % code('php artisan ' + sig.split()[0]))
        for a in args:
            n, _, d = a.partition(':')
            out.append('- %s : %s' % (code(n.strip()), accents(d.strip())))
        out.append('')
    return '\n'.join(out)


def schedule_table():
    s = read('routes/console.php')
    rows = []
    for cls, freq in re.findall(r'Schedule::command\((\w+)::class\)->(\w+)\(\)', s):
        f = open(os.path.join(ROOT, 'app', 'Console', 'Commands', cls + '.php'), encoding='utf-8').read()
        name = re.search(r"\$signature\s*=\s*'([^\s']+)", f).group(1)
        rows.append((code(name), {'daily': 'tous les jours', 'everyMinute': 'chaque minute'}.get(freq, freq)))
    return table(['Tâche planifiée', 'Fréquence'], rows)


# --------------------------------------------------------------------------------------------- API

def api_table():
    spec = yaml.safe_load(read('public/docs/openapi.yaml'))
    routes = read('routes/api.php')
    abil = {}
    for block in re.finditer(r"abilities:([a-z:]+)'.*?->group\(function \(\) \{(.*?)\}\);", routes, re.S):
        for method, path in re.findall(r"Route::(\w+)\('([^']+)'", block.group(2)):
            abil[(method, '/' + path)] = block.group(1)
    rows = []
    for path, ops in spec['paths'].items():
        for method, op in ops.items():
            a = abil.get((method, path), 'aucun' if path == '/meta' else 'jeton seul')
            rows.append((code(method.upper()), code('/api/v1' + path), cell(op.get('summary', '')), code(a) if ':' in a else a))
    return table(['Méthode', 'Adresse', 'Rôle', 'Droit exigé'], rows)


def abilities_table():
    s = read('app/Services/ApiTokenService.php')
    rows = [(code(k), cell(v)) for k, v in re.findall(r"'([a-z]+:[a-z]+)' => '([^']+)'", s)]
    return table(['Droit', 'Ce qu\'il permet'], rows)


def rate_limits_table():
    s = read('app/Providers/AppServiceProvider.php')
    lab = {'api-ip': 'toutes les requêtes, par adresse IP', 'api': 'requêtes authentifiées, par jeton', 'api-write': 'écritures (POST, DELETE), par jeton'}
    rows = [(code(n), lab.get(n, ''), '%s par minute' % v) for n, v in re.findall(r"RateLimiter::for\('([a-z-]+)'.*?perMinute\((\d+)\)", s)]
    return table(['Limite', 'S\'applique à', 'Plafond'], rows)


def personal_tokens():
    s = read('app/Services/ApiTokenService.php')
    d = re.search(r'PERSONAL_DURATIONS = \[([^\]]+)\]', s).group(1)
    n = re.search(r'PERSONAL_LIMIT = (\d+)', s).group(1)
    return d.replace(',', ' ou').replace('  ', ' ').strip(), n


# --------------------------------------------------------------------------------------------- webhooks

def webhook_events_table():
    s = read('app/Support/WebhookEvent.php')
    consts = dict(re.findall(r"const (\w+) = '([^']+)'", s))
    rows = [(code(consts[c]), cell(l.replace("\\'", "'"))) for c, l in re.findall(r"self::(\w+) => '((?:[^'\\]|\\.)*)'", s)]
    return table(['Événement', 'Quand il part'], rows)


def webhook_presets_table():
    rows = []
    presets = [json.load(open(f, encoding='utf-8')) for f in glob.glob(os.path.join(ROOT, 'resources', 'webhook-presets', '*.json'))]
    presets.sort(key=lambda p: (p.get('category', ''), p.get('name', '')))
    for p in presets:
        rows.append((p['name'], cell(p.get('category', '')), cell(p.get('description', ''))))
    return table(['Service', 'Catégorie', 'Ce qui est envoyé'], rows), len(presets)


def webhook_variables_table():
    s = read('app/Services/Webhook/EventMessage.php')
    block = re.search(r'VARIABLES = \[(.*?)\];', s, re.S).group(1)
    rows = [(code('{{%s}}' % k), cell(v[1:-1].replace("\\'", "'"))) for k, v in re.findall(r"'(\w+)' => (\"[^\"]+\"|'(?:[^'\\]|\\.)*')", block)]
    return table(['Variable', 'Contenu'], rows)


def webhook_constants():
    s = read('app/Services/Webhook/WebhookSender.php')
    return {'attempts': re.search(r'MAX_ATTEMPTS = (\d+)', s).group(1),
            'backoff': re.search(r'BACKOFF_MINUTES = \[([^\]]+)\]', s).group(1),
            'disable': re.search(r'DISABLE_AFTER = (\d+)', s).group(1),
            'keep': re.search(r'KEEP_DAYS = (\d+)', read('app/Services/Webhook/WebhookRunner.php')).group(1)}


# --------------------------------------------------------------------------------------------- divers

def sms_drivers():
    return re.search(r"'sms_driver'\s*=>\s*'nullable\|in:([^']+)'", read('app/Http/Controllers/Admin/SettingController.php')).group(1).split(',')


def prerequisites_table():
    s = read('app/Http/Controllers/InstallController.php')
    rows = [(cell(n),) for n in re.findall(r"\['name'=>'([^']+)'", s)]
    return table(["Contrôle de l'assistant (étape Prérequis)"], rows)


def mfa_constants():
    t = read('app/Services/TotpService.php')
    o = read('app/Services/OtpService.php')
    m = read('app/Http/Controllers/MfaController.php')
    return {'digits': re.search(r'DIGITS\s*= (\d+)', t).group(1), 'period': re.search(r'PERIOD\s*= (\d+)', t).group(1),
            'otp_minutes': re.search(r'EXPIRY_MINUTES = (\d+)', o).group(1), 'resend': re.search(r'RESEND_SECONDS = (\d+)', o).group(1),
            'max': re.search(r'MFA_MAX_ATTEMPTS = (\d+)', m).group(1), 'decay': int(re.search(r'MFA_DECAY_SECONDS = (\d+)', m).group(1)) // 60,
            'recovery': re.search(r'generateRecoveryCodes\(int \$count = (\d+)\)', m).group(1)}


def backup_constants():
    s = read('app/Services/Backup/BackupManager.php')
    return {'before_update': re.search(r'KEPT_BEFORE_UPDATE = (\d+)', s).group(1)}


def nav_types_table():
    s = read('app/Models/NavItem.php')
    rows = [(code(k), cell(v.replace("\\'", "'"))) for k, v in re.findall(r"'(\w+)'\s*=>\s*'((?:[^'\\]|\\.)*)'", re.search(r'typeLabels = \[(.*?)\];', s, re.S).group(1))]
    return table(['Type', 'Libellé'], rows)


def bulk_actions():
    s = read('app/Http/Controllers/Admin/UserBulkController.php')
    return re.findall(r"'(\w+)'", re.search(r'ACTIONS = \[([^\]]+)\]', s).group(1))


def manifest_example():
    s = read('app/Plugins/Manifest.php')
    ex = re.search(r'\* \{\n(.*?)\n \* \}', s, re.S).group(1).replace(' * ', '').replace(' *', '')
    return ex.replace('\\', '\\\\')  # JSON valide : la barre oblique inverse des espaces de noms se double


def protected_paths():
    s = read('app/Services/Update/PathPolicy.php')
    dirs = re.findall(r"'([^']+)'", re.search(r'DIRECTORIES = \[([^\]]+)\]', s).group(1))
    files = re.findall(r"'([^']+)'", re.search(r'ROOT_FILES = \[([^\]]+)\]', s).group(1))
    return dirs, files


def test_inventory():
    files = [f for f in glob.glob(os.path.join(ROOT, 'tests', '**', '*Test.php'), recursive=True)]
    n_methods = 0
    by_dir = {}
    for f in files:
        s = open(f, encoding='utf-8').read()
        k = len(re.findall(r'public function test_\w+|#\[Test\]\s*\n\s*public function', s))
        n_methods += k
        d = os.path.relpath(os.path.dirname(f), os.path.join(ROOT, 'tests'))
        by_dir.setdefault(d, [0, 0])
        by_dir[d][0] += 1
        by_dir[d][1] += k
    rows = [(code('tests/' + d), str(a), str(b)) for d, (a, b) in sorted(by_dir.items())]
    return table(['Dossier', 'Fichiers', 'Tests'], rows), len(files), n_methods


def changelog():
    tags = subprocess.run(['git', 'tag', '--sort=-v:refname'], cwd=ROOT, capture_output=True, text=True).stdout.split()
    out = []
    for i, t in enumerate(tags):
        date = subprocess.run(['git', 'log', '-1', '--format=%ad', '--date=short', t], cwd=ROOT, capture_output=True, text=True).stdout.strip()
        subj = subprocess.run(['git', 'log', '-1', '--format=%s', t], cwd=ROOT, capture_output=True, text=True).stdout.strip()
        title = re.sub(r'^(Version \S+|NovaCreneaux \S+)\s*:?\s*', '', subj) or 'Première version publiée'
        prev = tags[i + 1] if i + 1 < len(tags) else None
        mig = []
        if prev:
            diff = subprocess.run(['git', 'diff', '--name-only', '--diff-filter=A', prev, t, '--', 'database/migrations'], cwd=ROOT, capture_output=True, text=True).stdout.split()
            mig = [os.path.basename(m)[18:-4].replace('_', ' ') for m in diff]
            files = subprocess.run(['git', 'diff', '--shortstat', prev, t], cwd=ROOT, capture_output=True, text=True).stdout.strip()
        else:
            files = ''
        out.append((t, date, title[0].upper() + title[1:], mig, files))
    return out


def theme_table():
    s = read('app/Support/ThemeSettings.php')
    rows = [(code(re.search(r"ACCENT = '([^']+)'", s).group(1)), "Couleur d'accent (boutons, liens)", 'les deux', code(re.search(r"ACCENT_DEFAULT = '([^']+)'", s).group(1)))]
    for mode, label in (('DARK', 'sombre'), ('LIGHT', 'clair')):
        block = re.search(r"self::%s => \[(.*?)\]," % mode + r"\s*(?:self::|\];)", s, re.S).group(1)
        for k, lab, d in re.findall(r"'(\w+)' => \['([^']+)', '[^']+', '([^']+)'\]", block):
            rows.append((code(k), lab.replace('topbar', "barre du haut"), label, code(d)))
    return table(['Réglage', 'Élément', 'Thème', 'Défaut'], rows)


# Titres des versions : les messages de commit sont sans accents ; corrections typographiques seulement
ACCENTS = {'reservations': 'réservations', 'reservation': 'réservation', 'generes': 'générés', 'oublie': 'oublié', 'securite': 'sécurité',
           'cle': 'clé', 'mise a jour': 'mise à jour', 'reparee': 'réparée', 'acces': 'accès', 'role': 'rôle', 'Roles': 'Rôles',
           'glisser-deposer': 'glisser-déposer', 'theme': 'thème', 'detail': 'détail', 'admin': 'administration'}

AREAS = [('app/Services/Webhook', 'webhooks'), ('app/Services/Backup', 'sauvegardes'), ('app/Services/Update', 'mises à jour'),
         ('app/Plugins', 'extensions'), ('app/Http/Controllers/Api', 'API'), ('app/Http/Controllers/Admin', 'administration'),
         ('app/Http/Controllers/Auth', 'connexion et comptes'), ('app/Http/Controllers/Mfa', 'double authentification'),
         ('app/Services/Reservation', 'règles de réservation'), ('app/Models', 'modèles de données'), ('app/Http/Middleware', 'sécurité des requêtes'),
         ('app/Support', 'règles partagées'), ('resources/views/admin', 'pages d\'administration'), ('resources/views', 'pages du site'),
         ('resources/webhook-presets', 'services de webhook'), ('database/migrations', 'base de données'), ('public/docs', 'documentation de l\'API'),
         ('scripts', 'publication'), ('.github', 'intégration continue'), ('tests', 'tests'), ('config', 'configuration'), ('routes', 'adresses du site')]


def fix_title(t):
    for a, b in ACCENTS.items():
        t = re.sub(r'\b%s\b' % re.escape(a), b, t)
    return t[0].upper() + t[1:]


def changelog_md():
    out = []
    tags = subprocess.run(['git', 'tag', '--sort=-v:refname'], cwd=ROOT, capture_output=True, text=True).stdout.split()
    rows = []
    for tag, date, title, mig, stat in changelog():
        rows.append((code(tag[1:]), date, cell(fix_title(title))))
    out.append(table(['Version', 'Date', 'Titre'], rows))
    for i, (tag, date, title, mig, stat) in enumerate(changelog()):
        out.append('\n## Version %s (%s)\n\n**%s.** %s\n' % (tag[1:], date, fix_title(title).rstrip('.'), SUMMARY.get(tag, '')))
        prev = tags[i + 1] if i + 1 < len(tags) else None
        if prev:
            files = subprocess.run(['git', 'diff', '--name-only', prev, tag], cwd=ROOT, capture_output=True, text=True).stdout.split()
            areas = []
            for f in files:
                for pre, lab in AREAS:
                    if f.startswith(pre):
                        if lab not in areas:
                            areas.append(lab)
                        break
            m = re.match(r'(\d+) files? changed(?:, (\d+) insertions?\(\+\))?(?:, (\d+) deletions?\(-\))?', stat)
            if m:
                out.append('- Ampleur : %s fichiers modifiés, %s lignes ajoutées, %s retirées.' % (m.group(1), m.group(2) or 0, m.group(3) or 0))
            if areas:
                out.append('- Parties concernées : %s.' % ', '.join(areas))
            if mig:
                mfiles = subprocess.run(['git', 'diff', '--name-only', '--diff-filter=A', prev, tag, '--', 'database/migrations'], cwd=ROOT, capture_output=True, text=True).stdout.split()
                out.append('- Évolution de la base (migration appliquée à la mise à jour) : %s.' % ', '.join(code(os.path.basename(x)[:-4]) for x in mfiles))
            else:
                out.append('- Pas de changement de structure de la base.')
        else:
            n = len(glob.glob(os.path.join(ROOT, 'database', 'migrations', '*.php')))
            out.append('- Point de départ de l\'historique.')
    return '\n'.join(out)

# Résumé rédigé de chaque version, établi à partir des fichiers ajoutés et modifiés par la version (git diff)
SUMMARY = {
    'v1.0.0': "Réservation de créneaux, administration des ressources, des créneaux et des comptes, double authentification, API v1, assistant d'installation et mise à jour par archive signée.",
    'v1.0.1': "Les réglages secrets (mot de passe SMTP, jetons SMS) sont désormais chiffrés en base. Arrivée des sauvegardes de la base : manuelles, automatiques selon un calendrier, lancées par cron ou à la visite suivante.",
    'v1.1.0': "Permissions délégables par rôle : le personnel n'entre que dans les sections confiées. Menu d'administration adapté à ces droits, journal automatique des modifications (ressources, créneaux, rôles, pages) et réglage des couleurs du site.",
    'v1.1.1': "Le glisser-déposer de la liste des rôles fonctionne (ajout de SortableJS).",
    'v1.2.0': "Chaque compte autorisé peut créer ses propres jetons d'API depuis son profil. Une case par rôle autorise ou non l'accès à l'API.",
    'v1.3.0': "Notifications sur le site, premiers webhooks (envoi en tâche de fond), sauvegarde de la base avant chaque mise à jour, interrupteur général de l'API et avis par e-mail à la suppression d'un compte. Passage sous licence MIT.",
    'v1.4.0': "Webhooks : premier format mis en forme, pour Discord.",
    'v1.4.1': "Correction de la sauvegarde faite avant une mise à jour. Les refus d'accès à l'API et les envois de webhooks abandonnés sont notés dans le journal. Corrections des pages et des notifications.",
    'v1.4.2': "Renforcement de la sécurité : nettoyage du HTML des pages et des messages (protection contre l'injection de scripts), refus des liens symboliques lors des mises à jour, assistant d'installation et clé physique plus sûrs.",
    'v1.4.3': "Corrections issues d'un audit de sécurité : fermeture des autres sessions après un changement de mot de passe, en-têtes de sécurité HTTP, vérification des liens de navigation, audit des dépendances dans l'intégration continue.",
    'v1.5.0': "Webhooks à modèles : services préréglés (Discord, Slack, Teams, Telegram, Make, n8n...) et format personnalisé avec variables. Le format Discord de la 1.4.0 est remplacé par ce système.",
    'v1.6.0': "Système d'extensions : catalogue signé, installation, activation, désactivation, permissions et menus propres à chaque extension, commande plugin:manage.",
    'v1.6.1': "La liste des comptes de l'administration affiche le nombre d'utilisateurs. Correctifs de sécurité.",
    'v1.6.2': "Page des limites de réservation par rôle refaite, avec une description unique des quatre limites (formulaire, validation, enregistrement).",
    'v1.6.3': "Export PDF du planning des réservations.",
    'v1.6.4': "Suspensions avec gravité (mineure, moyenne, grave), durée ou caractère définitif, depuis la liste ou la fiche d'un compte. E-mail de mot de passe oublié refait en français, au format des autres e-mails.",
    'v1.6.5': "Personnalisation sans toucher au code : sous-titre, exemple d'adresse, qui contacter, vocabulaire (« élève », « classe »), thème par défaut, couleur et signature des e-mails, longueur minimale des mots de passe.",
    'v1.6.6': "Les QR codes de la double authentification sont produits sur le serveur : aucun service extérieur n'est appelé. Corrections d'affichage (créneaux, rôles, thèmes, profil).",
    'v1.6.7': "Ouverture des réservations « la veille » : le jour le plus lointain s'ouvre à l'heure d'ouverture de la ressource. L'heure limite d'annulation est affichée dans « Mes réservations ».",
    'v1.6.8': "Planning du jour par ressource et par créneau, avec réservation pour le compte d'un élève. Le journal d'activité montre le détail avant et après de chaque modification, y compris des réglages.",
    'v1.6.9': "Les adresses e-mail sont enregistrées en minuscules : « Lea.Martin@... » et « lea.martin@... » sont le même compte. Les adresses existantes sont converties à la mise à jour.",
}


def admin_menu_table():
    """Menu de l'administration, lu dans app/Support/AdminNavigation.php (sections, entrées, sous-entrées)."""
    s = read('app/Support/AdminNavigation.php')
    body = s[s.index('private function definition()'):s.index('private function pluginSection()')]
    rows = []
    for header, items in re.findall(r"\['header' => (null|'[^']+'), 'items' => \[(.*?)\n            \]\],", body, re.S):
        head = 'En tête du menu' if header == 'null' else header.strip("'")
        labels = []
        for m in re.finditer(r"\$this->link\('([^']+)'|'label' => '([^']+)', 'icon' => '[^']+', 'id' => '\w+', 'children' => \[", items):
            labels.append(m.group(1) or m.group(2))
        if 'children' in items:
            parent = re.search(r"'label' => '([^']+)', 'icon' => '[^']+', 'id' => '\w+', 'children'", items).group(1)
            kids = re.findall(r"\$this->link\('([^']+)'", items)
            rows.append((cell(head), '**%s** : %s' % (parent, ', '.join(kids))))
        else:
            rows.append((cell(head), ', '.join('**%s**' % l for l in labels)))
    return table(['Rubrique du menu', 'Entrées'], rows)
