.PHONY: help queue-mobile queue-default queue-once-mobile queue-once-default test test-mobile update cron mobile-build-prune

PHP ?= php
PHPUNIT ?= vendor/bin/phpunit

queue-mobile: ## Start mobile-builds queue worker
	$(PHP) think queue:work --queue=mobile-builds --tries=1 --timeout=900 --sleep=3

queue-default: ## Start default queue worker
	$(PHP) think queue:work --queue=default --tries=3 --timeout=60 --sleep=3

queue-once-mobile: ## Run one mobile-builds job and exit
	$(PHP) think queue:work --queue=mobile-builds --once --tries=1 --timeout=900 --sleep=3

queue-once-default: ## Run one default queue job and exit
	$(PHP) think queue:work --queue=default --once --tries=3 --timeout=60 --sleep=3

test: ## Run backend PHPUnit suite
	$(PHPUNIT)

test-mobile: ## Run mobile build adapter tests
	$(PHPUNIT) tests/Unit/Mobile tests/Feature/Mobile

update: ## Apply framework database upgrades (database/updates/vX.Y.Z)
	$(PHP) think yd:update

cron: ## Run scheduled backend tasks once
	$(PHP) think cron:run

mobile-build-prune: ## Prune expired mobile build artifacts
	$(PHP) think saas:mobile-build-prune

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
