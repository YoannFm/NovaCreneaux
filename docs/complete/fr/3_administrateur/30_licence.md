# Licence et distribution

## Licence de NovaCreneaux

NovaCreneaux est distribué sous **licence MIT** (fichier `LICENSE` à la racine du projet, copyright 2026 LE BORGNE Yoann).

En pratique, la licence MIT permet d'utiliser, de copier, de modifier et de redistribuer le logiciel, y compris dans un cadre commercial. Une seule condition : conserver la mention de copyright et le texte de la licence dans toutes les copies. Le logiciel est fourni « tel quel », sans garantie.

## Composants fournis avec le logiciel

L'archive contient des composants d'autres auteurs. Chacun garde sa propre licence.

| Composant | Version | Usage dans NovaCreneaux | Licence |
|---|---|---|---|
| Laravel | 13 | Cadriciel de l'application | MIT |
| Laravel Sanctum | 4 | Jetons de l'API | MIT |
| Dompdf | 3 | Export PDF du planning | LGPL 2.1 |
| Bacon QR Code | 3 | QR code de la double authentification, produit sur le serveur | BSD 2 clauses |
| Symfony HTML Sanitizer | 7 | Nettoyage des contenus HTML saisis | MIT |
| TinyMCE | 8.6.0 | Éditeur de texte des pages, messages et conditions | GPL 2 ou plus (clé `gpl`) |
| Swagger UI | 5.34.0 | Page `/api/docs` | Apache 2.0 |
| SortableJS | - | Glisser-déposer (navigation, rôles) | MIT |
| Bootstrap Icons | 1.13.1 | Icônes | MIT |

Les fichiers de licence sont dans les dossiers `public/vendor/` (Swagger UI, SortableJS) et `vendor/` (paquets PHP). Le dossier de TinyMCE ne contient pas de fichier de licence. TinyMCE est chargé avec la clé `gpl`, qui correspond à sa distribution sous licence GPL.

## Distribution

- Chaque version est publiée sur la page « Releases » du dépôt GitHub `YoannFm/NovaCreneaux`, sous forme d'archive `novacreneaux-X.Y.Z.zip` accompagnée de sa signature `novacreneaux-X.Y.Z.zip.sig`.
- L'archive contient les dépendances PHP de production et les fichiers compilés. Il n'y a donc rien à compiler, et Composer comme Node.js sont inutiles sur le serveur.
- La signature est vérifiée par l'outil de mise à jour intégré (chapitre [[n exploitation-site]]). Une archive non signée ou modifiée est refusée.
