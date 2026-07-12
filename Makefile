# Makefile - helper tasks for local development

WORKSPACE_DEPLOY_CMD = REMOTE_HOST=ovh-boutique \
    REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend \
    SITEMAP_BASE_URL=https://preprod.lescaramagnols.com \
    bash backend/tools/deploy-fast.sh --all-changes

.PHONY: help install-backend install-frontend install-git-hooks test-backend test-frontend build-frontend postbuild all

help:
	@echo "Available targets: install-backend, install-frontend, install-git-hooks, test-backend, test-frontend, build-frontend, postbuild, all"

install-backend:
	@cd backend && composer install --no-interaction

install-frontend:
	@cd frontend && npm ci

install-git-hooks:
	@chmod +x .githooks/pre-commit
	@git config --local core.hooksPath .githooks
	@echo "Git hooks installes depuis .githooks"

test-backend:
	@cd backend && composer test || true

test-frontend:
	@cd frontend && npm run test:run || true

build-frontend:
	@cd frontend && npm run build

postbuild:
	@cd frontend && npm run postbuild

all: install-backend install-frontend test-backend test-frontend build-frontend postbuild

# Interface standard workspace
include /home/surfacepro8/www/.infra/tools/make-repo.mk
