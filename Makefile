.PHONY: help hooks check precommit

.DEFAULT_GOAL := help

help: ## list every target with its one-line description
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "%-12s  %s\n", $$1, $$2}'

# Installs the commit gate: .githooks/pre-commit runs `make -C app precommit`
# for every commit that touches app/. Run once per clone; worktrees share
# the setting.
hooks: ## install the commit gate (git config core.hooksPath .githooks)
	git config core.hooksPath .githooks
	@echo "core.hooksPath=.githooks"

check: ## the app's full gate (lint -> assets -> coverage); run once before a PR
	$(MAKE) -C app check

precommit: ## the app's per-commit gate (lint + ungated tests)
	$(MAKE) -C app precommit
