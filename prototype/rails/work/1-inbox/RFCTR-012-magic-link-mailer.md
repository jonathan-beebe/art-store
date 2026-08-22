---
id: RFCTR-012
type: refactor
status: open
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
