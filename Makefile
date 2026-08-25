.PHONY: help hooks check check-node check-php check-rails

.DEFAULT_GOAL := help

help: ## list every target with its one-line description
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "%-12s  %s\n", $$1, $$2}'

# Installs the commit gate: .githooks/pre-commit runs `make check` for every
# prototype a commit touches. Run once per clone; worktrees share the setting.
hooks: ## install the commit gate (git config core.hooksPath .githooks)
	git config core.hooksPath .githooks
	@echo "core.hooksPath=.githooks"

check: check-node check-php check-rails ## run every prototype's commit gate

check-node: ## the node prototype's `make check`
	$(MAKE) -C prototype/node check

check-php: ## the php prototype's `make check`
	$(MAKE) -C prototype/php check

check-rails: ## the rails prototype's `make check`
	$(MAKE) -C prototype/rails check
