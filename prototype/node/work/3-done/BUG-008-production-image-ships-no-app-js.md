---
id: BUG-008
type: bug
status: resolved
created: 2026-08-24
---

# BUG-008: Production image ships no /app.js

## Problem
The `runtime` stage of `Dockerfile` copies `src/app` and the build stage's compiled `public/app.css`, and nothing else from `public/`. `src/public/app.js` — tracked source, referenced by all three site layouts as `<script defer src="/app.js">` — never enters the image, so every page served by a deployed container requests `/app.js` and receives a 404 (confirmed live on art-store-node.onrender.com). Development never sees it because the compose bind mount serves `src/public` directly. Each 404 also renders the full HTML error page through the error handler, one wasted render per page view.

## Goal
The production image serves every tracked static file the pages reference.

## Outcome
A container built from the `runtime` target answers `GET /app.js` 200 with the tracked file, and pages on the deployed site load it with no console errors.

## Why it matters
The unread-message badge never updates live on the deployed site, every page view pays a wasted request and error-page render, and the console 404 reads as a broken deploy to anyone reviewing it.

## Discovery notes
`.dockerignore` already keeps the built `app.css`, `uploads/`, and `storage/` out of the build context, so the runtime stage can copy `src/public` wholesale and receive only tracked static files; the built `app.css` still arrives separately from the build stage. Found alongside a sibling symptom on the same deploy: the render-blocking `/app.css` was served with `cache-control: public, max-age=0` and revalidated on every navigation, painting a white flash between pages — addressed in the same change by setting `maxAge` on the static registrations (short for the unversioned `app.css`/`app.js`, long and immutable for the UUID-named uploads).

## Related work
- 39b3b4f — introduced the production `runtime` image
- 9f25ded — php/rails production images and Render deploy chains
