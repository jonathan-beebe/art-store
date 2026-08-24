---
id: IMPRV-011
type: improvement
status: open
created: 2026-08-23
---

# IMPRV-011: Identity hardening — non-revealing admin login, cross-site redirect refusal, CSRF tokens

## Problem
`adminSite` passes `admits` to `signInRoutes`, which refuses before issuing a link, so the response reveals which addresses are operators (PHP answers byte-identically either way). `resolveLocalRedirect` checks origin only, so a seller-site link can `redirect_to` an admin path (PHP `ActorType::allowsPath()` refuses cross-site paths). No POST carries a CSRF token; the defence is `SameSite=Lax` plus CSP `form-action 'self'`, recorded nowhere. `docs/alignment.md` §7 decision 3 adopts tokens.

## Goal
Node's sign-in and form posts are as hard to abuse as the other two prototypes'.

## Outcome
The admin sign-in POST answers the same page and status for an admitted and an unknown address, issuing nothing for the unknown one, with the debug bar's local-dev notice never naming a seeded address; a `redirect_to` outside the signing-in site's own path prefix is refused; every POST form carries a CSRF token that the server verifies, a missing or wrong token answers 403 with the site's page, and tests cover each; `docs/identity.md` states all three.

## Why it matters
Operator addresses are personal data; a cross-site redirect is a phishing hop; the CSRF defence should be a decision the docs can point at.

## Discovery notes
A double-submit cookie signed with the existing `COOKIE_SECRET` needs no dependency; the token is a hidden input rendered by the layout. PHP's `allowsPath` and its byte-identical response are the shapes to borrow (`prototype/php` on `main`).

## Related work
- BUG-004, BUG-006
- prototype/php IMPRV-002
