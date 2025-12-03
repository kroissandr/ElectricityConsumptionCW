.PHONY: help up down start stop migrate fixtures reset logs clean-migrations

## 🚀 Запустить все контейнеры
up:
	docker-compose up -d

## 🛑 Остановить все контейнеры
down:
	docker-compose down

## 🌐 Запустить Symfony сервер
start:
	symfony server:start

## ⏹️ Остановить Symfony сервер
stop:
	symfony server:stop

## Запуск контейнеров с Symfony сервером
start-up:
	$(MAKE) up && $(MAKE) start

## Выключение контейнеров с Symfony сервером
stop-down:
	$(MAKE) stop && $(MAKE) down

## 📝 Создать и применить миграции
migrate:
	php bin/console make:migration --no-interaction
	php bin/console doctrine:migrations:migrate --no-interaction

## 🎲 Загрузить фикстуры
fixtures:
	php bin/console doctrine:fixtures:load --no-interaction

## 🗑️ Очистить старые миграции
clean-migrations:
	@echo "🗑️  Удаление старых миграций..."
	@if [ -d "migrations" ] && [ -n "$$(ls migrations/Version*.php 2>/dev/null)" ]; then \
		rm -f migrations/Version*.php; \
		echo "✅ Старые миграции удалены"; \
	else \
		echo "ℹ️  Миграций для удаления не найдено"; \
	fi

## ♻️ Полный сброс БД (удалить миграции → удалить БД → создать БД → создать миграции → применить → фикстуры)
reset:
	$(MAKE) clean-migrations
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	$(MAKE) migrate
	$(MAKE) fixtures
	@echo "✅ База данных полностью сброшена с чистыми миграциями!"

## 📜 Показать логи контейнеров
logs:
	docker-compose logs -f

## 📋 Показать все команды
help:
	@echo "Доступные команды:"
	@echo "  make up              - Запустить контейнеры (Docker)"
	@echo "  make down            - Остановить контейнеры"
	@echo "  make start           - Запустить Symfony сервер"
	@echo "  make stop            - Остановить Symfony сервер"
	@echo "  make migrate         - Создать и применить миграции"
	@echo "  make fixtures        - Загрузить демо-данные"
	@echo "  make clean-migrations - Удалить старые миграции"
	@echo "  make reset           - Полный сброс БД с очисткой миграций"
	@echo "  make logs            - Показать логи контейнеров"
	@echo "  make help            - Эта справка"
