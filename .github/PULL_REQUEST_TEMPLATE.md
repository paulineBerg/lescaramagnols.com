<!-- Pull Request Template -->

## Description
Short description of the change and why it is needed.

## Checklist
- [ ] I ran backend tests: `composer test --working-dir=backend` or `vendor/bin/phpunit`
- [ ] I ran frontend tests/build: `cd frontend && npm run test:run && npm run build`
- [ ] I followed coding conventions (used `t()` for translations, `env()`/`app_config()` where relevant)
- [ ] I updated or added translation keys in `backend/lang/*` if UI text changed
- [ ] I added or updated template files in `backend/templates/pages/` when adding pages
- [ ] I did NOT commit `backend/.env` or other secrets

## Notes for reviewer
Any special instructions to test this change locally (ports, env vars, seeds).
