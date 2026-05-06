# JSON-LD et URL SEO publiques

Statut : actif  
Date de reference : 2026-05-04

Ce document fixe la regle projet pour les donnees structurees publiques.

## Source canonique

La generation JSON-LD publique est centralisee dans `backend/src/Seo/StructuredDataBuilder.php` et rendue dans le `<head>` par `backend/templates/partials/scripts_head.php`.

Le fallback historique place en bas de page ne doit pas etre reactive. Les templates publics fournissent seulement le contexte deja disponible : titre, description, canonical, langue, image SEO et article de blog ouvert.

## Regles SEO

- La canonical HTML est la source de reference de la page.
- Aucune URL emise dans le JSON-LD, la canonical, le sitemap ou le flux RSS ne doit contenir de fragment `#`.
- Les liens visibles peuvent conserver une ancre si elle sert l'interface, par exemple l'ouverture d'un article rattache dans un accordeon. Cette ancre ne doit pas remonter dans les sorties machine.
- Le JSON-LD ne doit decrire que le contenu visible ou le contexte stable du site.
- Une page standard emet `Organization`, `Person`, `WebSite` et `WebPage`.
- Le hub blog emet une page de type `CollectionPage`.
- Un article de blog ouvert emet en plus `BlogPosting`, avec son URL canonique sans fragment, ses dates, son auteur, ses tags et son image quand elle existe.
- L'image JSON-LD d'une page ou d'un article vient du contexte SEO de la page. Si aucune image dediee n'est resolue, on ne force pas une image globale de contenu.

## Injection globale

Le champ admin `head_metadata_html` reste reserve aux balises globales exceptionnelles (`meta`, `link`, cas JSON-LD tres cible).

Pour eviter les doublons et les fluctuations Search Console :

- ne pas y placer le schema global du site (`Organization`, `WebSite`, `Person`) ;
- ne pas y placer de JSON-LD contenant des URL avec `#` ;
- preferer une evolution de `StructuredDataBuilder` pour toute regle durable.

Au rendu public, les scripts JSON-LD globaux contenant un fragment `#` sont ignores. Les autres balises globales restent conservees.

## Extensions futures

FAQ et avis ne doivent etre ajoutes que lorsque le contenu correspondant est visible dans la page et modelise proprement.

Avant emission :

- ajouter les champs de contexte dans le template ou le service source ;
- ajouter la construction dans `StructuredDataBuilder` ;
- tester le payload avec PHPUnit ;
- verifier une URL reelle dans Google Rich Results Test ;
- verifier que la canonical, le sitemap et le RSS ne contiennent pas d'URL fragmentee.

## Verification locale

Commandes utiles :

```bash
cd backend && ./vendor/bin/phpunit tests/Seo/StructuredDataBuilderTest.php tests/ScriptsHeadPartialTest.php tests/SitemapServiceTest.php tests/RssFeedServiceTest.php
php -l src/Seo/StructuredDataBuilder.php
php -l src/Seo/StructuredDataRenderer.php
php -l src/Seo/SeoUrlNormalizer.php
```

Smoke HTTP recommande :

```bash
curl -s http://127.0.0.1:8099/association?open_article=article-attache | rg 'canonical|application/ld\\+json|#attached-article'
curl -s http://127.0.0.1:8099/sitemap.xml | rg '#'
```
