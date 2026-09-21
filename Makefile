PROJECT=pop-estate
APP=app
DB=db

up:
	docker compose up -d --build
	@echo "✅ http://localhost:8080/settlements/"

down:
	docker compose down
	@echo "✅ Apagado sin borrar BD (sin -v)"

down-v:
	@echo "⚠️  Esto BORRA db_data y ollama_data (pierdes liquidaciones y modelos)"
	@read -p "Escribe BORRAR para confirmar: " c && [ "$$c" = "BORRAR" ] && docker compose down -v || echo "Cancelado"

# FIX EN CALIENTE - NO PIERDE BD - tu caso actual
fix:
	@echo "🔧 Fix caliente (Entity + Repo + Controller + Twig) sin perder BD"
	rm -rf var/cache/* var/log/*
	docker compose up -d --build
	docker compose exec $(APP) php -l src/Entity/Settlement.php
	docker compose exec $(APP) php -l src/Repository/SettlementRepository.php
	docker compose exec $(APP) php -l src/Controller/SettlementController.php
	docker compose exec $(APP) php bin/console doctrine:schema:update --force --complete 2>/dev/null || docker compose exec $(APP) php bin/console doctrine:schema:update --force
	docker compose exec $(APP) php bin/console cache:clear
	@echo "✅ http://localhost:8080/settlements/ - KPI + buscador + paginador lila"

fix-front:
	rm -rf var/cache/*
	docker compose exec $(APP) php bin/console cache:clear
	@echo "✅ Solo front recargado"

logs:
	docker compose logs $(APP) --tail=100 -f

sh:
	docker compose exec $(APP) bash

ps:
	docker compose ps

db:
	docker compose exec $(DB) psql -U pop -d pop_estate -c "SELECT id, estado, total, observacion FROM settlement ORDER BY id DESC LIMIT 10;"

lint:
	php -l src/Entity/Settlement.php && php -l src/Repository/SettlementRepository.php && php -l src/Controller/SettlementController.php && echo "✅ 3 OK"

reset-db:
	@echo "⚠️  Reset BD completo (pierdes #140-149)"
	@read -p "Escribe RESET: " c && [ "$$c" = "RESET" ] && docker compose down -v && docker compose up -d --build && sleep 15 && docker compose exec $(APP) php bin/console doctrine:migrations:migrate --no-interaction && docker compose exec $(APP) php bin/console doctrine:fixtures:load --no-interaction || echo "Cancelado"
