# Procedure - Archivage des documents source

Cette procedure s'applique quand une tache multi-IA est terminee, validee et
que les documents source doivent etre conserves hors du flux de travail courant.

## Objectif

- Conserver les prompts, fichiers de transmission et preuves de validation.
- Eviter de supprimer une source utile.
- Eviter d'archiver des secrets, donnees runtime, documents utilisateurs ou logs
  de production.
- Garder les liens projet valides apres archivage.

## Conditions avant archivage

Executer l'archivage uniquement si toutes les conditions suivantes sont vraies :

- `CURRENT_TASK*.md` indique un etat final (`Termine` ou equivalent).
- Les sections resultat, tests, revue finale et etat sont completees.
- Les validations listees dans `CURRENT_TASK*.md` ont ete executees ou une limite
  explicite est documentee.
- `git status --short` a ete verifie.
- Aucun fichier a archiver n'est un secret, une donnee personnelle, un dump, un
  upload, un document runtime prive ou un log de production.
- Aucun document reference par `AGENTS.md`, un README actif, un script, une
  procedure d'exploitation ou un runbook critique n'est deplace sans mise a jour
  prealable de toutes ses references.

## Destination

Utiliser une archive locale par sujet et par date :

```text
.ai/archives/<sujet>-YYYY-MM-DD/
```

Exemples :

```text
.ai/archives/logging-2026-07-19/
.ai/archives/import-export-2026-07-19/
```

Structure recommandee :

```text
.ai/archives/<sujet>-YYYY-MM-DD/
├── README.md
├── source/
│   ├── CURRENT_TASK*.md
│   ├── prompt-source.md
│   └── prompts-agent/
├── validation/
│   └── validation-summary.md
└── SHA256SUMS
```

## Contenu a archiver

- Fichier de transmission de la tache.
- Prompt source demande par l'utilisateur.
- Prompts operationnels utilises par les agents, si utiles a l'historique.
- Analyse d'architecture ou revue finale.
- Liste des commandes de validation et resultats synthetiques.
- Documentation finale creee pour la tache.

## Exclusions

Ne jamais archiver dans `.ai/archives/` :

- secrets, tokens, mots de passe, cookies, sessions, TOTP, cles API ;
- donnees SQL, dumps, exports, backups ou snapshots ;
- documents utilisateurs, pieces jointes, uploads, fichiers runtime prives ;
- logs de production ou chemins sensibles d'acces ;
- caches, fichiers temporaires, dependances vendor/node_modules ;
- fichiers volumineux non necessaires a la tracabilite.

## Procedure

1. Identifier le sujet et la date.

```bash
ARCHIVE_DIR=".ai/archives/logging-2026-07-19"
mkdir -p "$ARCHIVE_DIR/source" "$ARCHIVE_DIR/validation"
```

2. Copier les documents source dans l'archive.

```bash
cp .ai/CURRENT_TASK.md "$ARCHIVE_DIR/source/CURRENT_TASK*.md"
cp backend/docs/prompt-logs-journalisation-centralisee-complete-projet-webapps-private.md "$ARCHIVE_DIR/source/" 2>/dev/null || true
cp .ai/prompts/CODEX_IMPLEMENTER.md "$ARCHIVE_DIR/source/" 2>/dev/null || true
```

3. **Supprimer les originaux apres verification de l'archive.**

```bash
# Supprimer les fichiers sources ORIGINAUX apres confirmation que l'archive est valide
rm -f .ai/CURRENT_TASK*.md
rm -f backend/docs/prompt-logs-journalisation-centralisee-complete-projet-webapps-private.md
rm -f .ai/prompts/CODEX_IMPLEMENTER.md
```

4. Creer un `README.md` d'archive.

```bash
cat > "$ARCHIVE_DIR/README.md" <<'EOF'
# Archive - <sujet> - YYYY-MM-DD

Archive locale des documents source et validations synthetiques de la tache.

## Contenu

- `source/` : documents source archives (originaux supprimes apres verification).
- `validation/` : synthese des validations executees.
- `SHA256SUMS` : empreintes des fichiers archives.

## Exclusions

Aucun secret, dump, upload, document utilisateur, log de production ou fichier
runtime prive ne doit etre present dans cette archive.

## Note

Les documents originaux ont ete supprimes apres archivage valide.
EOF
```

5. Ajouter une synthese des validations.

```bash
cat > "$ARCHIVE_DIR/validation/validation-summary.md" <<'EOF'
# Validations

Reporter ici les commandes executees et leur resultat, sans recopier de secrets
ni de logs verbeux.
EOF
```

6. Generer les empreintes.

```bash
(cd "$ARCHIVE_DIR" && find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS)
```

7. Verifier l'archive.

```bash
test -d "$ARCHIVE_DIR"
find "$ARCHIVE_DIR" -maxdepth 3 -type f | sort
(cd "$ARCHIVE_DIR" && sha256sum -c SHA256SUMS)
git status --short
```

## Suppression des originaux apres archivage

**Regle : Tous les documents sources sont supprimes apres archivage valide.**

La suppression est executee **apres** :
- copie controlee dans l'archive
- generation et verification du SHA256SUMS
- validation de l'integrite de l'archive

**Exceptions** : Ne pas supprimer un document si :
- il est encore reference par `AGENTS.md`, un README actif, un script, une
  procedure d'exploitation ou un runbook critique ;
- il fait partie des invariants du projet (ex: `backend/docs/RUNBOOK_STORAGE_MIGRATION.md`) ;
- sa suppression necessite une mise a jour prealable de references.

Cas particulier : ne pas supprimer `backend/docs/RUNBOOK_STORAGE_MIGRATION.md`
sans mettre a jour `AGENTS.md` et sans respecter les consignes de stockage
runtime prive.

## Nettoyage

Apres archivage et suppression des originaux :

- ne pas commit automatiquement ;
- signaler les fichiers archives ;
- signaler les fichiers supprimes ;
- signaler les changements hors perimetre visibles dans `git status --short` ;
- verifier qu'aucun fichier critique n'a ete supprime par erreur.
