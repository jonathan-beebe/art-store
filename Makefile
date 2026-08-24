.PHONY: hooks check check-node check-php check-rails

# Installs the commit gate: .githooks/pre-commit runs `make check` for every
# prototype a commit touches. Run once per clone; worktrees share the setting.
hooks:
	git config core.hooksPath .githooks
	@echo "core.hooksPath=.githooks"

check: check-node check-php check-rails

check-node:
	$(MAKE) -C prototype/node check

check-php:
	$(MAKE) -C prototype/php check

check-rails:
	$(MAKE) -C prototype/rails check
