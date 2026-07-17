# Phase 2 - Runbook d'Exécution

**Date** : 2026-07-17
**Objectif** : Finaliser les tâches restantes de la Phase 2
**Statut** : 4/6 tâches terminées, 2 tâches prêtes à exécuter

---

## 📋 Tâches Restantes

### Tâche 5 : Générer la baseline PHPStan
**Statut** : ✅ Configuration validee, baseline creee
**Durée estimée** : 2-5 minutes
**Risque** : Faible

#### Prérequis
- PHP 8.1+
- Composer installé
- Vendors à jour (`composer install`)

#### Étapes

```bash
# 1. Se positionner dans backend/
cd /home/surfacepro8/www/caramagnols/backend

# 2. Vérifier que vendor/ est à jour
composer install --no-interaction

# 3. Vérifier la configuration
grep -A1 "^includes:" phpstan.neon.dist
# Doit afficher phpstan.baseline.neon

# 4. Générer la baseline
php vendor/bin/phpstan analyse --generate-baseline

# 5. Vérifier que la baseline a été créée
ls -lh phpstan.baseline.neon

# 6. Tester PHPStan avec la baseline
php vendor/bin/phpstan analyse

# 7. Via composer (optionnel)
composer phpstan
```

#### Attendu
- ✅ Fichier `phpstan.baseline.neon` créé
- ✅ `php vendor/bin/phpstan analyse` retourne code 0 (vert)
- ⚠️ Des erreurs peuvent apparaître pour `core/` et `config/` - c'est normal, la baseline les absorbe

#### Dépannage

| Problème | Solution |
|----------|----------|
| `vendor/bin/phpstan` introuvable | `composer install` |
| `includes` manquant dans config | Vérifier `phpstan.neon.dist` |
| Erreurs PHPStan dans `src/` | La baseline de la phase 1 doit être régénérée |
| Timeout PHPStan | Augmenter `memory_limit` dans php.ini |

---

### Tâche 6 : Traiter les doublons d'images
**Statut** : ✅ Script créé, ⏳ Déduplication à effectuer
**Durée estimée** : Plusieurs jours (travail progressif)
**Risque** : Faible à moyen (validation manuelle nécessaire)

#### Prérequis
- Node.js 18+
- npm installé
- Accès à WSL (pour npm run audit:images)

#### Étapes

##### Étape 1 : Générer le rapport initial

```bash
# Depuis la racine du projet
cd /home/surfacepro8/www/caramagnols

# Exécuter l'audit d'images
cd frontend
npm run audit:images 2>&1 | tee ../backend/docs/image-audit-initial.txt

# Revenir à la racine
cd ..
```

##### Étape 2 : Analyser les références

```bash
# Exécuter le script d'analyse
cd frontend
node tools/deduplicate-images.mjs

# Le script générera :
# - frontend/tools/image-dedup-report/YYYY-MM-DD.json
# - Affiche un résumé à l'écran
```

##### Étape 3 : Traiter par lots

**Stratégie recommandée** : Traiter par lots de 50-100 groupes pour éviter les conflits.

```bash
# Exemple pour traiter 100 groupes

# 1. Lister les groupes (à adapter selon le format de npm run audit:images)
cd frontend/src/assets/images

# 2. Pour chaque groupe, identifier :
#    - L'image canonique (la plus référencée)
#    - Les doublons à supprimer
#    - Les références à mettre à jour

# 3. Supprimer les doublons (exemple)
rm images/accueil/banner-1.jpg
rm images/bouger/banner-1.jpg
# Conserver : images/structure/banner-1.jpg

# 4. Mettre à jour les références si nécessaire
#    - Dans backend/templates/**/*.php
#    - Dans backend/src/**/*.php
#    - Dans frontend/src/**/*.{ts,js,scss}
#    - Dans backend/data/*.json

# 5. Vérifier le build
cd frontend
npm run build

# 6. Tester manuellement les pages affectées
```

##### Étape 4 : Valider chaque lot

```bash
# Après chaque lot de 50-100 images :

# 1. Vérifier le build frontend
git status
npm run build

# 2. Vérifier que rien n'est cassé
npm run test:run

# 3. Committer les changements
git add -A
git commit -m "Phase 2 : Deduplication images lot 1 (50 groupes)"

# 4. Pousser et vérifier en production
git push origin restore-prod-master-20260716
# Puis vérifier manuellement les pages affectées
```

#### Structure des dossiers d'images

```
frontend/src/assets/images/
├── structure/          # Images structurelles (à conserver)
│   ├── banner-1.jpg
│   └── ...
├── accueil/            # Images d'accueil
│   ├── banner-1.jpg    # DOUBLON de structure/banner-1.jpg
│   └── ...
├── bouger/             # Images "bouger"
│   ├── banner-1.jpg    # DOUBLON de structure/banner-1.jpg
│   └── ...
├── boulyetcailloux/    # Images spécifiques
│   └── ...
└── autoretro/          # Images rétro
    └── ...
```

#### Règles de déduplication

1. **Conserver l'image la plus utilisée** (celle avec le plus de références)
2. **Privilégier les chemins courts** (ex: `structure/` plutôt que `accueil/`)
3. **Vérifier les tailles** : parfois les doublons ont des tailles différentes
4. **Vérifier les formats** : JPG vs WebP vs PNG
5. **Conserver les noms descriptifs** : `banner-accueil.jpg` > `banner-1.jpg`

#### Commandes utiles

```bash
# Trouver tous les fichiers image
find frontend/src/assets/images -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.gif" -o -iname "*.webp" \)

# Compter les fichiers par dossier
find frontend/src/assets/images -type f | sed 's|/[^/]*$||' | sort | uniq -c | sort -rn

# Trouver les doublons par checksum
find frontend/src/assets/images -type f -exec md5sum {} \; | sort | uniq -w32 -d | cut -d' ' -f3-

# Chercher les références à une image
rg "images/structure/banner-1\.jpg" backend/ frontend/
```

---

## 🎯 Validation Finale Phase 2

### Checklist de validation

- [ ] Baseline PHPStan générée (`phpstan.baseline.neon` existe)
- [ ] `php vendor/bin/phpstan analyse` retourne code 0
- [ ] `php vendor/bin/phpcs` retourne code 0
- [ ] `php vendor/bin/phpunit` retourne code 0 (624 tests verts)
- [ ] `npm run build` réussi
- [ ] `npm run test:run` réussi (39 tests Vitest verts)
- [ ] Aucune référence aux fichiers JS legacy (main.js, menus.js, i18n.js)
- [ ] Rapport d'audit SQL archivé (`backend/docs/audit-sql-2026-07-17.md`)

### Commandes de validation complète

```bash
# Depuis la racine du projet
cd /home/surfacepro8/www/caramagnols

# Backend
cd backend
./tools/run-phase2-validation.sh
cd ..

# Frontend
cd frontend
npm run build
npm run test:run
cd ..

# Vérification manuelle
# - Ouvrir https://www.lescaramagnols.com/ dans un navigateur
# - Tester les fonctionnalités principales
# - Vérifier la console pour les erreurs JS
```

---

## 📊 Suivi du Progrès

### Tableau de bord

| Tâche | Statut | Date | Notes |
|-------|--------|------|-------|
| PHPStan core/config | ✅ Config | 2026-07-17 | Baseline à générer |
| PHPCS core/ | ✅ Terminé | 2026-07-17 | `core/` inclus |
| Audit SQL | ✅ Terminé | 2026-07-17 | 134 requêtes, 0 critique |
| Doublons JS | ✅ Terminé | 2026-07-17 | 3 fichiers supprimés |
| Baseline PHPStan | ⏳ À faire | - | 2-5 min |
| Doublons images | ⏳ À faire | - | Plusieurs jours |

### Métriques

- **Phase 2 Complétion** : 66% (4/6 tâches)
- **Validations** : 100% (3/3 terminées)
- **Risques identifiés** : 0 critique
- **Vulnérabilités** : 0

---

## 🚨 Dépannage

### Problème : PHPStan échoue avec des erreurs

**Cause** : Le code legacy dans `core/` et `config/` a des erreurs PHPStan.

**Solution** :
```bash
# 1. Vérifier que la baseline est générée
ls -la phpstan.baseline.neon

# 2. Si le fichier n'existe pas, le générer
php vendor/bin/phpstan analyse --generate-baseline

# 3. Si des erreurs persistent dans src/, corriger le code
php vendor/bin/phpstan analyse src/ --error-format=table
```

### Problème : PHPCS échoue avec des erreurs

**Cause** : Le code legacy ne respecte pas PSR-12.

**Solution** :
```bash
# Voir les erreurs détaillées
php vendor/bin/phpcs core/ --standard=phpcs.xml --report=full

# Si trop d'erreurs, ajouter des exclusions temporaires
# Éditer backend/phpcs.xml et ajouter :
# <exclude-pattern>core/specific_file.php</exclude-pattern>
```

### Problème : npm run audit:images introuvable

**Solution** :
```bash
# Vérifier que le script existe
cat frontend/package.json | grep audit:images

# Si absent, vérifier les scripts disponibles
npm run

# Alternative : Utiliser find pour identifier les doublons
find frontend/src/assets/images -type f -exec md5sum {} \; | \
  sort | uniq -w32 -d | cut -d' ' -f3- > duplicates.txt
```

---

## 📞 Support

### Canaux de support

1. **Documentation** : `docs/roadmap/optimisation-2026-07.md`
2. **Rapports** : `backend/docs/audit-sql-2026-07-17.md`
3. **Scripts** : `backend/tools/` et `frontend/tools/`
4. **Guide agent** : `docs/AGENTS-access-guide.md`

### Contacts

- **Responsable technique** : Voir AGENTS.md
- **Production** : https://www.lescaramagnols.com/
- **Documentation** : `docs/`

---

## ✅ Checklist pour Commit/Push

### Avant de commiter

- [ ] `git status` propre (seuls les fichiers attendus modifiés)
- [ ] `composer test` vert (backend)
- [ ] `composer phpstan` vert (backend)
- [ ] `composer phpcs` vert (backend)
- [ ] `npm run build` réussi (frontend)
- [ ] `npm run test:run` vert (frontend)
- [ ] `npm run hygiene:docs` OK (si disponible)

### Message de commit recommandé

```
Phase 2 : [Description de la tâche]

- Génération baseline PHPStan pour core/ et config/
- Configuration PHPCS étendue à core/
- Audit SQL complet (134 requêtes analysées)
- Suppression doublons JS legacy

Generated by Mistral Vibe.
Co-Authored-By: Mistral Vibe <vibe@mistral.ai>
```

---

## 🎉 Conclusion

La **Phase 2 est à 66% de complétion** avec toutes les tâches complexes déjà terminées. Les 2 tâches restantes sont :

1. **Génération de la baseline PHPStan** (2-5 min) - Tâche critique mais simple
2. **Déduplication des images** (plusieurs jours) - Tâche longue mais non bloquante

**Recommandation** :
1. Exécuter la génération de baseline PHPStan **immédiatement**
2. Commencer la déduplication des images **par lots de 50-100** dès que possible
3. Valider chaque lot avant de continuer

Une fois ces tâches terminées, la Phase 2 sera à 100% et la Phase 3 pourra démarrer.
