---
id: FEAT-013
type: feature
status: resolved
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

## Working

### What I verified before changing anything

`config/application.rb:15` had `action_cable/engine` commented out, the Gemfile
carried none of the three gems, and there was no `config/cable.yml`,
`config/importmap.rb` or `app/javascript`. Baseline 723 runs at 100% line
coverage. `docs/review.md:65` claims "no `<script>` in any view, no
`app/javascript`, no importmap" — that line is now wrong and belongs to
FEAT-014 along with `docs/architecture.md`.

### Gems

`bundle install` in the container resolved against Rails 8.1.3.1:
`turbo-rails 2.0.23`, `importmap-rails 2.2.3`, `solid_cable 4.0.2`. The bundle
lives in the bind-mounted `vendor/bundle`, so the running container picked them
up on the next `make up`.

### Solid Cable: single database, not a second one

Deviation from the discovery notes, which suggested a `cable` entry in
`database.yml` and `db/cable_schema.rb`. The gem's README documents both
shapes; I took the single-database one (copy the gem's schema into a normal
migration, no `connects_to` in `cable.yml`) because:

- `docs/architecture.md` states one deployable, one SQLite file. A second
  database file makes that sentence false for one table.
- The stock Rails 8 multi-db shape ships `db/cable_schema.rb` and relies on
  `db:prepare` loading it. `make fresh` runs `db:drop db:create db:migrate
  db:seed`; on an empty second database with an empty `db/cable_migrate`,
  `db:migrate` would leave `solid_cable_messages` missing.

So: `db/migrate/20260823000105_create_solid_cable_messages.rb` holds the gem's
schema, `solid_cable_messages` is in `db/schema.rb`, and `config/cable.yml` is
`solid_cable` for development and production with `test` in test.
`docker/entrypoint.sh` needed no change — its `bin/rails db:prepare` covers the
one database it already covered. Verified after the change: `make fresh` runs
clean and seeds, `make up` boots and the table is present, `bin/rails test`
prepares the test database from `schema.rb`.

### Importmap

Files laid by hand rather than through `importmap:install`, which would also
have written `bin/importmap` and `vendor/javascript/.keep` — a binstub for
pinning npm packages this app has no use for. What exists:
`config/importmap.rb` (two pins) and `app/javascript/application.js` (one
`import`). `javascript_importmap_tags` in the three layouts. No Stimulus.
The served map:

    "application": "/assets/application-bcb79c8b.js"
    "@hotwired/turbo-rails": "/assets/turbo.min-9fd88cd5.js"   (105,579 bytes, 200)

The CSP initializer is still commented out, so the tags carry no nonce.

### No ApplicationCable::Connection

`actioncable/lib/action_cable/engine.rb:55` sets the connection class to
`"ApplicationCable::Connection".safe_constantize || ActionCable::Connection::Base`.
With no such class the base connects, which the handshake below confirms.
Signed stream names are the access control, so an identified connection would
add a file with nothing to say. `app/channels/` does not exist.

### Per-site broadcast markup

The three sites' message rows and badges differ in Tailwind classes that carry
theme, not decoration: the storefront row has no card, the portal's is a white
card on a neutral page, the admin's a slate card on a dark page. A single
markup would put `text-neutral-500` on `bg-slate-900`. So the broadcast is
per-site, which needs a stream per participant rather than one per
conversation: the thread pages subscribe with `turbo_stream_from @conversation,
current_<actor>`, and `Message#broadcast_arrival` sends each participant the
partial of the site they read on. Deviation from the ticket's
`turbo_stream_from @conversation`; the ticket allowed per-site markup and this
is what it costs.

Partials, each serving both the first render and the broadcast:
`{shop,seller,admin}/conversations/_message.html.erb` and
`_unread_badge.html.erb`.

### Dom ids

Both targets are derived rather than written twice: `Conversation#messages_dom_id`
(`messages_conversation_8`) and `Messaging#unread_badge_dom_id`
(`unread_messages_customer_1`). The view and the broadcast call the same
method.

The badge partial renders a `class="contents"` wrapper carrying the dom id and,
inside it, the `data-unread-messages` span only when the count is positive.
That keeps FEAT-011's assertions (`text: "1"`, and `false` when nothing is
unread) exact, gives the replace a target that is present at zero, and
`display: contents` keeps the wrapper out of the nav's flex layout.

### Where the domain rules stayed

`Conversation#post!` and `#read_by!` are unchanged in what they decide.
`Message after_create_commit` is the only new writer of broadcasts on a post.
`read_by!` broadcasts through `ActiveRecord.after_all_transactions_commit`, so
it runs after the commit whether or not a caller wrapped it, and only when a
reading actually moved a row.

The three `unread_message_count` helper methods (`ShopHelper`,
`Seller::BaseController`, `Admin::BaseController`) are gone: the badge partial
reads the count off the actor it is given.

### Turbo Drive and the JavaScript-off promise

Every existing test passed unmodified. Three of the four re-render paths
already answered `:unprocessable_content`, which is what Turbo needs to render
a refused form.

One class of flow did need the standard fix. Turbo submits `PATCH` and `DELETE`
over `fetch`, which repeats the method on a 302; Rails' convention is
`status: :see_other`. Four redirects, each after a non-POST:

| Action                              | Verb   | Was | Now |
| ----------------------------------- | ------ | --- | --- |
| `Seller::FaqsController#update`     | PATCH  | 302 | 303 |
| `Seller::FaqsController#destroy`    | DELETE | 302 | 303 |
| `Seller::ListingsController#update` | PATCH  | 302 | 303 |
| `Shop::CartItemsController#destroy` | DELETE | 302 | 303 |

No `data: { turbo: false }` was needed anywhere. `redirect_back` in
`Shop::FavoritesController` and the two support controllers reads `Referer`,
which Turbo sends.

### Live walk (dev stack, after `make down && make up` and `make fresh`)

1. `GET /` — `<script type="importmap">` with the two pins, the
   `<script type="module">import "application"</script>` tag, one
   `<turbo-cable-stream-source signed-stream-name="…">` for the badge, and
   `<span id="unread_messages_customer_11" class="contents">`.
2. WebSocket handshake at `/cable`: `101 Switching Protocols` and
   `{"type":"welcome"}`, with no `ApplicationCable::Connection` defined.
3. Two curl sessions signed in over magic links (`maya@example.com` seller,
   `casey@example.com` customer), plus `ops@example.com` on the admin site.
   Each thread page carries two stream sources and one importmap tag, and its
   `<ol id="messages_conversation_N">`.
4. Customer asks a listing question, seller opens the thread: one
   `solid_cable_messages` row, `replace` on
   `Z2lkOi8vYXJ0LXN0b3JlL1NlbGxlci8x:unread_messages` targeting
   `unread_messages_seller_1`.
5. Seller replies: three more rows — `append` to
   `Conversation/8:Seller/1` rendering `seller/conversations/_message`
   (`bg-white`), `append` to `Conversation/8:Customer/1` rendering
   `shop/conversations/_message` (no card), and `replace` on
   `Customer/1:unread_messages`.
6. Delivery, not just enqueue: a `bin/rails runner` in a second process
   subscribed to the customer's two streams through
   `ActionCable.server.pubsub` and received both frames within a second of the
   seller's POST from the web process. Repeated for the read side — the
   customer opening the thread delivered the `replace` on their badge stream.
7. Access: `Turbo::StreamsChannel.verified_stream_name` returns the name for
   its own signature, `nil` for the unsigned string
   `art-store/Conversation/8:art-store/Customer/1`, and `nil` for a tampered
   signature. The seller's and the customer's names for the same conversation
   differ.
8. JavaScript-off paths over curl: the question POST, the reply POST and the
   add-to-cart POST all answered 302 to the right place; remove-from-cart
   answered 303 to `/cart`.

### Results

734 runs, 2270 assertions, 0 failures, 0 errors. Line coverage 1231/1231
(100.00%). Eleven new tests: five on `Message` (append to each side, per-site
markup, the recipient's badge, the writer's badge untouched, a rolled-back post
sending nothing), three on `Conversation` (`read_by!` sends the reader a badge
with what is left, sends nothing when nothing was unread, sends nothing when
the reading rolls back), and one per site asserting the thread page's two
signed stream sources and the importmap tag.

### Left alone

`docs/architecture.md` and `docs/review.md` (FEAT-014). The `Notifications`
badge in the seller nav, which is not this ticket's. `bin/importmap` and
`vendor/javascript`, which the app has no pins to fetch for.
