---
id: RFCTR-012
type: refactor
status: resolved
created: 2026-08-22
---

# RFCTR-012: Deliver magic links through Action Mailer

## Problem
`src/app/delivery/` defines a `MagicLinkDelivery` factory keyed on `Rails.configuration.x.magic_links.delivery`, a `FlashMagicLinkDelivery` that writes the URL into the flash, and a `MailMagicLinkDelivery` whose only behaviour is `raise NotImplementedError`. Action Mailer is loaded in `config/application.rb` and unused.

## Goal
Sending a sign-in link is a mailer call.

## Outcome
A `MagicLinkMailer` with a template sends the link; the debug alert still prints the link in development and test (the flash behaviour the layouts and tests depend on); the `app/delivery` tree, the `MAGIC_LINK_DELIVERY` setting and the `NotImplementedError` stub are gone; README and `docs/identity.md` describe the mailer as the email hook; no mail leaves the container in development or test.

## Why it matters
A port with a stub implementation is speculative structure; Action Mailer is the Rails answer to the same question and gives a preview/template for free.

## Discovery notes
`delivery_method :test` in development keeps mail in `ActionMailer::Base.deliveries`. The flash line can live beside the mailer call in the controller, guarded by an environment check or a config flag. Depends on RFCTR-004's `MagicLink.issue` shape.

## Related work
- RFCTR-004

## Working

`MagicLinkMailer#sign_in` takes the link and the URL as mailer params and
renders a text and an HTML part; the URL is passed in because the plaintext
token is readable once, at issue time. `ApplicationMailer` carries
`default from: "noreply@artstore.test"` and the stock `mailer` layouts.
`MagicLinkSender#send_magic_link` now issues the link, enqueues the mail with
`deliver_later`, and writes the URL into `flash[:debug_magic_link]` when
`Rails.configuration.x.magic_links.debug_alert` is on.

That setting replaces `MAGIC_LINK_DELIVERY`: `MAGIC_LINK_DEBUG_ALERT`, cast
through `ActiveModel::Type::Boolean`, defaults to on outside production.
Development joins test on `delivery_method :test`, so no mail leaves the
container in either. `app/delivery/` and `test/delivery/` are gone.

`test/test_helper.rb` includes `ActiveJob::TestHelper` and
`ActionMailer::TestHelper` in `ActiveSupport::TestCase`, so the whole suite
runs on the test queue adapter rather than delivering from a background
thread. `test/mailers/magic_link_mailer_test.rb` covers the addresses, the
subject and both parts; `test/mailers/previews/magic_link_mailer_preview.rb`
renders at `/rails/mailers`. The seller sessions and checkout controller tests
assert the enqueue, and one seller test turns the debug alert off and checks
the flash stays empty.

Left alone: `Notification#deliver_by_email` is still an empty hook — its
comment now points at `MagicLinkMailer` rather than the deleted port.
Production SMTP settings stay commented out.
