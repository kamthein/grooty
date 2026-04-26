# Grooty — Makefile
PHP = /Applications/MAMP/bin/php/php8.3.30/bin/php
CONSOLE = $(PHP) bin/console

.PHONY: install db migrate reset cc

install:
	composer install
	cp -n .env .env.local || true
	$(MAKE) db
	$(MAKE) migrate
	mkdir -p public/uploads/events/thumbs
	@echo "✓ Prêt. Lance : symfony server:start"

db:
	$(CONSOLE) doctrine:database:create --if-not-exists

migrate:
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

reset:
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(MAKE) db
	$(MAKE) migrate

cc:
	$(CONSOLE) cache:clear

routes:
	$(CONSOLE) debug:router
