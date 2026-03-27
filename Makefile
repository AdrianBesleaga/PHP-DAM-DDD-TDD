.PHONY: help install test analyse lint quality up down logs shell token db serve

# ─── Default ──────────────────────────────────────────────────────
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

# ─── Development ─────────────────────────────────────────────────
install: ## Install PHP dependencies
	composer install

serve: ## Start local dev server (no Docker)
	composer serve

db: ## Create/reset SQLite database
	composer db:create

token: ## Generate a test JWT token (usage: make token USER_ID=1)
	php bin/generate-token.php --user-id=$(or $(USER_ID),1)

# ─── Quality ─────────────────────────────────────────────────────
test: ## Run PHPUnit tests
	composer test

analyse: ## Run PHPStan static analysis (level 8)
	composer analyse

lint: ## Check code style (PHP-CS-Fixer)
	composer cs:check

lint-fix: ## Auto-fix code style
	composer cs:fix

quality: ## Run all quality checks (test + analyse + lint)
	composer quality

# ─── Docker ──────────────────────────────────────────────────────
up: ## Start Docker containers (detached)
	docker compose up -d --build

down: ## Stop Docker containers
	docker compose down

logs: ## Tail Docker container logs
	docker compose logs -f

shell: ## Open shell in app container
	docker compose exec app sh

# ─── CI Simulation ───────────────────────────────────────────────
ci: quality ## Simulate CI pipeline locally
	docker build -t dam-api:local .
	@echo ""
	@echo "✅ CI simulation passed"
