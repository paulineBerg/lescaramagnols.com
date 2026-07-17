#!/bin/bash
#
# Script de déploiement pour la release 2026-07-17
# Finalisation phases 0-3 et lancement phase 4
#
# Ce script exécute les validations pré-déploiement, puis lance le déploiement
# vers la production via deploy-release.sh
#
# Usage: ./deploy-2026-07-17.sh [--dry-run] [--skip-tests]
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DRY_RUN=false
SKIP_TESTS=false

# Analyser les arguments
for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --skip-tests)
            SKIP_TESTS=true
            ;;
    esac
done

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonctions de log
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Fonction pour exécuter une commande avec dry-run
run_cmd() {
    local cmd="$1"
    local description="$2"
    
    if [ "$DRY_RUN" = true ]; then
        log_warn "[DRY RUN] $description"
        log_warn "  Commande: $cmd"
        return 0
    else
        log_info "$description"
        eval "$cmd"
        return $?
    fi
}

# Vérifications pré-déploiement
echo "========================================"
echo "Déploiement 2026-07-17 - Validations"
echo "========================================"
echo ""

# 1. Vérifier que nous sommes sur la bonne branche
CURRENT_BRANCH=$(cd "$PROJECT_ROOT" && git branch --show-current 2>/dev/null || git rev-parse --abbrev-ref HEAD)
echo "Branche actuelle: $CURRENT_BRANCH"

if [[ "$CURRENT_BRANCH" != "restore-prod-master-20260716" ]]; then
    log_error "La branche actuelle n'est pas restore-prod-master-20260716: $CURRENT_BRANCH"
    echo "Pour déployer, basculez sur la bonne branche:"
    echo "  git checkout restore-prod-master-20260716"
    exit 1
fi

# 2. Vérifier que le working directory est propre
run_cmd "cd '$PROJECT_ROOT' && git status --porcelain" "Vérification de l'état git"
GIT_STATUS=$(cd "$PROJECT_ROOT" && git status --porcelain)

if [ -n "$GIT_STATUS" ]; then
    log_error "Le working directory n'est pas propre:"
    echo "$GIT_STATUS"
    log_error "Commitez ou stashiez vos modifications avant de déployer."
    exit 1
fi

log_info "Working directory est propre"

# 3. Vérifier que les fichiers sensibles ne sont pas dans le commit
log_info "Vérification des fichiers sensibles..."

FORBIDDEN_PATTERNS=(
    "\.env$"
    "\.env\.local$"
    "\.env\.bak"
    "config/db\.php$"
    "config/database\.override\.php$"
    "backend/private/storage/"
)

for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
    # Vérifier dans le dernier commit
    FILES_CHANGED=$(cd "$PROJECT_ROOT" && git show --name-only --pretty=format: HEAD | grep -E "$pattern" || true)
    if [ -n "$FILES_CHANGED" ]; then
        log_error "Fichiers sensibles détectés dans le dernier commit: $FILES_CHANGED"
        exit 1
    fi
done

log_info "Aucun fichier sensible détecté dans le commit"

# 4. Vérifier que les tests de base passent (si non skip)
if [ "$SKIP_TESTS" = false ]; then
    echo ""
    log_info "Exécution des validations de base..."
    
    # Vérifier la syntaxe PHP
    run_cmd "cd '$PROJECT_ROOT/backend' && find src/ -name '*.php' -type f | head -20 | xargs -n 1 php -l" "Vérification syntaxe PHP (échantillon)"
    
    # Vérifier que les nouveaux fichiers existent
    NEW_FILES=(
        "$PROJECT_ROOT/backend/src/PrivateApps/BlocNote/PrivateAppManifest.php"
        "$PROJECT_ROOT/backend/src/PrivateApps/Documents/PrivateAppManifest.php"
        "$PROJECT_ROOT/backend/src/PrivateApps/FamilyDiscussion/PrivateAppManifest.php"
        "$PROJECT_ROOT/backend/src/PrivateApps/TaxDeclarationHelper/PrivateAppManifest.php"
        "$PROJECT_ROOT/backend/src/PrivatePortal/PrivateAppRegistry.php"
        "$PROJECT_ROOT/backend/tests/PrivatePortal/PrivateAppRegistryTest.php"
        "$PROJECT_ROOT/backend/tools/sync-pdf-assets.sh"
    )
    
    for file in "${NEW_FILES[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Fichier manquant: $file"
            exit 1
        fi
    done
    
    log_info "Tous les nouveaux fichiers sont en place"
fi

# 5. Résumé des modifications
echo ""
log_info "Résumé des modifications depuis le dernier déploiement:"
cd "$PROJECT_ROOT" && git log --oneline -5

echo ""
echo "Fichiers modifiés:"
cd "$PROJECT_ROOT" && git show --name-status --pretty=format: HEAD | grep -E "^[AMD]\s" | head -20

# 6. Confirmation avant déploiement
echo ""
echo "========================================"
echo "Prêt pour le déploiement"
echo "========================================"

if [ "$DRY_RUN" = true ]; then
    log_warn "MODE SIMULATION - Le déploiement ne sera pas exécuté"
    echo ""
    echo "Pour déployer réellement, exécutez:"
    echo "  $0"
    exit 0
fi

# Confirmer avec l'utilisateur
read -p "Voulez-vous déployer en production? (yes/NO): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    log_info "Déploiement annulé"
    exit 0
fi

# Exécuter le déploiement
echo ""
log_info "Lancement du déploiement en production..."

# Le script deploy-release.sh devrait être utilisé
if [ -f "$SCRIPT_DIR/deploy-release.sh" ]; then
    run_cmd "'$SCRIPT_DIR/deploy-release.sh'" "Exécution de deploy-release.sh"
else
    log_error "Script de déploiement non trouvé: $SCRIPT_DIR/deploy-release.sh"
    exit 1
fi

log_info "Déploiement terminé avec succès!"

# Vérifications post-déploiement
echo ""
echo "========================================"
echo "Vérifications post-déploiement"
echo "========================================"

echo ""
log_info "Vérifiez manuellement en production:"
log_info "1. La page d'accueil: https://www.lescaramagnols.com/"
log_info "2. L'espace privé: https://www.lescaramagnols.com/espace-private-4h6F1c/login"
log_info "3. L'espace admin: https://www.lescaramagnols.com/espace-admin-7k9m2p"
log_info "4. Exécutez: composer check-security-headers"
log_info "5. Exécutez: composer check-env -- --strict-prod-security"

echo ""
log_info "Déploiement 2026-07-17 terminé!"
