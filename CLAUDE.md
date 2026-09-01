# Working in this repository

## Everything runs in Docker

Each prototype (`prototype/node`, `prototype/php`, `prototype/rails`) is
containerized so that nothing is installed on the host. That is the whole
reason the projects are containerized. The rule:

- Never run `npm`, `node`, `composer`, `php`, `bundle`, `ruby`, `rails`, or
  any test runner on the host machine. Never create `node_modules`, `vendor`,
  or build output on the host.
- Always use the prototype's `Makefile` targets (`make up`, `make test`,
  `make coverage`, `make smoke`, `make shell`, `make routes`, ...). Each one
  wraps `docker compose run --rm app ...` or `docker compose exec`. When no
  target fits, run the command through `docker compose run --rm app <cmd>`
  from the prototype directory.
- Agents and subagents follow the same rule. A worker that needs to run the
  suite uses `make test` from the prototype directory.

Why it matters beyond cleanliness: the bind-mounted `src/node_modules`
(`vendor/` for PHP, gems for Rails) holds platform-specific binaries. A host
install on macOS leaves darwin binaries in the directory the Linux container
reads, the entrypoint skips its own install because the directory looks fresh,
and the container crashes on a missing `linux-*` native module.

If a host install has already happened, delete the installed directory
(`rm -rf prototype/node/src/node_modules prototype/node/src/public/app.css`)
and `make up` again so the container installs for itself.

## Ports

PHP 8000, Rails 3300 (host; 3000 in the container), Node 4000. The three never collide.

## Git worktrees

Create every worktree under the repository root's `.claude/worktrees/`
directory (`git worktree add .claude/worktrees/<branch> <base>`), never as a
sibling of the repository.

Docker Compose derives the project name from the directory name, so two
checkouts of the same prototype (the main repo and a worktree) share one
compose project and replace each other's container. Bring the other stack
down first (`make down` in the other checkout) before `make up` in this one.

## Work tickets

Each prototype carries its tickets in `<prototype>/work/` (`1-inbox` →
`2-doing` → `3-done`, with `journal.md`). Use the `/work-*` skills.

## Alignment contract

`docs/alignment.md` fixes the shapes the three prototypes share: prefixed
ULID ids, the JSON log payload and event vocabulary, rate-limit names and env
variables, the order/fulfillment/refund lifecycle, the admin feature set, and
the make-target vocabulary. Read it before changing any of those in one
prototype; the other two must match.

## Commit gate

`make hooks` at the repository root installs `.githooks/pre-commit`, which
runs a per-commit gate for every prototype a commit touches (outside `work/`
and `docs/`). `make check` (lint → assets → coverage-gated tests) is the
full gate: it runs once per branch before a PR opens (CI runs it again on
push/PR as the backstop), not on every commit.

- php's per-commit gate is `make precommit`: lint (Pint + PHPStan) and the
  ungated test suite, in one container. No asset build, no coverage
  instrumentation, no HTML report — those only pay off once per branch.
- node and rails still run their full `make check` on every commit; IMPRV-021
  seeded a fast-path ticket for each.

A red test suite still blocks a commit either way.
