---
id: FEAT-013
type: feature
status: open
created: 2026-08-23
---

# FEAT-013: Live threads and unread badge with Hotwire over Solid Cable

## Problem
After FEAT-011 a thread page and the nav badge only change on the next full page load. `config/application.rb:15` leaves `action_cable/engine` commented out, the Gemfile has no `turbo-rails`, `importmap-rails`, or `solid_cable`, and the README states "no JavaScript" as a property of the app. The Node prototype's FEAT-016 ticket claims live updates are "the one capability in the field that neither competing prototype can structurally answer" and built a hand-rolled Server-Sent Events stream plus a custom script to move one number.

## Goal
A message posted by one side appears on the other side's open thread page and moves their nav badge without a reload, using the framework's own machinery, while every page still works with JavaScript off.

## Outcome
With two browsers open on the same thread, a reply posted in one appears in the other within a second and the other's "Messages" badge in the nav increments; opening a thread clears the reader's badge in that reader's other tabs; with JavaScript disabled every page, form, and redirect behaves exactly as it did after FEAT-011; no stream delivers a message or a count to anyone who is not the participant it belongs to; a posted message whose transaction rolls back broadcasts nothing; a test asserts the broadcasts for post and read; no Redis or other service is added — the stack is still one container and SQLite; `docs/architecture.md` and `README.md` describe what JavaScript is now present and what holds without it; the suite stays at 100% line coverage.

## Why it matters
This answers Node's FEAT-016 head-on with Rails' built-in answer. Hotwire (Turbo + Action Cable over Solid Cable) is stock Rails 8 and is the difference between a framework that ships live updates and one that hand-rolls them per feature.

## Discovery notes
Stock Rails 8 pieces: `turbo-rails`, `importmap-rails` (pins `@hotwired/turbo-rails` from the gem's own assets — no CDN, no node), `solid_cable` with its own SQLite database entry in `database.yml` and `db/cable_schema.rb`, `config/cable.yml` with `solid_cable` for development/production and `test` for test, `action_cable/engine` enabled, `javascript_importmap_tags` in the three layouts, and `config/importmap.rb`. The pieces that do the work: `turbo_stream_from conversation` on the thread page; `Message` `after_create_commit` that `broadcast_append_to`s the conversation (the message partial) and `broadcast_replace_to`s the counterpart's badge (stream `[recipient, :unread_messages]`, target the badge's dom id); `Conversation#read_by!` broadcasting the reader's badge. After-commit is the reason a rolled-back post sends nothing — it is the design point Node had to work around by hand. `turbo_stream_from` renders a signed stream name, so a client cannot subscribe to someone else's stream; an `ApplicationCable::Connection` is still reasonable to add but is not the access control. Wrap the badge in a partial with a stable `dom_id` on all three layouts so one partial serves the first render and the broadcast. The Rails test helpers: `Turbo::Broadcastable::TestHelper` (`assert_turbo_stream_broadcasts`) and `assert_broadcasts`. Verify inside the container (`docker compose run --rm app bundle exec …`) that the gem versions resolve against Rails 8.1.3 before committing to the Gemfile; `make build`/`make up` run `bundle install` through the entrypoint. Check Turbo Drive's effect on the existing forms and `button_to`s (it should be transparent; `data-turbo="false"` on a form is the escape hatch if a redirect-and-flash test changes behaviour). Watch the CSP initializer: it is commented out today, so importmap tags are unaffected. Keep the whole thing to configuration plus a few model callbacks; if more than ~40 lines of JavaScript appear, something is wrong.

## Related work
- FEAT-011
- FEAT-010
- prototype/node/work/3-done/FEAT-016-live-unread-badge-over-server-sent-events.md
