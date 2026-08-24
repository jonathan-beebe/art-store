---
id: BUG-005
type: bug
status: open
created: 2026-08-23
---

# BUG-005: Uploads trust the browser's content type with no size cap, and a magic link can be consumed twice

## Problem
Listing image upload accepts any file whose browser-declared content type starts with `image/` and has no size cap (Node sniffs magic bytes and refuses SVG; PHP decodes with gd). Magic-link consumption reads the row then writes `consumed_at` in two statements, so two concurrent verifications of the same token both succeed (Node checks the `UPDATE … WHERE consumed_at IS NULL` row count).

## Goal
The upload and the sign-in link each refuse what they should, atomically.

## Outcome
An upload larger than the cap or whose bytes are not PNG/JPEG/GIF/WebP is refused with a field error on the listing form whatever its declared type (SVG refused); a second concurrent consume of the same magic link is refused, asserted by a test that consumes the same token twice with the row-count check; `docs/identity.md` and the listing docs state both.

## Why it matters
An SVG upload is stored script; a double-consumable link is a session-fixation primitive.

## Discovery notes
Active Storage's `blob.byte_size` and a magic-byte check on the first bytes (or `image_processing`/libvips `identify`) before attach; `MagicLink.where(consumed_at: nil).update_all(...)` returning the affected count is the Node shape in Active Record.

## Related work
- prototype/node BUG-002, BUG-004
- FEAT-004 (seller portal)
