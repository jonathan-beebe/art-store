# Working in this repository

The app lives in `app/`: one Laravel application serving the storefront,
the seller portal, and the admin site. `docs/` holds the product and design
decisions; `app/docs/` holds the implementation docs.

## Everything runs in Docker

The app is containerized so that nothing is installed on the host. The rule:

- Never run `npm`, `node`, `composer`, `php`, or any test runner on the
  host machine. Never create `node_modules`, `vendor`, or build output on
  the host.
- Always use `app/Makefile` targets (`make up`, `make test`, `make coverage`,
  `make smoke`, `make shell`, `make routes`, ...). Each one wraps
  `docker compose run --rm app ...` or `docker compose exec`. When no target
  fits, run the command through `docker compose run --rm app <cmd>` from
  `app/`.
- Agents and subagents follow the same rule. A worker that needs to run the
  suite uses `make test` from `app/`.

Why it matters beyond cleanliness: the bind-mounted `src/vendor` and
`src/node_modules` hold platform-specific binaries. A host install on macOS
leaves darwin binaries in the directory the Linux container reads, the
entrypoint skips its own install because the directory looks fresh, and the
container crashes on a missing `linux-*` native module.

If a host install has already happened, delete the installed directory
(`rm -rf app/src/vendor app/src/node_modules`) and `make up` again so the
container installs for itself.

## Port

The app serves on host port 8000.

## Git worktrees

Create every worktree under the repository root's `.claude/worktrees/`
directory (`git worktree add .claude/worktrees/<branch> <base>`), never as a
sibling of the repository.

`app/Makefile` names the compose project after the checkout directory plus
`app` (`art-store-app` in the main checkout), so a worktree gets its own
containers.

## Work tickets

`work/` directories are git-ignored. Tickets, journals, and retros are local
working state (`app/work/`, managed by the `/work-*` skills) and never land
in a commit.

## Spec

`docs/spec.md` fixes the shapes the app commits to: prefixed ULID ids, the
JSON log payload and event vocabulary, rate-limit names and env variables,
the order/fulfillment/refund lifecycle, the admin feature set, and the
make-target vocabulary. Source comments cite it by section number. Read it
before changing any of those.

## Commit gate

`make hooks` at the repository root installs `.githooks/pre-commit`, which
runs `make -C app precommit` (lint with Pint + PHPStan, then the ungated
test suite, in one container) for every commit that touches `app/` outside
`app/docs/` and `app/README.md`. `make check` (lint → assets →
coverage-gated tests) is the full gate: it runs once per branch before a PR
opens, and CI (`.github/workflows/check.yml`) runs it again on push/PR as
the backstop.

A red test suite blocks a commit either way.
