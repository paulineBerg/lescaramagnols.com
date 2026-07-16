# Bootstrap Et I18n Backend

Date : 2026-03-19

Ce document décrit le socle HTTP backend après la phase de convergence PHP legacy / couche moderne.

Référence complémentaire :
- `docs/backend/public-entrypoints.md`
- `../consolidation-lot-d.md`

## Objectif

Garantir un seul chemin d'initialisation pour :
- la configuration
- la sécurité HTTP
- la langue courante
- les traductions PHP

Le front-office reste rendu par PHP. Cette convergence ne transforme pas le site en SPA.

Mise a jour 2026-04-16 :
- dans le cadre du Lot D, le socle `bootstrap + Http + i18n + assets PHP` est traite comme un domaine de consolidation a part, avec ses tests et ses wrappers tracked associes

## Source De Verite

- résolution de langue : `backend/src/I18n/LanguageResolver.php`
- chargement des traductions : `backend/src/I18n/Translator.php`
- source des traductions : `backend/lang/*.php`
- bootstrap legacy compatible : `backend/core/lang_bootstrap.php`

## Flux Recommande

1. `backend/core/bootstrap.php` charge l'autoload Composer, l'environnement, la sécurité, la config, puis l'i18n.
2. `backend/core/lang_bootstrap.php` appelle `bootstrap_language_context()`.
3. `bootstrap_language_context()` :
   - fabrique une `Request` si besoin
   - résout la langue via `LanguageResolver`
   - persiste le cookie `lang`
   - fixe `CURRENT_LANG`
   - hydrate `$GLOBALS['langTranslations']`
4. Les templates PHP utilisent ensuite `CURRENT_LANG` et `t()`.
5. Le runtime frontend (`window.caramagnolsRuntime`) peut embarquer des valeurs i18n frontend critiques (ex: fallback titre YouTube pour consentement) pour éviter les textes métiers en dur côté TypeScript.

## Contrat De Fonctions

- `bootstrap_language_context(?Request $request = null): string`
  - initialise le contexte langue une seule fois par requête
- `load_translations_cached(string $lang): array`
  - charge les traductions via `Translator`
- `translation_file_path(string $lang): string`
  - renvoie le fichier réel utilisé, utile pour ETag et outillage
- `t(string $key): string`
  - lit dans `$langTranslations`
- `translation_key_for_text(string $value, ?array $preferredPrefixes = null): ?string`
  - aide à relier une valeur éditoriale à une clé i18n canonique lorsque c'est possible

## Règles D Evolution

- toute nouvelle logique de résolution de langue doit passer par `LanguageResolver`
- aucun front-controller ne doit recharger lui-même les traductions
- le chargement d'un fichier de langue ne doit plus avoir d'effet de bord HTTP
- les entrées HTTP doivent réutiliser le bootstrap commun
- RSS et l'admin réutilisent désormais ce bootstrap commun via `backend/public/index.php`

## Compatibilite Residuelle

Le front public est maintenant servi depuis le registre éditorial structuré et le bootstrap commun.

Mise a jour 2026-03-21 :
- tests unitaires cibles ajoutes sur `bootstrap_language_context()` dans `backend/tests/BootstrapLanguageContextTest.php` :
  - resolution via query string
  - fallback cookie quand la langue query est invalide
  - idempotence du contexte langue sur un meme cycle requete
