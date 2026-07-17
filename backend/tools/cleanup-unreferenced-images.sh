#!/usr/bin/env bash

# Script de nettoyage des images non referencees dans structure/.
#
# Execution :
#   cd /home/surfacepro8/www/caramagnols/backend
#   ./tools/cleanup-unreferenced-images.sh --dry-run
#   ./tools/cleanup-unreferenced-images.sh --execute
#
# Le mode execute cree d'abord un backup horodate dans backend/var/backups/.

set -euo pipefail

# Parse arguments
DRY_RUN=true
for arg in "$@"; do
    case "$arg" in
        --execute) DRY_RUN=false ;;
        --dry-run) DRY_RUN=true ;;
        *) echo "Usage: $0 [--dry-run|--execute]" ; exit 1 ;;
    esac
done

IMAGES_DIR="../frontend/src/assets/images/structure"
BACKUP_DIR="../backend/var/backups/images-$(date +%Y%m%d-%H%M%S)"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo_success() {
    echo -e "${GREEN}✓${NC} $1"
}

echo_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

echo_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

echo_error() {
    echo -e "${RED}✗${NC} $1"
}

if [ ! -d "$IMAGES_DIR" ]; then
    echo_error "Dossier $IMAGES_DIR introuvable"
    exit 1
fi

if [ "$DRY_RUN" = false ]; then
    echo_info "Création d'un backup dans $BACKUP_DIR..."
    mkdir -p "$BACKUP_DIR"
    cp -r "$IMAGES_DIR"/* "$BACKUP_DIR/" 2>/dev/null || true
    echo_success "Backup créé"
fi

echo ""
echo_info "Analyse des fichiers à supprimer..."
echo ""

# Liste des fichiers à supprimer (non référencés dans le code)
# Basé sur l'analyse du 2026-07-17
FILES_TO_REMOVE=(
    "apple.png"
    "apple.webp"
    "piscine.jpg"
    "piscine.webp"
    "la_piscine.jpg"
    "la_piscine.webp"
    "paulineetnoel.jpg"
    "paulineetnoel.webp"
    "btemail.gif"
    "Thumbs.db"
    # Favicons - garder uniquement favicon.ico et favicon-48x48.png
    "favicon-16x16.png"
    "favicon-16x16.webp"
    "favicon-32x32.png"
    "favicon-32x32.webp"
    "favicon-64x64.png"
    "favicon-64x64.webp"
    "favicon-180x180.png"
    "favicon-180x180.webp"
    "favicon-192x192.png"
    "favicon-192x192.webp"
    "favicon-512x512.png"
    "favicon-512x512.webp"
)

# Fichiers à CONSERVER
FILES_TO_KEEP=(
    "banniere.gif"      # Référencé dans pages.json (HTML encodé)
    "banniere.jpg"      # Référencé dans pages.json (OpenGraph)
    "banniere.webp"     # Non référencé mais version moderne
    "favicon.ico"      # Référencé dans scripts_head.php
    "favicon-48x48.png" # Référencé dans plusieurs fichiers
    "logo.png"         # Référencé dans PublicUrlNormalizer.php et StructuredDataBuilder.php
    "logo.webp"        # Fallback dans PublicUrlNormalizer.php
    "logo@480w.webp"   # Version redimensionnée
)

total_size=0
removed_count=0
kept_count=0

for file in "${FILES_TO_REMOVE[@]}"; do
    filepath="$IMAGES_DIR/$file"
    if [ -f "$filepath" ]; then
        size=$(stat -c%s "$filepath" 2>/dev/null || stat -f%z "$filepath" 2>/dev/null || echo 0)
        total_size=$((total_size + size))

        if [ "$DRY_RUN" = true ]; then
            echo_info "[DRY RUN] Supprimerait : $file ($size octets)"
        else
            rm -v "$filepath"
            echo_success "Supprimé : $file ($size octets)"
        fi
        removed_count=$((removed_count + 1))
    fi
done

echo ""
echo_info "Fichiers conservés (ne pas supprimer) :"
for file in "${FILES_TO_KEEP[@]}"; do
    filepath="$IMAGES_DIR/$file"
    if [ -f "$filepath" ]; then
        size=$(stat -c%s "$filepath" 2>/dev/null || stat -f%z "$filepath" 2>/dev/null || echo 0)
        echo_info "  ✓ $file ($size octets)"
        kept_count=$((kept_count + 1))
    else
        echo_warning "  ⚠ $file (INTROUVABLE - peut déjà être supprimé)"
    fi
done

echo ""
echo "=========================================="
echo "RÉSUMÉ"
echo "=========================================="
echo_info "Mode : $( [ "$DRY_RUN" = true ] && echo 'DRY RUN (test)' || echo 'EXÉCUTION' )"
echo_success "Fichiers à supprimer : $removed_count"
echo_info "Espace libéré : $total_size octets ($(echo "scale=2; $total_size/1024" | bc) KB)"
echo_info "Fichiers conservés : $kept_count"
if [ "$DRY_RUN" = false ]; then
    echo_info "Backup : $BACKUP_DIR"
fi
echo ""

if [ "$DRY_RUN" = true ]; then
    echo_warning "Aucun fichier n'a été supprimé (mode dry-run)"
    echo_info "Pour exécuter réellement, utilisez : $0 --execute"
else
    echo_success "Nettoyage terminé !"
fi

exit 0
