#!/usr/bin/env bash

# Validation Phase 2 - qualite statique et hygiene.
# A executer depuis backend/ :
#   ./tools/run-phase2-validation.sh

set -u

errors=0

run_step() {
    local label="$1"
    shift

    printf '\n== %s ==\n' "$label"
    if "$@"; then
        printf '[OK] %s\n' "$label"
    else
        printf '[ERREUR] %s\n' "$label"
        errors=$((errors + 1))
    fi
}

if [ ! -f "composer.json" ] || [ ! -d "core" ] || [ ! -d "src" ]; then
    printf '[ERREUR] Ce script doit etre lance depuis backend/.\n'
    exit 1
fi

if grep -q "phpstan.baseline.neon" phpstan.neon.dist && grep -q "^includes:" phpstan.neon.dist; then
    printf '[OK] Configuration PHPStan avec baseline incluse.\n'
else
    printf '[ERREUR] Configuration PHPStan attendue : includes -> phpstan.baseline.neon.\n'
    errors=$((errors + 1))
fi

run_step "PHPStan" composer phpstan
run_step "PHPCS" composer phpcs
run_step "PHPUnit" env COMPOSER_PROCESS_TIMEOUT=1200 composer test

printf '\n== Recapitulatif ==\n'
if [ "$errors" -eq 0 ]; then
    printf '[OK] Validation Phase 2 terminee.\n'
    exit 0
fi

printf '[ERREUR] Validation Phase 2 en echec : %s etape(s) en erreur.\n' "$errors"
exit 1
