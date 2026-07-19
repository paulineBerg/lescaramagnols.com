#!/bin/bash
#
# Script de synchronisation des PDF du dépôt vers le stockage public
# Modèle : sync-editorial-uploads.sh
#
# Ce script copie les PDF du répertoire frontend/src/assets/pdf/ vers backend/public/uploads/pdf/
# et met à jour les références. Les PDF sont exclus du versionnage git via .gitignore.
#
# Usage:
#   ./sync-pdf-assets.sh [--dry-run] [--verbose]
#

set -euo pipefail

# Configuration
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../frontend/src/assets/pdf" && pwd)"
TARGET_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../public/uploads/pdf" && pwd)"
DRY_RUN=false
VERBOSE=false

# Analyser les arguments
for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --verbose)
            VERBOSE=true
            ;;
    esac
done

# Fonction de log
log() {
    if [ "$VERBOSE" = true ]; then
        echo "[INFO] $1"
    fi
}

# Fonction d'erreur
error() {
    echo "[ERROR] $1" >&2
}

# Vérifier que les répertoires existent
if [ ! -d "$SOURCE_DIR" ]; then
    error "Répertoire source non trouvé : $SOURCE_DIR"
    exit 1
fi

if [ ! -d "$TARGET_DIR" ]; then
    if [ "$DRY_RUN" = true ]; then
        log "Créerait le répertoire cible : $TARGET_DIR"
    else
        log "Création du répertoire cible : $TARGET_DIR"
        mkdir -p "$TARGET_DIR"
    fi
fi

# Compter les fichiers à synchroniser
TOTAL_FILES=0
TOTAL_SIZE=0

# Fonction pour copier récursivement
sync_pdfs() {
    local src="$1"
    local dst="$2"

    # Créer le répertoire cible s'il n'existe pas
    local relative_path="${src#$SOURCE_DIR/}"
    local target_path="$dst/$relative_path"

    if [ -d "$src" ]; then
        if [ "$DRY_RUN" = false ]; then
            mkdir -p "$target_path"
        else
            log "Créerait le répertoire : $target_path"
        fi

        # Traiter les sous-répertoires et fichiers
        for item in "$src"/*; do
            if [ -e "$item" ]; then
                sync_pdfs "$item" "$dst"
            fi
        done
    elif [ -f "$src" ]; then
        # C'est un fichier PDF
        if [[ "$src" == *.pdf ]]; then
            local filename="$(basename "$src")"
            local target_file="$target_path/$filename"

            if [ "$DRY_RUN" = true ]; then
                local size=$(stat -c%s "$src" 2>/dev/null || stat -f%z "$src" 2>/dev/null || echo 0)
                TOTAL_FILES=$((TOTAL_FILES + 1))
                TOTAL_SIZE=$((TOTAL_SIZE + size))
                log "Copierait : $src -> $target_file ($size octets)"
            else
                local size=$(stat -c%s "$src" 2>/dev/null || stat -f%z "$src" 2>/dev/null || echo 0)
                TOTAL_FILES=$((TOTAL_FILES + 1))
                TOTAL_SIZE=$((TOTAL_SIZE + size))

                if [ -f "$target_file" ]; then
                    # Comparer les tailles et timestamps
                    local src_size=$(stat -c%s "$src")
                    local dst_size=$(stat -c%s "$target_file")
                    local src_mtime=$(stat -c%Y "$src")
                    local dst_mtime=$(stat -c%Y "$target_file")

                    if [ "$src_size" -eq "$dst_size" ] && [ "$src_mtime" -le "$dst_mtime" ]; then
                        log "Déjà à jour : $filename"
                    else
                        log "Mise à jour : $src -> $target_file"
                        cp -f "$src" "$target_file"
                    fi
                else
                    log "Copie : $src -> $target_file"
                    cp -f "$src" "$target_file"
                fi
            fi
        fi
    fi
}

# Démarrer la synchronisation
echo "Synchronisation des PDF : $SOURCE_DIR -> $TARGET_DIR"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo "MODE SIMULATION - Aucune modification ne sera effectuée"
    echo ""
fi

sync_pdfs "$SOURCE_DIR" "$TARGET_DIR"

# Afficher le résumé
echo ""
echo "=== Résumé ==="
echo "Fichiers à synchroniser : $TOTAL_FILES"
if [ "$TOTAL_SIZE" -gt 1048576 ]; then
    echo "Taille totale : $((TOTAL_SIZE / 1048576)) Mo"
elif [ "$TOTAL_SIZE" -gt 1024 ]; then
    echo "Taille totale : $((TOTAL_SIZE / 1024)) Ko"
else
    echo "Taille totale : $TOTAL_SIZE octets"
fi

if [ "$DRY_RUN" = true ]; then
    echo ""
    echo "Pour exécuter réellement la synchronisation, lancez :"
    echo "  $0"
fi

echo "Fait."
