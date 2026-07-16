# Etat prod OVH - 2026-04-16

## Objet

Trace courte du deploiement effectue sur OVH le `2026-04-16` pour recopie du dernier lot courant, avec verification du perimetre reellement publie.

## Commande executee

Deploiement effectif :

```bash
bash .ops-sync/bin/push-backend-release.sh
```

Mode utilise :

- `deploy-release`
- sync complet de `backend/`
- sync de `vendor/`
- regeneration de `public/sitemap.xml`
- purge du cache runtime distante

## Horodatage utile

Marqueurs observes sur OVH :

- `public/.vite/manifest.json` : `2026-04-16 13:33:30 UTC`
- `public/assets/main.ClR6Xz1r.js` : `2026-04-16 13:33:30 UTC`
- `public/assets/style.CAmrMQVa.css` : `2026-04-16 13:33:30 UTC`
- `public/sitemap.xml` : `2026-04-16 14:04:42 UTC`

## Ce qui a effectivement change en prod

### 1. Assets frontend publies

Les bundles Vite actifs servis en production apres deploiement sont :

- `main.ClR6Xz1r.js`
- `style.CAmrMQVa.css`
- `mer.BHJSPSkI.webp`
- `mer.YsFucqis.jpg`
- `PlageMerSoleil.D2h6OO4T.jpg`
- `PlageMerSoleil.ffFolf4f.webp`
- `St-Tropez.BiwTXx_C.webp`
- `St-Tropez.DiddkOTK.jpg`
- `st-tropez.DfcyEWA8.webp`
- `st-tropez.q0946iJL.jpg`
- `balladeNature.C3KgDM0A.webp`
- `balladeNature.CiZNnbpt.jpg`

### 2. Normalisation des images du lot B appliquee

Constats verifies sur OVH :

- ancien chemin `public/assets/images/autoretro/mercedes/Benz Patent-Motorwagen.jpg` : absent
- nouveau chemin `public/assets/images/autoretro/mercedes/benz_patent_motorwagen.jpg` : present

Le lot B a donc bien remplace les anciens noms d assets par les noms normalises publies depuis la source frontend.

### 3. Nettoyage heritage effectivement publie

Constats verifies sur OVH :

- `templates/pages/site/` : absent
- `public/installsql.php` : absent

Le deploiement a donc embarque aussi la suppression des anciens templates pages et de l ancien point d entree d installation.

### 4. Backend courant recopie au-dela du seul lot B

Comme le script utilise etait `deploy-release`, la prod a recu l etat backend courant complet et non un sous-ensemble strictement limite aux assets du lot B.

Blocs visiblement recopies :

- `backend/data/pages.json`
- `backend/public/.htaccess`
- `backend/public/index.php`
- `backend/public/rss.php`
- `backend/core/**`
- `backend/src/**`
- `backend/templates/**`
- `backend/tools/**`
- `backend/vendor/**`

## Effets de bord constates

Le `deploy-release` a aussi pousse des artefacts locaux qui ne relevaient pas du lot B.

Elements observes en prod apres deploiement :

- `data/logs/access.log`
- `data/logs/content.log`
- `data/logs/security.log`
- `data/snapshots/*.json`
- `var/dev-ssl/`
- `var/phpstan/`
- `var/phpunit/`

Ces repertoires et fichiers ne devraient pas faire partie d un deploiement de production standard. Ils refletent le perimetre actuel du script `backend/tools/deploy-release.sh`, pas seulement le besoin du lot B.

## Controles de cloture

Sorties de fin de script :

- `cache_cleared`
- `autoload_ok`
- `deploy-release completed.`

Verifications HTTP :

- `https://www.lescaramagnols.com/` -> `HTTP/2 200`
- `https://www.lescaramagnols.com/assets/images/autoretro/mercedes/benz_patent_motorwagen.jpg` -> `HTTP/2 200`

Headers constates sur la home :

- `server: OVHcloud`
- `strict-transport-security: max-age=31536000; includeSubDomains; preload`
- `content-security-policy` present

## Conclusion

La prod OVH a bien pris :

- le lot B de normalisation d assets
- les bundles frontend publies associes
- l etat backend courant complet

Point d attention archive :

- le deploiement `release` a egalement embarque des artefacts locaux non cibles (`data/logs`, `data/snapshots`, `var/phpstan`, `var/dev-ssl`, etc.)
- ce point doit etre traite dans le script de deploiement avant le prochain push prod si l objectif est un perimetre strictement produit
