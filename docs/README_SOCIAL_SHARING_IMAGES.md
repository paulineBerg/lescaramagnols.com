# Images de partage social (Open Graph / Twitter)

Objectif
- Garantir que chaque page visible sur `lescaramagnols.com` diffuse une image de partage qui correspond à son contenu, et non l'image globale de la page d'accueil.
- Préserver le flux d'architecture existant (`scripts_head.php` + templates dynamiques).

Règle de priorité appliquée
- 1) article ouvert via `open_article` quand l'URL active pointe une chronique rattachée
- 2) `meta.image` de la page (ou de l'article / hub)
- 3) `shared_media[0]` pour les pages dynamiques (`backend/templates/pages/dynamic.php`)
- 4) image représentative liée (article rattaché dans le cas d'une page, article en premier de la liste sur le hub blog)
- 5) image de contenu de l'article (fallback de dernier recours)
- 6) fallback global (`site.head_metadata_html`) uniquement quand aucune image éditoriale n'est résolue

Sortie HTML attendue
- Quand `pageMetaImage` est défini, le `<head>` doit contenir :
  - `og:image`
  - `og:image:secure_url`
  - `og:image:alt` quand un alt est disponible
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
- `twitter:card` global est aussi neutralisé dans ce cas, car `scripts_head.php` émet la carte adaptée à l'image dédiée.
- Les dimensions attendues restent cibles (idéalement `1200x630`), avec les valeurs réelles quand elles sont disponibles.
- L'alternative par défaut doit rester une image versionnée dans `frontend/src/assets/images/**` (ou un upload éditorial déjà référencé), de préférence en `jpg`.
- Ne pas ajouter une nouvelle image globale de partage pour masquer une absence d'image de page. La correction doit se faire sur la donnée éditoriale ou le fallback dédié.

Bouton public de partage
- Le bouton d'en-tête `Partager` partage l'URL active de `window.location.href`, afin de conserver les paramètres utiles comme `open_article`.
- Le navigateur utilise `navigator.share` quand il est disponible; sinon un menu local propose Facebook, WhatsApp, LinkedIn, X, e-mail et copie du lien.
- Aucun script tiers de réseau social n'est chargé pour ce bouton. Les plateformes externes récupèrent l'image par les balises Open Graph / Twitter du HTML serveur.

Vérification manuelle conseillée
- page à image spécifique :
  - `curl -s https://www.lescaramagnols.com/<route-de-page> | rg -n 'property="og:image"|property="og:image:secure_url"|name="twitter:image"|twitter:image:alt|property="og:image:type"|site.head_metadata_html'`
- page sans image éditoriale :
  - vérifier que le fallback global reste bien présent
- vérifier dans l'HTML final qu'aucune URL de la home (global) n'apparaisse dans les balises OG/Twitter quand une page a sa propre image
- depuis une page publique, cliquer sur `Partager` et vérifier que l'URL proposée correspond à l'URL active affichée par le navigateur

Points de contrôle recommandés
- page/route de test avec `meta.image`
- route sans image spécifique (fallback global attendu)
- page avec `shared_media` uniquement
- article blog avec et sans `featured_image`
- hub blog avec et sans `hubMeta.image`
- page parent avec `?open_article=<slug>` pour vérifier l'image de la chronique ouverte

Référence de code
- `backend/templates/partials/scripts_head.php`
- `backend/templates/pages/dynamic.php`
- `backend/templates/pages/blog/article.php`
- `backend/templates/pages/blog/index.php`
- `backend/templates/partials/menus_header.php`
- `frontend/src/js/share.ts`
- `backend/tests/ScriptsHeadPartialTest.php`
