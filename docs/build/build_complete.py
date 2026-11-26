#!/usr/bin/env python3
"""Documentation de NovaCreneaux : un PDF par édition, en trois parties (générique : Utilisateurs, Personnel, Administrateur ; lycée Louis Rascol : Élèves, Documentalistes, Technique).

Chaîne : chapitres Markdown (docs/complete/fr) + tableaux générés depuis les sources (sources.py)
-> pandoc (HTML) -> Brave sans interface (PDF) -> PyMuPDF (sommaire paginé, en-têtes, numéros de page, signets).

Mise en page sobre : pages blanches, aucun fond coloré.
Usage : docs/build/.venv/bin/python docs/build/build_complete.py [generique] [rascol]      (pandoc, Brave, pip install pymupdf pyyaml)
Sorties : docs/pdf/NovaCreneaux - Documentation complete.pdf (édition générique)
          docs/pdf/NovaCreneaux - Lycee Louis Rascol - Documentation du CDI.pdf (édition du CDI du lycée Louis Rascol)
"""
import glob, html, os, re, subprocess, sys, tempfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import sources as S

ROOT = S.ROOT
DOCS = os.path.join(ROOT, 'docs')
IMAGES = os.path.join(DOCS, 'images')
BROWSER = '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser'
VERSION = S.version()
MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']


def date_fr():
    import datetime
    d = datetime.date.today()
    return '%d %s %d' % (d.day, MOIS[d.month - 1], d.year)


# ----------------------------------------------------------------------------- remplacement des balises [[...]]

def generated():
    presets, n_presets = S.webhook_presets_table()
    tests, n_files, n_tests = S.test_inventory()
    dirs, files = S.protected_paths()
    mfa, wh = S.mfa_constants(), S.webhook_constants()
    durations, limit = S.personal_tokens()
    g = {
        'version': VERSION, 'settings_count': str(S.check_settings()), 'secret_keys': S.secret_keys_list(), 'secret_count': str(len(S.SECRET_KEYS)),
        'env': S.env_table(), 'nova_env': S.nova_env_table(), 'nova_config': S.nova_config_table(),
        'permissions': S.permissions_table(), 'admin_only': S.admin_only_list(), 'limits': S.limits_table(),
        'default_roles': S.default_roles_table(), 'sanctions': S.sanctions_table(), 'failures': S.failure_codes_table(), 'failures_user': S.failure_codes_table(with_status=False),
        'artisan': S.artisan_table(), 'artisan_args': S.artisan_args(), 'schedule': S.schedule_table(),
        'api': S.api_table(), 'abilities': S.abilities_table(), 'rate_limits': S.rate_limits_table(),
        'tok_durations': durations, 'tok_limit': limit,
        'events': S.webhook_events_table(), 'presets': presets, 'presets_count': str(n_presets), 'variables': S.webhook_variables_table(),
        'sms_drivers': ', '.join(S.code(d) for d in S.sms_drivers()), 'prereq': S.prerequisites_table(), 'nav_types': S.nav_types_table(),
        'theme': S.theme_table(), 'manifest': S.manifest_example(), 'plugin_api': re.search(r'API_VERSION = (\d+)', S.read('app/Plugins/PluginManager.php')).group(1),
        'protected_dirs': ', '.join(S.code(d + '/') for d in dirs), 'protected_files': ', '.join(S.code(f) for f in files),
        'backup_before_update': S.backup_constants()['before_update'],
        'admin_menu': S.admin_menu_table(), 'config_templates': S.config_templates_table(), 'tests_table': tests, 'tests_files': str(n_files), 'tests_count': str(n_tests), 'changelog': S.changelog_md(),
    }
    for path in glob.glob(os.path.join(DOCS, 'complete', 'figures', '*.yml')):  # [[fig planning]] : capture annotée
        g['fig ' + os.path.basename(path)[:-4]] = '```annoter\n' + open(path, encoding='utf-8').read() + '```'
    for k, v in mfa.items():
        g['mfa ' + k] = str(v)
    for k, v in wh.items():
        g['wh ' + k] = v
    for sec in S.SECTIONS:
        g['settings ' + sec] = S.settings_table(sec)
    return g


def fill(md, g):
    def sub(m):
        key = m.group(1).strip()
        if key.startswith('!'):  # [[!cle]] : balise écrite telle quelle (exemples dans le texte)
            return '[[' + key[1:] + ']]'
        if key not in g:
            raise SystemExit('Balise inconnue : [[%s]]' % key)
        return g[key]
    return re.sub(r'\[\[([^\]]+)\]\]', sub, md)


# Éditions : chacune est une suite de parties ; une partie liste ses chapitres (motifs relatifs à docs/complete).
# Une édition peut reprendre des chapitres de l'édition générique (fr/...) et y ajouter les siens.
# « alias » : renvoi d'un chapitre générique vers le chapitre qui joue ce rôle dans l'édition.
EDITIONS = {
    'generique': {
        'out': 'NovaCreneaux - Documentation complete.pdf',
        'header': 'NovaCreneaux - Documentation complete',
        'cover_top': 'LOGICIEL LIBRE · RÉSERVATION DE CRÉNEAUX',
        'cover_sub': 'Documentation complète',
        'cover_desc': "Guide de l'utilisateur, du personnel, de l'administrateur et du développeur : installation, "
                      "règles de réservation, configuration, exploitation, API, webhooks et extensions.",
        'parts': [
            (None, None, None, ['fr/0_intro/*.md']),
            (1, 'Utilisateurs', "Pour les personnes qui réservent : se connecter, réserver, annuler, gérer son profil et comprendre les règles de réservation.", ['fr/1_utilisateur/*.md']),
            (2, 'Personnel', "Pour les membres du personnel qui ont accès à l'administration : ressources, planning, comptes, sanctions, contenus et journal.", ['fr/2_personnel/*.md']),
            (3, 'Administrateur', "Pour la personne qui installe et règle le site, et pour les développeurs : paramètres, installation, exploitation, intégrations, extensions et code.", ['fr/3_administrateur/*.md']),
        ],
        'alias': {},
    },
    'rascol': {
        'out': 'NovaCreneaux - Lycee Louis Rascol - Documentation du CDI.pdf',
        'header': 'NovaCreneaux - CDI du lycée Louis Rascol',
        'cover_top': 'LYCÉE LOUIS RASCOL · ALBI · CDI',
        'cover_sub': 'Guide du CDI',
        'cover_desc': "Réserver une place au CDI, gérer le CDI au quotidien et maintenir le site : un guide pour les élèves, "
                      "un guide pour les documentalistes et une partie technique.",
        'parts': [
            (None, None, None, ['rascol/0_intro/*.md']),
            (1, 'Élèves', "Pour les élèves du lycée : se connecter, réserver une place au CDI, annuler, gérer son compte et comprendre les horaires de réservation.", ['rascol/1_eleves/*.md']),
            (2, 'Documentalistes', "Pour les documentalistes, administrateurs du site : configurer le CDI, suivre le planning, gérer les comptes des élèves, régler le site et l'entretenir.",
             ['rascol/2_documentalistes/20_*.md', 'rascol/2_documentalistes/21_*.md', 'rascol/2_documentalistes/22_*.md',
              'fr/3_administrateur/32_parametres.md', 'fr/3_administrateur/34_roles.md', 'fr/3_administrateur/35_exploitation-site.md',
              'rascol/2_documentalistes/26_*.md', 'rascol/2_documentalistes/27_*.md']),
            (3, 'Technique', "Pour la personne qui héberge et maintient le site : installation, serveur, intégrations, extensions, code et historique des versions.",
             ['fr/3_administrateur/30_licence.md', 'fr/3_administrateur/31_installation.md', 'fr/3_administrateur/33_configuration-serveur.md',
              'fr/3_administrateur/36_exploitation-serveur.md', 'fr/3_administrateur/37_integrations.md', 'fr/3_administrateur/38_extensions.md',
              'fr/3_administrateur/39_developpeur.md', 'fr/3_administrateur/40_faq-administrateur.md', 'fr/3_administrateur/41_historique.md',
              'rascol/3_technique/*.md']),
        ],
        'alias': {'guide-personnel': 'guide-documentaliste', 'guide-utilisateur': 'guide-eleve', 'droits-personnel': 'roles'},
    },
}
COMPLETE = os.path.join(DOCS, 'complete')


def chapter_files(ed):
    """[(numéro de partie, chemin, clé)] dans l'ordre du document ; la clé est le nom du fichier sans son numéro (11_regles.md -> regles)."""
    out = []
    for num, name, desc, patterns in ed['parts']:
        for pattern in patterns:
            found = sorted(glob.glob(os.path.join(COMPLETE, pattern)))
            if not found:
                raise SystemExit('Aucun chapitre pour %s' % pattern)
            for path in found:
                out.append((num, path, re.sub(r'^\d+_', '', os.path.basename(path)[:-3])))
    return out


GENERATED = None


def chapters(ed):
    """Suite d'éléments ('part', numéro, titre, description) et ('chapter', partie, markdown), chapitres numérotés en continu."""
    global GENERATED
    if GENERATED is None:
        GENERATED = generated()
    g = dict(GENERATED)
    files = chapter_files(ed)
    for n, (_, _, key) in enumerate(files, 1):
        g['n ' + key] = str(n)  # renvoi « chapitre [[n regles]] »
    for alias, key in ed['alias'].items():
        g.setdefault('n ' + alias, g['n ' + key])
    out, current = [], 'debut'
    for n, (num, path, key) in enumerate(files, 1):
        if num != current:
            current = num
            part = next(p for p in ed['parts'] if p[0] == num)
            if num is not None:
                out.append(('part', part[0], part[1], part[2]))
        md = fill(open(path, encoding='utf-8').read().strip(), g)
        title = re.match(r'# (.*)', md).group(1)
        out.append(('chapter', num, re.sub(r'^# .*', '# %d. %s' % (n, title), md, count=1)))
    return out


# ----------------------------------------------------------------------------- HTML

MARK = '<span class="mk">§§%d§§</span>'


ACCENT = '#d9480f'  # couleur unique des annotations


def image_size(name):
    import pymupdf
    pix = pymupdf.Pixmap(os.path.join(IMAGES, name))
    return pix.width, pix.height


def annotated_figure(spec):
    """Capture annotée : bloc ```annoter (YAML) -> image + SVG (cadres, cercles, flèches, repères numérotés) + légende.

    image: planning.png
    legende: Planning du jour
    marques:            # coordonnées en pixels de l'image
      - {n: 1, cadre: [x, y, largeur, hauteur], texte: "..."}
      - {n: 2, rond: [cx, cy, rayon], texte: "..."}
      - {n: 3, fleche: [x1, y1, x2, y2], texte: "..."}   # le repère est au départ de la flèche
      - {n: 4, cadre: [...], bulle: [x, y], texte: "..."}  # position du repère imposée
    """
    import yaml
    d = yaml.safe_load(spec)
    w, h = image_size(d['image'])
    k = w / 1280.0
    sw, r = 4 * k, 19 * k
    shapes, badges, legend = [], [], []
    for m in d['marques']:
        n = m['n']
        if 'masque' in m:  # donnée personnelle masquée (adresse IP, e-mail...)
            x, y, cw, ch = m['masque']
            shapes.append('<rect x="%s" y="%s" width="%s" height="%s" fill="#c9ced6"/>' % (x, y, cw, ch))
            if 'texte' not in m:
                continue
            bx, by = m['bulle'] if isinstance(m.get('bulle'), list) else [x - r * 0.4, y - r * 0.4]
        elif 'cadre' in m:
            x, y, cw, ch = m['cadre']
            shapes.append('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="none" stroke="#fff" stroke-width="%s"/>'
                          '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="none" stroke="%s" stroke-width="%s"/>'
                          % (x, y, cw, ch, 8 * k, sw * 2.2, x, y, cw, ch, 8 * k, ACCENT, sw))
            bx, by = m['bulle'] if isinstance(m.get('bulle'), list) else [x - r * 0.4, y - r * 0.4]
        elif 'rond' in m:
            cx, cy, rr = m['rond']
            shapes.append('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="#fff" stroke-width="%s"/>'
                          '<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="%s"/>'
                          % (cx, cy, rr, sw * 2.2, cx, cy, rr, ACCENT, sw))
            bx, by = m['bulle'] if isinstance(m.get('bulle'), list) else [cx + rr * 0.75 + r * 0.6, cy - rr * 0.75 - r * 0.6]
        else:
            x1, y1, x2, y2 = m['fleche']
            shapes.append('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="#fff" stroke-width="%s" stroke-linecap="round"/>'
                          '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" stroke-linecap="round" marker-end="url(#fl)"/>'
                          % (x1, y1, x2, y2, sw * 2.4, x1, y1, x2, y2, ACCENT, sw * 1.2))
            bx, by = m['bulle'] if isinstance(m.get('bulle'), list) else [x1, y1]
        if m.get('bulle', '') == 'aucune':  # forme supplémentaire d'un repère déjà légendé
            continue
        badges.append('<circle cx="%s" cy="%s" r="%s" fill="%s" stroke="#fff" stroke-width="%s"/>'
                      '<text x="%s" y="%s" font-family="Helvetica, Arial, sans-serif" font-size="%s" font-weight="700" fill="#fff" text-anchor="middle">%s</text>'
                      % (bx, by, r, ACCENT, 3 * k, bx, by + r * 0.38, r * 1.05, n))
        legend.append('<li><span class="nb">%s</span>%s</li>' % (n, html.escape(m['texte'])))
    svg = ('<svg viewBox="0 0 %d %d" preserveAspectRatio="none"><defs><marker id="fl" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto">'
           '<path d="M0,0 L10,5 L0,10 z" fill="%s"/></marker></defs>%s%s</svg>') % (w, h, ACCENT, ''.join(shapes), ''.join(badges))
    return ('<figure class="annot"><div class="shot"><img src="file://%s" alt="%s">%s</div><figcaption>%s</figcaption><ol class="legend">%s</ol></figure>'
            % (os.path.join(IMAGES, d['image']), html.escape(d['legende']), svg, html.escape(d['legende']), ''.join(legend)))


def to_html(md):
    blocks = []

    def keep(m):
        blocks.append(annotated_figure(m.group(1)))
        return '\n\nANNOTATION%dFIN\n\n' % (len(blocks) - 1)
    md = re.sub(r'```annoter\n(.*?)```', keep, md, flags=re.S)
    r = subprocess.run(['pandoc', '-f', 'markdown+pipe_tables+backtick_code_blocks-raw_html-smart-auto_identifiers-implicit_figures', '-t', 'html5', '--wrap=none'],
                       input=md, capture_output=True, text=True, check=True)
    frag = re.sub(r'<colgroup>.*?</colgroup>', '', r.stdout, flags=re.S)  # largeurs de colonnes : calculées par le navigateur
    # images : chemin absolu, légende sous l'image
    frag = re.sub(r'<p><img src="images/([^"]+)" alt="([^"]*)" /></p>',
                  lambda m: '<figure><img src="file://%s" alt="%s"><figcaption>%s</figcaption></figure>' % (os.path.join(IMAGES, m.group(1)), m.group(2), m.group(2)), frag)
    frag = re.sub(r'<p>ANNOTATION(\d+)FIN</p>', lambda m: blocks[int(m.group(1))], frag)
    return frag


def mark_headings(frag, entries, shift=0):
    def sub(m):
        lvl = int(m.group(1)) + shift
        title = html.unescape(re.sub(r'<[^>]+>', '', m.group(2))).strip()
        idx = len(entries)
        entries.append((lvl, title))
        return '<h%d id="h%d">%s%s</h%d>' % (lvl - shift, idx, MARK % idx, m.group(2), lvl - shift)
    return re.sub(r'<h([12])(?:\s[^>]*)?>(.*?)</h\1>', sub, frag, flags=re.S)


CSS = """
@page { size: A4; margin: 22mm 18mm 20mm 18mm; }
@page cover { size: A4; margin: 0; }
* { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
html { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #1f2328; background: #fff; }
body { margin: 0; background: #fff; }
.mk { position: absolute; font-size: 2px; color: transparent; }
h1 { font-size: 20pt; font-weight: 700; color: #111827; border-bottom: 1.5px solid #111827; padding-bottom: 3px; margin: 1.8em 0 .7em; page-break-after: avoid; }
h2 { font-size: 13.5pt; font-weight: 700; color: #111827; margin: 1.5em 0 .4em; page-break-after: avoid; }
h3 { font-size: 11pt; font-weight: 700; color: #374151; margin: 1.2em 0 .3em; page-break-after: avoid; }
p { margin: .5em 0; }
li { margin: .15em 0; }
strong { color: #111827; }
code { font-family: Menlo, 'Courier New', monospace; font-size: 8.4pt; background: #f3f4f6; padding: 0 3px; border-radius: 2px; overflow-wrap: anywhere; }
pre { background: #f6f7f9; border: 1px solid #e3e5e8; padding: 7px 9px; border-radius: 3px; white-space: pre-wrap; overflow-wrap: anywhere; page-break-inside: avoid; }
pre code { background: none; padding: 0; font-size: 8.2pt; }
table { border-collapse: collapse; width: 100%; margin: .7em 0 1em; font-size: 8.6pt; }
th, td { border-bottom: 1px solid #d5d8dd; padding: 4px 6px; vertical-align: top; text-align: left; overflow-wrap: break-word; }
td code { overflow-wrap: break-word; word-break: normal; }
th { border-top: 1.2px solid #6b7280; border-bottom: 1.2px solid #6b7280; background: #f3f4f6; font-weight: 700; }
tr { page-break-inside: avoid; }
ul, ol { margin: .4em 0; padding-left: 1.4em; }
a { color: inherit; text-decoration: none; }
figure { margin: .8em 0 1.1em; page-break-inside: avoid; text-align: center; }
figure img { max-width: 100%; max-height: 105mm; border: 1px solid #d5d8dd; }
figcaption { font-size: 8.4pt; color: #555c66; margin-top: 2mm; font-style: italic; }
figure.annot { page-break-inside: avoid; }
blockquote { margin: .9em 0; padding: .5mm 0 .5mm 4.5mm; border-left: 2.5px solid #6b7280; color: #1f2328; page-break-inside: avoid; }
blockquote p { margin: .35em 0; }
blockquote ol, blockquote ul { margin: .3em 0; }
figure.annot .shot { position: relative; display: inline-block; max-width: 100%; line-height: 0; }
figure.annot .shot img { display: block; max-width: 100%; max-height: 118mm; }
figure.annot .shot svg { position: absolute; left: 0; top: 0; width: 100%; height: 100%; }
ol.legend { list-style: none; text-align: left; margin: 2.5mm 0 0; padding: 0; font-size: 8.8pt; columns: 2; column-gap: 8mm; }
ol.legend li { margin: 0 0 1.4mm; break-inside: avoid; display: flex; gap: 2mm; align-items: baseline; }
ol.legend .nb { flex: none; display: inline-block; width: 4.6mm; height: 4.6mm; line-height: 4.6mm; border-radius: 50%; background: #d9480f; color: #fff; font-weight: 700; font-size: 7.6pt; text-align: center; }
.cover { page: cover; width: 210mm; height: 297mm; position: relative; background: #fff; page-break-after: always; }
.cover .top { position: absolute; left: 24mm; right: 24mm; top: 30mm; border-top: 2px solid #111827; padding-top: 4mm;
  font-size: 8.5pt; letter-spacing: 2.5px; color: #4b5563; }
.cover .title { position: absolute; left: 24mm; top: 92mm; font-size: 46pt; font-weight: 800; color: #111827; letter-spacing: -.5px; }
.cover .sub { position: absolute; left: 24mm; top: 116mm; font-size: 17pt; color: #374151; }
.cover .desc { position: absolute; left: 24mm; right: 40mm; top: 132mm; font-size: 10.5pt; color: #4b5563; line-height: 1.55; }
.cover .meta { position: absolute; left: 24mm; right: 24mm; bottom: 30mm; border-top: 1px solid #9ca3af; padding-top: 4mm;
  display: flex; justify-content: space-between; font-size: 9pt; color: #374151; }
.cover .meta b { color: #111827; }
.toc-title { font-size: 20pt; font-weight: 700; color: #111827; border-bottom: 1.5px solid #111827; padding-bottom: 3px; margin: 0 0 6mm; }
.toc { font-size: 9.6pt; page-break-after: always; }
.toc a { display: block; }
.toc a div { display: flex; align-items: baseline; gap: 2mm; padding: .45mm 0; }
.toc .dots { flex: 1; border-bottom: 1px dotted #9ca3af; transform: translateY(-1.2mm); }
.toc .l1 { font-weight: 700; color: #111827; margin-top: 2.6mm; }
.toc .l2 { padding-left: 6mm; color: #374151; font-size: 9pt; }
.chapters > h1:first-child { margin-top: 0; }
.part { page-break-before: always; page-break-after: always; height: 235mm; position: relative; }
.part .pn { position: absolute; top: 78mm; left: 0; font-size: 10pt; letter-spacing: 3px; color: #4b5563; border-top: 2px solid #111827; padding-top: 4mm; width: 100%; }
.part .pt { position: absolute; top: 92mm; left: 0; font-size: 34pt; font-weight: 800; color: #111827; }
.part .pd { position: absolute; top: 112mm; left: 0; right: 30mm; font-size: 11pt; color: #4b5563; line-height: 1.55; }
.part + h1 { margin-top: 0; }
.toc .l0 { font-weight: 800; color: #111827; margin-top: 4.5mm; font-size: 10.8pt; border-top: 1px solid #d5d8dd; padding-top: 1.5mm; }
"""


def cover(ed):
    return ('<div class="cover"><div class="top">%s</div>'
            '<div class="title">NovaCreneaux</div><div class="sub">%s</div><div class="desc">%s</div>'
            '<div class="meta"><span><b>Version %s</b></span><span>Licence MIT</span><span>%s</span></div></div>') % (
        html.escape(ed['cover_top']), html.escape(ed['cover_sub']), html.escape(ed['cover_desc']), html.escape(VERSION), date_fr())


def part_page(num, name, desc, idx):
    return ('<div class="part" id="h%d">%s<div class="pn">PARTIE %d</div><div class="pt">%s</div><div class="pd">%s</div></div>'
            % (idx, MARK % idx, num, html.escape(name), html.escape(desc)))


def build_html(ed, pages=None):
    entries = []  # (niveau du signet, titre, partie)
    body = []
    part = None
    for item in chapters(ed):
        if item[0] == 'part':
            part = 'Partie %d - %s' % (item[1], item[2])
            idx = len(entries)
            entries.append((1, part, part))
            body.append(part_page(item[1], item[2], item[3], idx))
        else:
            start = len(entries)
            body.append(mark_headings(to_html(item[2]), entries, 0 if part is None else 1))
            for k in range(start, len(entries)):
                entries[k] = entries[k] + (part,)
    toc = ['<div class="toc-title">Sommaire</div><div class="toc">']
    for idx, (lvl, title, _) in enumerate(entries):
        cls = 'l0' if lvl == 1 and title.startswith('Partie ') else 'l1' if (lvl == 2 or (lvl == 1)) else 'l2'
        if entries[idx][2] is None:
            cls = 'l1' if lvl == 1 else 'l2'
        num = str(pages[idx]) if pages and idx in pages else '000'
        toc.append('<a href="#h%d"><div class="%s"><span>%s</span><span class="dots"></span><span>%s</span></div></a>' % (idx, cls, html.escape(title), num))
    toc.append('</div>')
    page = ('<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>NovaCreneaux - Documentation complète</title><style>%s</style></head>'
            '<body>%s%s<div class="chapters">%s</div></body></html>') % (CSS, cover(ed), '\n'.join(toc), '\n'.join(body))
    return page, entries


def render_pdf(page_html, pdf):
    with tempfile.TemporaryDirectory() as tmp:
        f = os.path.join(tmp, 'doc.html')
        open(f, 'w', encoding='utf-8').write(page_html)
        cmd = [BROWSER, '--headless=new', '--disable-gpu', '--no-sandbox', '--no-pdf-header-footer', '--allow-file-access-from-files',
               '--print-to-pdf=' + pdf, 'file://' + f]
        for attempt in range(3):  # Brave échoue parfois au lancement (code 2) : nouvel essai
            if subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0:
                return
        raise SystemExit('Brave n\'a pas produit le PDF après 3 essais.')


def locate(pdf):
    import pymupdf
    doc = pymupdf.open(pdf)
    pages = {}
    for pn in range(doc.page_count):
        for m in re.finditer(r'§§(\d+)§§', re.sub(r'\s+', '', doc[pn].get_text())):
            pages.setdefault(int(m.group(1)), pn + 1)
    doc.close()
    return pages


def finish(pdf, entries, pages, ed):
    import pymupdf
    doc = pymupdf.open(pdf)
    doc.set_toc([[lvl, title, pages[idx]] for idx, (lvl, title, _) in enumerate(entries) if idx in pages])
    doc.set_metadata({'title': 'NovaCreneaux - Documentation complète', 'author': 'NovaCreneaux', 'subject': 'Version ' + VERSION,
                      'creator': 'docs/build/build_complete.py (pandoc + Brave + PyMuPDF)'})
    # partie de chaque page : celle du dernier titre placé avant ou sur la page
    starts = sorted((pages[i], e[2]) for i, e in enumerate(entries) if i in pages)
    part_pages = {pages[i] for i, e in enumerate(entries) if i in pages and e[0] == 1 and e[1] == e[2]}
    grey = (0.33, 0.36, 0.40)
    for pn in range(1, doc.page_count):  # pas d'en-tête sur la couverture
        page = doc[pn]
        w, h = page.rect.width, page.rect.height
        num = str(pn + 1)
        page.insert_text(((w - pymupdf.get_text_length(num, fontname='helv', fontsize=8.5)) / 2, h - 28), num, fontsize=8.5, fontname='helv', color=grey)
        if pn + 1 in part_pages:
            continue  # page de garde de partie : numéro seul
        part = None
        for p, name in starts:
            if p <= pn + 1:
                part = name
        left = ed['header'] + ('  |  ' + part if part else '')
        page.insert_text((51, 38), left, fontsize=8, fontname='helv', color=grey)
        label = 'Version ' + VERSION
        page.insert_text((w - 51 - pymupdf.get_text_length(label, fontname='helv', fontsize=8), 38), label, fontsize=8, fontname='helv', color=grey)
        page.draw_line((51, 43), (w - 51, 43), color=(0.75, 0.77, 0.80), width=0.5)
    doc.save(pdf + '.tmp', garbage=3, deflate=True)
    doc.close()
    os.replace(pdf + '.tmp', pdf)


def build(name):
    ed = EDITIONS[name]
    out = os.path.join(DOCS, 'pdf', ed['out'])
    os.makedirs(os.path.dirname(out), exist_ok=True)
    page, entries = build_html(ed)
    render_pdf(page, out)
    pages = locate(out)
    for _ in range(3):  # sommaire paginé ; le gabarit « 000 » garde la même longueur
        page, _ = build_html(ed, pages)
        render_pdf(page, out)
        again = locate(out)
        if again == pages:
            break
        pages = again
    missing = [e[1] for i, e in enumerate(entries) if i not in pages]
    if missing:
        raise SystemExit('Titres introuvables dans le PDF : %s' % missing[:5])
    finish(out, entries, pages, ed)
    import pymupdf
    d = pymupdf.open(out)
    print('%s : %d pages, %d Ko, %d titres' % (out, d.page_count, os.path.getsize(out) // 1024, len(entries)))


def main():
    """Sans argument : toutes les éditions. Sinon : les éditions nommées (generique, rascol)."""
    for name in sys.argv[1:] or list(EDITIONS):
        build(name)


if __name__ == '__main__':
    main()
