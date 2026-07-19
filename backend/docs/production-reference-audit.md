# Audit de coherence avec la production

Date de reference : 2026-07-16.

## Principe

La production `https://www.lescaramagnols.com/` est la version maitre. Le depot local doit etre aligne progressivement sur les routes, contenus visibles, assets et protections observees en production.

## Ecarts confirmes

- Route admin : la production sert `/espace-admin-7k9m2p` avec une page de connexion admin. Le depot local renvoie une 404 pour cette URL.
- Securite admin : la production affiche un formulaire avec e-mail, mot de passe, code 2FA TOTP et token CSRF. Le depot local ne contient pas cette route canonique ni ce flux.
- Securite HTTP : la production expose notamment CSP avec nonce, HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP et cookies `Secure`/`HttpOnly`. Le serveur local de developpement n'en reproduit pas l'ensemble.
- Navigation publique : la production contient des entrees visibles non presentes ou non branchees localement, dont Citroen/2CV, plusieurs pages Mercedes detaillees, Sainte-Maxime, Gassin, Grimaud, La Croix-Valmer, Cavalaire-sur-Mer, La Mole, Le Rayol-Canadel-sur-Mer et Blog.
- Routes publiques : la production utilise des URLs plus courtes comme `/auto-retro/...`, `/bouger/...`, `/blog`; le depot local utilise majoritairement `/site/...`.
- Assets et contenus : certains PDF ou images de villages existent localement, mais les pages publiques correspondantes ne sont pas toujours presentes dans `backend/templates/pages`.

## Verifications locales realisees

- `php backend/core/tools/check_env.php --path=backend/.env --env=development` : OK apres correction du script.
- `composer test --working-dir=backend` : OK, avec une depreciation PHPUnit.
- `npm run test:run` depuis `frontend/` : OK.

## Points a traiter en priorite

- Reproduire la route `/espace-admin-7k9m2p` localement avec les controles de securite observes en production.
- Ajouter ou reconnecter les routes publiques absentes en respectant les URLs de production.
- Aligner les menus desktop/mobile sur la production.
- Auditer les en-tetes de securite et les cookies pour que le comportement deploye reste coherent avec la production.

## Sauvegardes locales utiles

- `/home/surfacepro8/backups/caramagnols/prod/editorial-prod-before-cron-center-fix-20260620-192812.json.gz` contient un export editorial prod structure avec `pages`, `navigation`, `blog` et `tiles`.
- `/mnt/c/Users/lesca/OneDrive/Documents/Divers/Developpement/vscode/python/sauvegarde_fichiers caramagnols/sauvegarde/260531_173443/caramagnols` contient une sauvegarde de code beaucoup plus proche de la production que le depot actuel.
- Comparaison ciblee du 2026-07-16 sur `backend/config`, `backend/core`, `backend/src`, `backend/templates`, `backend/tests`, `backend/tools`, `docs`, `frontend/src`, `frontend/tools`, `.github` :
  - depot actuel : 640 fichiers
  - sauvegarde OneDrive 2026-05-31 : 1916 fichiers
  - presents uniquement dans la sauvegarde : 1348 fichiers
  - presents uniquement dans le depot actuel : 72 fichiers
  - communs : 568 fichiers
- Fichiers structurants presents dans la sauvegarde et absents du depot actuel : `backend/core/auth/admin.php`, `backend/core/security.php`, `backend/core/rate_limiter.php`, `backend/core/content/pages_loader.php`, `backend/core/menu_loader.php`, `backend/src/Admin/*`, `backend/src/Blog/*`, `backend/src/Http/LegacyRouteResolver.php`, `backend/config/admin.override.php`, `backend/public/.htaccess`.
- Le routeur de la sauvegarde utilise `LegacyRouteResolver` et les donnees editoriales, alors que le depot actuel mappe seulement les URLs vers des fichiers sous `backend/templates/pages`.
- Ne pas lancer le script de restauration OneDrive directement sur le depot : son `readme.txt` indique qu'il ecrase les fichiers existants et supprime ceux absents de la sauvegarde.
