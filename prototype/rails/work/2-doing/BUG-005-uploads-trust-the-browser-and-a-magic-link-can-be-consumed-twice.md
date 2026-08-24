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

## Working

**Upload validation.** `app/models/image_format.rb` is a new plain module —
`ImageFormat.sniff(bytes)` — that reads the leading bytes and returns
`:png`/`:jpeg`/`:gif`/`:webp` or `nil`, ported from the Node prototype's
`image-format.ts` magic-byte table. `Listing#image=` now reads the upload's
`size` (rejecting over `Listing::MAX_IMAGE_UPLOAD_BYTES`) and its first
`ImageFormat::SNIFF_BYTES` bytes (rejecting anything `ImageFormat.sniff`
does not recognise) before ever calling `super`/`attach`; the rejection
message is carried in an instance variable and surfaced by the existing
`validate :image_is_an_image` as a `listing_image` field error, so a
rejected upload re-renders the form with the seller's other fields intact —
same path a title or price validation failure already took. `image=` only
runs this check against something that looks like a real upload
(`respond_to?(:read) && respond_to?(:size)`); anything else (e.g. a signed
blob id string, which stock Active Storage direct uploads use) still passes
straight to `super`, unchanged from before this ticket — nothing in this
app posts that shape today, so this is a no-op guard, not a new path.

- **Cap: 5 MB** (`Listing::MAX_IMAGE_UPLOAD_BYTES = 5.megabytes`). Matches
  the PHP prototype's `MAX_IMAGE_KILOBYTES = 5120`.
- **Accepted formats: PNG, JPEG, GIF, WebP** — the same four Node and PHP
  accept. SVG is deliberately excluded (markup, not a signature format) and
  refused whatever its declared `Content-Type`; a mismatched declared type
  is ignored entirely — only the bytes decide, both ways (a lying `image/png`
  on SVG bytes is refused, a lying `text/plain` on real PNG bytes is
  accepted).
- Checked for other reachable paths: no seed, rake task, or API touches
  `Listing#image`; the only writer is the seller listing form
  (`Seller::ListingsController`). `Listing#image.attach` called directly on
  an association (bypassing the `image=` setter and its validation) is used
  once in `test/models/listing_test.rb` to test that an already-stored
  blob renders — that path was already outside every prior upload
  validation too, and stays that way; nothing in the app reaches it.
- Form (`app/views/seller/listings/_form.html.erb`) states the cap and the
  four formats in the hint text and narrows `accept` to the four MIME
  types. Docs: `docs/architecture.md`'s listing-validation paragraph now
  names the cap and `ImageFormat.sniff`.

**Magic-link double consume.** `MagicLink#consume` (renamed from the old
bang `consume!`, since it now returns a boolean rather than raising)
replaced the read-then-`update!` pair with
`self.class.where(id: id, consumed_at: nil).update_all(consumed_at: now) == 1`.
`Auth::MagicLinksController#show` keeps its `link.usable?` gate first (that
read only decides which message — expired vs. already-used — an
already-spent or expired link gets; it changes no row) and then
unconditionally calls `link.consume`; a `false` return (the atomic update
matched no row) is refused with the same "already been used" message a
sequentially-replayed link gets, logged the same way. `docs/identity.md`'s
seller sign-in section states the rule and what the `usable?` read still
is and is not responsible for.

- **What the test proves.** `test/models/magic_link_test.rb` — "consume
  refuses a second copy of the link loaded before the first was spent" —
  loads the same row into two separate `MagicLink` instances (`racer_a`,
  `racer_b`), as two concurrent requests would each do their own `SELECT`,
  then calls `consume` on each. `racer_a` wins; `racer_b`'s `UPDATE`
  matches nothing because `racer_a`'s write already landed, and it returns
  `false` even though `racer_b`'s own in-memory `consumed_at` was still
  read as nil. This is the shape of the fix: refusal comes from the
  database's row-count answer at write time, not from a timestamp read
  into Ruby beforehand. What it does **not** prove: two threads or two
  Puma workers hitting the same row at the literal same instant — SQLite
  in this container serializes writes regardless, so a true concurrent
  race was not driven end to end. A second test — "consume is a single
  conditional UPDATE, not a read followed by a write" — subscribes to
  `sql.active_record` and asserts `consume` issues exactly one `UPDATE ...
  WHERE ... consumed_at IS NULL` statement, no separate `SELECT`, which is
  the actual guarantee SQLite's own single-writer serialization relies on
  to make the two-instance test meaningful.
- Renaming `consume!` to `consume` touched three existing test call sites
  (`test/models/magic_link_test.rb`,
  `test/controllers/auth/magic_links_controller_test.rb`) — no behavior
  change to those, only the name and (for the two direct-consume calls) an
  unused boolean return.
- Added controller-level `LogCapture` tests asserting `magic_link.consume`
  logs `refused` at `info` for an expired, a consumed, and an unknown
  token alike (never saying which), and `did` for the winning consume,
  carrying `actor_type` and `magic_link_id`.

**Deviations from the ticket's literal wording:** none. `make check`
(rubocop → tailwindcss build → full suite at `COVERAGE_MIN=100`) is green.

### Fix-up

Three review items on this ticket's commit (`b639352`):

- **Transport-level upload cap.** `Listing#image_upload_rejection`'s size
  check only runs after Rack has already streamed the whole multipart body
  to a tempfile — Rack's own ceiling
  (`Rack::Multipart::Parser::PARSER_BYTESIZE_LIMIT`, read once from
  `ENV["RACK_MULTIPART_PARSER_BYTESIZE_LIMIT"]` the first time
  `rack/multipart/parser.rb` loads) defaults to 10 GiB, so a seller with a
  session could park gigabytes on disk per request before the app-level
  check ever ran. `lib/upload_limits.rb` now holds `UploadLimits::MAX_IMAGE_BYTES`
  (plain arithmetic, no ActiveSupport) as the one source of truth;
  `Listing::MAX_IMAGE_UPLOAD_BYTES` is defined from it, and `config/boot.rb`
  sets `ENV["RACK_MULTIPART_PARSER_BYTESIZE_LIMIT"]` from it too — before
  `bundler/setup` runs, so the value is in place before Rack ever reads it
  (an initializer would be too late; Rack is already loaded by then). The
  transport limit is **6 MiB** — the 5 MiB image cap plus 1 MiB of headroom
  for the multipart envelope (boundaries, headers, and the form's other
  fields: title, description, medium, dimensions, price, quantity, none of
  which individually exceeds a few KB). It protects against a request whose
  multipart body — as declared by `Content-Length`, or as actually
  streamed, whichever comes first — exceeds that total; Rack raises and
  aborts the parse mid-read rather than finishing the write to a tempfile.
  A new test (`test/models/listing_test.rb`) asserts
  `ENV["RACK_MULTIPART_PARSER_BYTESIZE_LIMIT"]` equals
  `Listing::MAX_IMAGE_UPLOAD_BYTES + 1.megabyte` rather than pushing
  gigabytes through the suite.
- **Stale method name in docs.** `#consume!` (renamed to `#consume` in this
  commit) was still named in `docs/architecture.md`, `docs/ontology.md`,
  `docs/identity.md` (a second sequence diagram the original doc edit
  missed), and `README.md`. All four now say `#consume`. A repo-wide grep
  turned up no other reference to a method or route this commit renamed or
  removed.
- **Mislabelled test.** `test/controllers/auth/magic_links_controller_test.rb`
  had a test named for the row-count check, but a second `get` on a used
  link reloads a row with `consumed_at` already set — `usable?` refuses it
  before `consume` ever runs, so the row-count race is never exercised here.
  Renamed to "a second, sequential visit to a used link is refused and
  logged"; the row-count guarantee stays proved by
  `test/models/magic_link_test.rb`'s racer test and its single-UPDATE test.

`make check` is green: 1211 runs, 4287 assertions, 100% line coverage
(2183/2183).
