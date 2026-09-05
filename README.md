# Art Store

An online marketplace where artists sell their handmade art to buyers. One
Laravel application serves three sites from one server:

- the storefront at `/`
- the seller portal at `/seller`
- the admin site at `/admin`

## Running it

Docker Desktop is the only prerequisite. From `app/`:

```sh
make up      # build, install, migrate, serve on http://localhost:8000
make fresh   # reset the database and load the demo data
make test    # the test suite
make help    # every target
```

`app/README.md` covers first run, the commands, seeded accounts, and
deployment.

## Reading order

1. `docs/principles.md` — what the product optimizes for.
2. `docs/ontology.md` — every entity in the product and why it exists.
3. `docs/spec.md` — identifiers, logging, rate limits, the order lifecycle,
   the admin feature set, and the make vocabulary.
4. `app/docs/architecture.md` — the layers, sites, and conventions of the
   code; then the per-feature docs indexed at `app/docs/README.md`.

## Contributing

`make hooks` at the root installs the commit gate. Each commit that touches
`app/` outside `app/docs/` and `app/README.md` runs lint and the test suite;
`make check` runs the full gate (lint, assets, coverage-gated tests) once
before a PR opens; CI runs lint and the tests, without coverage.
`CLAUDE.md` holds the working rules, including that nothing runs on the
host outside Docker.