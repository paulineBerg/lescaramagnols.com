# Guide d'Accès aux Fichiers pour les Agents

**Contexte** : Le projet Caramagnols est hébergé sous WSL2 (`\\wsl.localhost\Ubuntu\home\surfacepro8\www\caramagnols`) mais accessible depuis Windows.

## Règles d'Accès aux Fichiers

### 1. Chemins Relatifs (Recommandé)

**TOUJOURS** utiliser des chemins **relatifs** depuis la racine du projet :

```
docs/roadmap/optimisation-2026-07.md
backend/src/PrivatePortal/Http/PrivatePortalController.php
backend/phpcs.xml
frontend/src/js/main.ts
backend/tests/PrivatePortalPhaseCoverageTest.php
```

Ces chemins fonctionnent avec tous les outils (`read_file`, `edit`, `grep`, `write_file`, `bash`).

### 2. Chemins Absolus Windows (À éviter)

Ne **JAMAIS** utiliser :
```
\\wsl.localhost\Ubuntu\home\surfacepro8\www\caramagnols\docs\roadmap\optimisation-2026-07.md
```

Problèmes :
- Double backslash nécessaire en Windows
- Peut causer des erreurs de parsing
- Ne fonctionne pas avec tous les outils

### 3. Outils et Accès

#### `read_file`
```
Chemin relatif: docs/roadmap/optimisation-2026-07.md
```

#### `edit`
```
Chemin relatif: backend/phpcs.xml
```

#### `grep`
```
Path: backend/src (ou backend/core)
```

#### `write_file`
```
Chemin relatif: backend/tools/new_script.php
```

#### `bash` (Windows CMD)
```bash
# Utiliser dir au lieu de ls
dir backend\src\PrivatePortal\Http\

# Utiliser del au lieu de rm
del frontend\src\js\main.js

# Éviter les pipes Unix (|, grep, head, tail)
# Utiliser findstr pour rechercher
dir /s /b backend\src\*.php | findstr "PrivatePortal"
```

### 4. Commandes Windows vs Unix

| Action | Windows CMD | WSL/Unix |
|--------|-------------|----------|
| Lister fichiers | `dir` | `ls -la` |
| Lister récursif | `dir /s` | `find . -name "*.php"` |
| Supprimer fichier | `del fichier.txt` | `rm fichier.txt` |
| Supprimer dossier | `rmdir /s /q dossier` | `rm -rf dossier` |
| Rechercher texte | `findstr "texte" fichier.txt` | `grep "texte" fichier.txt` |
| Compter lignes | `find /c "texte" fichier.txt` | `grep -c "texte" fichier.txt` |

### 5. Structure du Projet

```
caramagnols/
├── backend/
│   ├── src/
│   │   ├── PrivatePortal/      # Socle (ne pas ajouter de logique métier)
│   │   │   └── Http/
│   │   │       └── PrivatePortalController.php
│   │   ├── PrivateApps/        # Modules métier
│   │   │   ├── Documents/
│   │   │   ├── BlocNote/
│   │   │   ├── TaxDeclarationHelper/
│   │   │   └── RealEstateRental/
│   │   ├── Admin/
│   │   └── Http/
│   │       └── FrontController.php
│   ├── core/                   # Code procédural legacy
│   │   ├── tools/              # Scripts CLI
│   │   └── router.php          # Routage legacy
│   ├── config/
│   ├── templates/
│   │   ├── admin/
│   │   └── partials/
│   ├── tests/
│   ├── composer.json
│   └── phpstan.neon.dist
├── frontend/
│   ├── src/
│   │   ├── js/
│   │   │   ├── main.ts         # Entrée Vite (actuelle)
│   │   │   ├── menus.ts
│   │   │   └── i18n.ts
│   │   └── scss/
│   └── vite.config.mjs
└── docs/
    ├── roadmap/
    │   └── optimisation-2026-07.md
    └── AGENTS.md
```

### 6. Bonnes Pratiques

✅ **FAIRE** :
- Utiliser des chemins relatifs depuis la racine
- Utiliser `/` comme séparateur dans les chemins
- Vérifier l'existence avec `dir` avant de lire/éditer
- Utiliser `grep` pour rechercher dans le code

❌ **NE PAS FAIRE** :
- Utiliser des chemins absolus Windows avec `\`
- Utiliser `ls`, `cat`, `grep` (commandes Unix) directement dans bash
- Supposer que `composer` est disponible dans l'environnement Windows
- Oublier de vérifier les permissions avant de supprimer

### 7. Exemples Concrets

**Rechercher toutes les occurrences de ->query() :**
```
grep --pattern "->query\(" --path "backend/src"
```

**Lire un fichier :**
```
read_file --file_path "backend/phpcs.xml"
```

**Éditer un fichier :**
```
edit --file_path "backend/phpcs.xml" --old_string "<exclude-pattern>core/*</exclude-pattern>" --new_string ""
```

**Supprimer un fichier :**
```
bash --command "del frontend\src\js\main.js"
```

**Créer un fichier :**
```
write_file --file_path "backend/tools/audit_sql.php" --content "<?php ..."
```

### 8. Environnement Spécifique

- **Système** : Windows 10/11 avec WSL2
- **Shell** : `C:\Windows\system32\cmd.exe`
- **Projet** : `\\wsl.localhost\Ubuntu\home\surfacepro8\www\caramagnols`
- **Working Directory** : Déjà positionné sur le projet
- **PHP** : Disponible via WSL, pas directement dans Windows CMD
- **Composer** : Doit être exécuté via WSL ou en se positionnant dans backend/

### 9. Accès WSL Direct

Si besoin d'exécuter des commandes Unix, utiliser :
```bash
wsl cd /home/surfacepro8/www/caramagnols && ls -la
wsl cd /home/surfacepro8/www/caramagnols && composer test
```

Mais préférer les outils natifs (read_file, edit, grep) qui fonctionnent directement.
