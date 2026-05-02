# Images de partage social (Open Graph / Twitter)

Objectif
- Garantir que chaque page visible sur `lescaramagnols.com` diffuse une image de partage qui correspond à son contenu, et non l'image globale de la page d'accueil.
- Préserver le flux d'architecture existant (`scripts_head.php` + templates dynamiques).

Règle de priorité appliquée
- 1) `meta.image` de la page (ou de l'article / hub)
- 2) `shared_media[0]` pour les pages dynamiques (`backend/templates/pages/dynamic.php`)
- 3) image représentative liée (article rattaché dans le cas d'une page, article en premier de la liste sur le hub blog)
- 4) image de contenu de l'article (fallback de dernier recours)
- 5) fallback global (`site.head_metadata_html`) quand aucune image éditoriale n'est résolue

Sortie HTML attendue
- Quand `pageMetaImage` est défini, le `<head>` doit contenir :
  - `og:image`
  - `og:image:secure_url`
  - `twitter:image`
  - `twitter:card`
- Les dimensions/format sont ajoutés quand connus :
  - `og:image:width`
  - `og:image:height`
  - `og:image:type`
- L'attribut `twitter:image:alt` doit être renseigné dès que possible.

Règles d'implémentation
- Les tags sociaux globaux dans `backend/config/site.override.php` (ex. `og:image`, `twitter:image`, etc.) sont gardés pour l'usage par défaut, mais supprimés de l'injection finale quand une image page est disponible.
- `scripts_head.php` neutralise donc les tags concurrents venant de `app_config('site.head_metadata_html')` si `pageMetaImage` est présent.
- Les dimensions attendues restent cibles (idéalement `1200x630`), avec les valeurs réelles quand elles sont disponibles.
- L'alternative par défaut doit rester une image versionnée dans `frontend/src/assets/images/**` (ou un upload éditorial déjà référencé), de préférence en `jpg`.

Vérification manuelle conseillée
- page à image spécifique :
  - `curl -s https://www.lescaramagnols.com/<route-de-page> | rg -n 'property="og:image"|property="og:image:secure_url"|name="twitter:image"|twitter:image:alt|property="og:image:type"|site.head_metadata_html'`
- page sans image éditoriale :
  - vérifier que le fallback global reste bien présent
- vérifier dans l'HTML final qu'aucune URL de la home (global) n'apparaisse dans les balises OG/Twitter quand une page a sa propre image

Points de contrôle recommandés
- page/route de test avec `meta.image`
- route sans image spécifique (fallback global attendu)
- page avec `shared_media` uniquement
- article blog avec et sans `featured_image`
- hub blog avec et sans `hubMeta.image`

Référence de code
- `backend/templates/partials/scripts_head.php`
- `backend/templates/pages/dynamic.php`
- `backend/templates/pages/blog/article.php`
- `backend/templates/pages/blog/index.php`
- `backend/tests/ScriptsHeadPartialTest.php`
