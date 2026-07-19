# SEO technique

## Portee

Ce document consolide:
- la generation JSON-LD
- les regles canonical/sitemap/RSS
- la gestion des images de partage Open Graph / Twitter

## Source canonique JSON-LD

- generation centralisee: `backend/src/Seo/StructuredDataBuilder.php`
- rendu head: `backend/templates/partials/scripts_head.php`

Regles:
- canonical HTML = reference de page
- aucune URL machine (`canonical`, JSON-LD, sitemap, RSS) ne doit contenir `#`
- le JSON-LD doit decrire uniquement du contenu visible et stable
- ne pas reinjecter de schema global statique via `head_metadata_html`

## Images de partage social

Priorite de resolution:
1. article ouvert (`open_article`) si present
2. `meta.image` de page/article/hub
3. `shared_media[0]` (pages dynamiques)
4. image representative liee
5. image de contenu article (dernier recours)
6. fallback global du site uniquement si aucune image editoriale

Sortie attendue quand image resolue:
- `og:image`
- `og:image:secure_url`
- `og:image:alt` (si disponible)
- `twitter:image`
- `twitter:card`
- dimensions/type OG quand connus

## Bouton de partage public

- partage `window.location.href` (incluant `open_article` si actif)
- utilise `navigator.share` si disponible, sinon menu local
- aucun script tiers social charge cote client

## Verification locale

```bash
cd backend && ./vendor/bin/phpunit tests/Seo/StructuredDataBuilderTest.php tests/ScriptsHeadPartialTest.php tests/SitemapServiceTest.php tests/RssFeedServiceTest.php
curl -s http://127.0.0.1:8099/association?open_article=article-attache | rg 'canonical|application/ld\+json|#attached-article'
curl -s http://127.0.0.1:8099/sitemap.xml | rg '#'
```

## References implementation

- `backend/templates/partials/scripts_head.php`
- `backend/templates/pages/dynamic.php`
- `backend/templates/pages/blog/article.php`
- `backend/templates/pages/blog/index.php`
- `backend/templates/partials/menus_header.php`
- `frontend/src/js/share.ts`
- `backend/tests/ScriptsHeadPartialTest.php`
