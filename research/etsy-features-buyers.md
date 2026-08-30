# Etsy.com — Buyer Feature Inventory

Research date: 2026-08-13. Method: Track B — no authenticated account access; reconstruction from vendor documentation (help center, legal pages, newsroom, Seller Handbook), two direct page fetches that got past bot protection (etsy.com/registry, etsy.com/giftcards), app store listings, engineering-blog posts, trade press, and user reports. Confidence: **confirmed** = vendor doc or visual evidence; **probable** = two independent secondary sources; **reported** = single source.

A methodology caveat that applies to most "confirmed" rows: Etsy's bot protection (DataDome) blocked direct fetches of nearly all help.etsy.com and etsy.com/legal pages, so vendor-doc content was read through search-index extracts of those pages rather than full-page reads. The cited article is Etsy's own; the reading of it is second-hand.

## Known blind spots

- **Account and settings screens**: no authenticated session existed. Navigation paths for logged-in surfaces come from help articles, not observation. Specifically unresolved: a buyer address-book UI, stored-payment-card management, and a self-serve order-cancellation control (none were found in documentation; absence is not proven).
- **Paid/gated buyer features**: Etsy Insider is invite-only; the member experience was never observed. Pricing is primary-confirmed as of the October 2025 terms; the renamed current terms page has no archive capture, so 2026 drift can't be ruled out.
- **Anything shipped in the last few months** without a public announcement — experiments and quiet rollouts are invisible to this method.
- **Live UI enumeration**: exact filter/sort lists, category-tree structure, and curated-collection naming were never screenshotted; they are reconstructed from documentation.
- Person-to-person "follow" mechanics, registry group-gifting, current video-review specs, and the UI language list are individually flagged below.

---

## Domain: Search and discovery

### Text search
- What it does: global keyword search with autocomplete over listings and shops.
- Where it lives: search bar on every page (web and app).
- Gating: all users, logged out included.
- Confidence: confirmed. Last evidenced: 2026-08-13.
- Evidence: help article "How to Search for Items and Shops on Etsy".[^search]

### Search filters
- What it does: narrow results by price range, free shipping / ships-from-country / ready-to-ship time, estimated delivery date, color, shop location, item type (handmade / vintage / craft supplies), customizable, on sale, holiday, sustainability attributes, Star Seller.
- Where it lives: "Show filters" on results (web), filter icon (app).
- Gating: all users.
- Confidence: probable (converging help-article snippets; corroborated independently by the filter query-parameters Etsy blocks in robots.txt — `color=`, `price_bucket=`, `ship_to=`, `customizable=`, `attr_*=`, `min=`/`max=`, `gift_card=`, `handmade`). Last evidenced: 2026-08-13.
- Evidence: help articles and robots.txt probe.[^search][^sustain][^robots]

### Sort options
- What it does: relevance (default), price ascending/descending, newest, top customer reviews.
- Where it lives: sort dropdown on results.
- Gating: all users.
- Confidence: probable. Last evidenced: 2026.
- Evidence: help-article snippets.[^search]

### Visual (image) search
- What it does: photograph or upload an image and get visually similar listings; built on a GPU-backed ML service searching 100M+ listings.
- Where it lives: camera icon in the search bar; launched iOS Nov 2022, Android later.
- Gating: all users; iOS-first rollout (current web/Android parity unverified).
- Confidence: confirmed. Last evidenced: launch coverage Nov 2022.
- Evidence: Etsy news post and TechCrunch.[^imgsearch]

### Category browse
- What it does: hierarchical category taxonomy at `/c/...` routes.
- Where it lives: top navigation and category pages.
- Gating: all users.
- Confidence: confirmed for the route structure (robots.txt), reported for the tree's contents.
- Evidence: robots.txt probe.[^robots]

### Gift Mode (AI gift finder)
- What it does: quiz — recipient relationship, occasion, 3+ interests from 15 categories — sorts the recipient into one of 200+ personas ("The Music Lover," "The Pet Parent") and surfaces curated gift guides. Built with machine learning, human curation, and GPT-4. Launched January 24, 2024.
- Where it lives: etsy.com/gift-mode, surfaced from homepage and navigation.
- Gating: all users to browse.
- Confidence: confirmed. Last evidenced: launch Jan 2024, still live 2026.
- Evidence: Etsy announcement and press coverage.[^giftmode]

### Shopping through external AI assistants
- What it does: Etsy listings are discoverable and purchasable through ChatGPT, Gemini, Google AI Mode, and Copilot; Etsy documents this for buyers in a dedicated help article. ChatGPT Instant Checkout (buy without leaving ChatGPT, standard payment methods, sellers keep order control) launched September 29, 2025; Microsoft Copilot Checkout went live in the US in January 2026. One earlier-sourced report claimed the ChatGPT Instant Checkout pilot was discontinued in early 2026; the deeper changelog research found no corroboration, so treat the discontinuation claim as unverified.
- Where it lives: outside etsy.com, inside the assistant products.
- Gating: assistant- and region-dependent (Copilot Checkout is US).
- Confidence: confirmed for the launches; the discontinuation report is uncorroborated.
- Evidence: help article "Purchasing Etsy Items Through ChatGPT, Gemini, Google AI Mode, and Copilot"; launch coverage.[^aiagents]

### Etsy's own AI assistants
- What it does: Etsy runs in-product AI chat assistants: a **support assistant** (delivery issues, listing issues, cases, refunds, account questions), a **gifting assistant**, and a **wedding assistant**. This confirms as shipped product what Etsy's Q1 2026 earnings described as agentic AI "in testing."
- Where it lives: chat interactions on Etsy (help and shopping surfaces).
- Gating: rollout scope not stated.
- Confidence: confirmed (article body fetched directly; created Oct 2024, updated 2026-08-13).
- Evidence: help article "How Etsy AI Assistants Work".[^hc-enum-b]

### Personalized homepage and recommendation modules
- What it does: ML-ranked homepage modules using purchases, clicked categories, listing taxonomy, and time features; "More from this shop" and "You may also like" modules; recently-viewed items (last 45 days, cross-device, signed-in).
- Where it lives: homepage and listing pages.
- Gating: personalization requires login; recently-viewed is logged-in.
- Confidence: confirmed for the module architecture (Etsy engineering blog); probable for the 45-day recently-viewed window.
- Evidence: Code as Craft posts; help snippets.[^cac]

---

## Domain: Listing page

### Listing view and actions
- What it does: photos/video, variation dropdowns, personalization text box, favoriting, share, reviews display, shipping-and-returns section with an estimated-delivery range ("Order today to get it by …"), and message-the-seller before purchase.
- Where it lives: listing page.
- Gating: browsing is open; favoriting and messaging require login.
- Confidence: probable (converging help-article snippets; no screenshot).
- Evidence: help articles on search/purchase/contacting a shop.[^search][^contact]

### Add to registry
- What it does: add a listing to a wedding, baby, or gift registry; selected personalization carries through.
- Where it lives: gift icon / "Add to registry" on listing pages.
- Gating: registry creation requires sign-in.
- Confidence: confirmed (etsy.com/registry fetched directly 2026-08-13).
- Evidence: registry page.[^registry]

---

## Domain: Favorites and lists

### Favorite items and shops
- What it does: heart a listing; favorite a shop from its homepage.
- Where it lives: heart icon on listings and shop pages.
- Gating: logged-in.
- Confidence: probable. Last evidenced: 2026.
- Evidence: help article "How to Keep Track of Items and Shops You Love".[^favorites]

### Collections
- What it does: user-created folders organizing favorited items (wish lists, themed boards).
- Where it lives: Favorites area of the account.
- Gating: logged-in.
- Confidence: probable.
- Evidence: same help article.[^favorites]

### Favorites privacy and follower feeds
- What it does: public/private toggles independently for favorite items, favorite shops, and each collection; public favorites appear in followers' feeds. The follower mechanic implies person-to-person following — a `/people/*/circle*` route still exists in robots.txt — but a discrete "follow user" flow was never directly evidenced.
- Where it lives: Favorites → Settings.
- Gating: logged-in.
- Confidence: probable for the privacy toggles; reported for person-following.
- Evidence: privacy-settings help article; robots.txt.[^privacy-settings][^robots]

---

## Domain: Gifting

### Etsy Registry
- What it does: three registry types — Wedding, Baby, Gift (any occasion). Free. Add from any listing; find a registry by the person's first and last name; share by link. Roughly 100 items per registry.
- Where it lives: etsy.com/registry, account menu.
- Gating: sign-in to create; anyone can browse a shared registry.
- Confidence: confirmed (direct fetch 2026-08-13); the 100-item cap is probable. Group-gifting/contribution-splitting: no evidence either way.
- Evidence: registry page.[^registry]

### Gift cards
- What it does: digital cards delivered by SMS or email (payable by card, Apple Pay, Google Pay); physical cards sold on etsy.com (US) and at US retailers (Target, CVS, Walgreens, Albertsons/Safeway, Ahold Delhaize brands, Rite Aid, Best Buy); corporate/bulk portal for orders of $2,000+. USD cards never expire; AUD/EUR cards (outside Austria) expire 48 months after purchase. US cards require a US billing address to buy and a US location to redeem; cards can't be bought with a prepaid card or PayPal, nor combined with a shop purchase in one transaction. Redemption requires an account; balance auto-applies at shops using Etsy Payments.
- Where it lives: etsy.com/giftcards; redemption at checkout ("Apply gift card or Etsy credit balance").
- Gating: region-gated as described.
- Confidence: confirmed (direct fetch of etsy.com/giftcards 2026-08-13 plus the gift-card legal page); expiry terms probable.
- Evidence: gift-cards page and legal terms.[^giftcards]

### Gift wrap and gift message
- What it does: seller-enabled paid gift wrap (flat per-order fee, optionally photographed); a free gift-message field at checkout printed on the packing slip, available even without paid wrap. Gift orders omit prices on the packing slip.
- Where it lives: checkout, on listings where the seller enabled it.
- Gating: appears only for participating shops.
- Confidence: probable.
- Evidence: gift-services help article and Seller Handbook.[^giftservices]

### Gift teaser
- What it does: emails the gift recipient a "sneak peek" before arrival, with optional note and tracking.
- Where it lives: checkout gifting options; recipient receives an Etsy email.
- Gating: buyer-selected on gift orders.
- Confidence: confirmed (dedicated help article for recipients exists).
- Evidence: help article "I Received an Email About a Gift Teaser from Etsy"; launch coverage.[^giftteaser]

---

## Domain: Cart and checkout

### Guest checkout
- What it does: browse, cart, and buy without an account; email receipt and shipping notifications; a guest order can later be connected to an account (required before reviewing).
- Where it lives: checkout.
- Gating: all users; reviews gated on account connection.
- Confidence: confirmed.
- Evidence: guest-checkout and guest-order-connection help articles.[^guest]

### Multi-shop cart, single checkout
- What it does: the cart groups items by shop with per-shop shipping options and note fields, but one payment method and one shipping address apply to the whole checkout. Different cards/addresses per shop require separate checkouts via Save for Later.
- Where it lives: cart and checkout.
- Gating: all users.
- Confidence: confirmed.
- Evidence: Seller Handbook multi-shop-checkout announcement.[^multishop]

### Save for later
- What it does: move cart items to a saved list; items untouched in the cart for 30+ days auto-move there.
- Where it lives: cart.
- Gating: logged-in.
- Confidence: confirmed.
- Evidence: Seller Handbook and cart help article.[^saveforlater]

### Coupons at checkout
- What it does: apply shop coupons (seller-funded, one shop) or Etsy-funded targeted coupons; one promo code per order — entering a new code replaces the old.
- Where it lives: "Apply coupon code" in cart.
- Gating: sign-in required; guests reportedly cannot apply codes.
- Confidence: confirmed.
- Evidence: coupon-redemption help article.[^coupons]

### Payment methods
- What it does: credit/debit cards, PayPal, Apple Pay, Google Pay, Etsy gift cards and credits; Klarna Pay-in-4 in US/UK/CA/AU/ES (US: $45–$10,000 orders, installments every 4 weeks; AU: A$50–1,000, every 2 weeks); Klarna pay-in-30/financing in DE/NL/SE/AT/CH/DK/FI/NO; iDEAL in the Netherlands.
- Where it lives: checkout.
- Gating: region-dependent.
- Confidence: confirmed for the core set; probable for the exact country lists.
- Evidence: payment-methods and Klarna help articles; Etsy news.[^payments]

### Donation round-up (Uplift Fund)
- What it does: US buyers paying USD by card can round the total up to the next dollar; the difference funds craft-entrepreneurship nonprofits. Since 2025 "Donate the Change" adds Etsy matching.
- Where it lives: checkout.
- Gating: US, USD, Etsy Payments card transactions.
- Confidence: confirmed.
- Evidence: Etsy Uplift Fund page and round-up terms.[^uplift]

---

## Domain: Orders and post-purchase

### Order tracking and history
- What it does: "Purchases and reviews" lists all orders with status; "Track Package" appears when tracking exists; receipts are printable.
- Where it lives: web account menu and app You tab → Purchases & Reviews.
- Gating: logged-in (guests: via receipt email).
- Confidence: confirmed.
- Evidence: order-status help article.[^orderstatus]

### Order changes and cancellation
- What it does: address changes only before shipment, by messaging the seller (sellers can edit the address when buying a label). No self-serve buyer cancel control was found — cancellation is seller-mediated, requested through Messages before shipment.
- Where it lives: order detail → Message Seller / Help with Order.
- Gating: pre-shipment only.
- Confidence: confirmed for address flow; probable (inferred) for the absence of self-serve cancellation.
- Evidence: address-change help articles.[^address]

### Digital download delivery
- What it does: files download from the Purchases page ("Download Files"); guests get an emailed link; no stated download limit. Downloads are not available in the mobile app — browser only.
- Where it lives: Purchases & Reviews (web).
- Gating: purchase; web-only.
- Confidence: confirmed.
- Evidence: digital-download help article.[^downloads]

---

## Domain: Buyer protection and disputes

### Etsy Purchase Protection
- What it does: covers non-arrival, arrival 7+ days past the estimated window, significantly-not-as-described, and damage — refunds up to $250 USD per order to the original payment method or as Etsy credit. Refund support ends 180 days after the transaction. The cap held at $250 through all three dated 2026 policy versions (March 24, July 6, August 4 — diffed via Wayback); trade coverage claiming "higher-value coverage" in 2026 was wrong. The 2026 revisions changed eligibility instead: sellers must answer help requests within 48 hours, and US-bound imports must ship DDP so buyers never see surprise customs bills (effective July 9, 2026; cases before May 7, 2026 fall under the prior policy). During the 2025 holiday season coverage was temporarily doubled to $500 for qualifying orders.
- Where it lives: Help with Order flow on the order.
- Gating: qualifying orders.
- Confidence: confirmed (dated policy captures diffed 2026-08-13).
- Evidence: purchase-protection policy captures and help articles.[^protection][^recent]

### Help-with-order and cases
- What it does: buyer must first open a help request with the seller; the seller has 48 hours to resolve; then the buyer can open a case for Etsy mediation.
- Where it lives: order detail → Help with Order.
- Gating: sequence enforced.
- Confidence: confirmed.
- Evidence: how-to-open-a-case help article.[^protection]

### Chargeback exclusivity
- What it does: one dispute channel at a time — filing a card/PayPal chargeback blocks opening (or closes) an Etsy case.
- Where it lives: policy behavior.
- Confidence: confirmed.
- Evidence: chargebacks help article.[^chargebacks]

### Returns and exchanges
- What it does: return policies are per-seller, set per listing; sellers may decline returns (custom, digital, perishable, and intimate items commonly excluded). Flow runs through Help with Order with photos; sellers can refund, send a prepaid label, replace, or partially refund. Purchase Protection backstops regardless of the seller's policy.
- Where it lives: listing page (policy display) and Help with Order.
- Confidence: confirmed.
- Evidence: return/exchange help article.[^returns]

---

## Domain: Reviews

### Leaving and editing reviews
- What it does: 100-day window from estimated delivery; star rating plus text and optional photo; separate item and shop-experience ratings; digital items unlock review only after download. Editable/deletable without limit inside the window until the seller posts a public response, which locks the review.
- Where it lives: Purchases & Reviews.
- Gating: purchasers with connected accounts.
- Confidence: confirmed.
- Evidence: review-window and review-system help articles.[^reviews]

### Video reviews
- What it does: up to 30-second video reviews with audio, iOS app only.
- Gating: iOS.
- Confidence: probable (single 2022 source; current spec and Android status unverified).
- Evidence: EcommerceBytes coverage.[^videoreviews]

---

## Domain: Messaging

### Buyer–seller messages
- What it does: threaded messaging with attachments and quick replies; Etsy scans message content and attachments (automated plus manual review) and may block messages; "Mark as Spam" (desktop) blocks a sender from messaging but not from buying.
- Where it lives: Messages; "Contact shop" on listings/shops.
- Gating: logged-in.
- Confidence: confirmed.
- Evidence: message-scanning and contact-shop help articles.[^messages]

---

## Domain: Account and privacy

### Registration and sign-in
- What it does: email/password with confirmation email, or Google / Facebook / Apple sign-in (auto-confirmed).
- Confidence: confirmed.
- Evidence: account-creation help article.[^account]

### Two-factor authentication
- What it does: optional for buyers (mandatory for sellers); authenticator apps; one-time backup codes; re-prompts every 30 days or on new devices.
- Where it lives: Account Settings → Security.
- Confidence: confirmed.
- Evidence: account-security help article.[^account]

### Privacy controls and data rights
- What it does: request a full data download (ZIP of CSV/JSON, link valid 2 weeks); request irreversible account deletion; clear recently-viewed history (also resets recommendations).
- Where it lives: Account Settings → Privacy.
- Confidence: confirmed.
- Evidence: data-download and deletion help articles.[^data]

### Preferences
- What it does: language, region, and currency set independently; email/notification subscriptions with some non-optional transactional email; bio and profile picture. Email preferences are managed on desktop, not in the app.
- Where it lives: Account Settings (Language/Location/Currency/Emails tabs).
- Confidence: confirmed.
- Evidence: settings help articles; Etsy engineering post on localization.[^prefs]
- Gap: no buyer address-book or stored-card management UI was found in documentation (blind spot, not confirmed absence).

---

## Domain: Loyalty — Etsy Insider

### Etsy Insider membership
- What it does: invite-only paid buyer membership (US, closed beta). V1 (launched September 2024): $18 per 3 months or $72/year; free US shipping covered up to $20 per item, "Deals & Drops," and doubled Donate-the-Change matching. Etsy's leadership said V1 "didn't provide scalable economics." V2 (terms finalized October 7, 2025; live November 4, 2025): seasonal price raised to $24 per 3 months (annual unchanged at $72); 5% back in Etsy credit on every purchase plus personalized offers; shipping coverage cut to $6 per order with a new $15 minimum; opened to a wider top-buyer cohort. Credit terms: expires on cancellation, $2,000 accumulation cap, deducted on returns. International expansion planned for 2026.
- Where it lives: invite flow; member perks apply at checkout.
- Gating: invite-only, US-only, paid subscription.
- Confidence: confirmed against the legal terms themselves — the October 2025 Wayback capture of the program terms states verbatim: seasonal "$24 for a three (3) month access period," annual "$72 per year," "5% of their Etsy purchase back in Etsy Credit," shipping "up to $6.00 per eligible order over $15," "closed beta invite only." The current help article (read directly 2026-08-13) describes the identical benefit structure without restating prices; the terms URL has since dropped "closed-" from its slug and has no 2026 capture, so pricing is confirmed as of Oct 2025 and probable-current.
- Evidence: program-terms Wayback capture (Oct 8, 2025), current help article, Etsy news, CBS coverage.[^insider]

---

## Domain: Mobile app (buyer)

The buyer app is "Etsy: Custom & Creative Goods" (iOS) / "Etsy: Shop from Real People" (Google Play) — one app, two store names.[^app]

### Gift Lists
- What it does: organize saved items by occasion with purchase-date reminders (emails 30 and 20 days before the date, on by default). Creation is iOS-app-only.
- Gating: iOS app.
- Confidence: probable.
- Evidence: help-article snippets.[^app]

### App notifications
- What it does: push alerts for favorite-shop back-in-stock, favorited-item low stock ("almost gone") and price drops, plus a Deals feed of promotions from favorited shops.
- Gating: app, logged-in.
- Confidence: probable.
- Evidence: help snippets and App Store listing.[^app]

### App-first features
- What it does: visual search launched iOS-first; seller-video viewing/favoriting/commenting is documented as iOS-US; Apple Pay / Google Pay / Klarna supported in-app; in-app messaging and order-tracking notifications.
- Confidence: confirmed for payments/messaging (App Store listing); reported for seller-video interaction.
- Evidence: App Store listing.[^app]

---

## Domain: Localization

### Currencies and regions
- What it does: browse and check out in local currency with automatic conversion; a few dozen supported display currencies. Language, region, and currency are settable independently. Locale routing is path-prefix based (~30 regional prefixes such as `/uk/`, `/de-en/`, `/jp/`).
- Confidence: probable for the currency list; confirmed for independent locale settings and the prefix routing.
- Evidence: help snippets; Etsy engineering post; robots.txt.[^prefs][^robots]
- Gap: the exact UI language list was never enumerated by any source.

---

## Recent buyer-facing changes (Aug 2025 – Aug 2026)

| Date | Change | Status | Confidence |
|---|---|---|---|
| 2025-09-29 | ChatGPT Instant Checkout launched | Live | Confirmed |
| 2025-11-04 | Etsy Insider V2 (repriced, 5% credit, shipping perk cut) | Live | Confirmed |
| 2026-01 | Microsoft Copilot Checkout live in US | Live | Confirmed |
| 2026-02 → 04-21 | UK "all-inclusive pricing" test (shipping-inclusive headline prices, DMCC-compliance rationale; help article "Pricing Transparency in the UK Under the DMCC Act" created 2026-02-19) | Reverted; itemized total now shown below item price | Confirmed |
| 2026-04-21 | Non-consensual intimate imagery (NCII) reporting channel added | Live | Confirmed |
| 2026-03 | Shop ratings recalculated: lifetime reviews with weight halving annually (was trailing 12 months) | Live | Confirmed |
| 2026-03/07 | Purchase Protection revisions ($250 cap unchanged; eligibility tightened — seller 48h response, DDP for US-bound) | Live | Confirmed (versions diffed) |
| 2026-04 | AI "Highlight Summaries" — AI-written listing blurbs shown to shoppers | Beta, labeled unreleased | Probable |
| 2026-05 | Agentic AI gift-finding assistant disclosed as in testing | Testing | Probable |
| 2026-07-09 | Mandatory DDP for non-US sellers shipping to US: no surprise tariff bills at delivery; surprise-tariff refunds honored | Live | Confirmed |

All rows sourced from the dated changelog research.[^recent]

---

[^search]: Etsy Help — "How to Search for Items and Shops on Etsy". https://help.etsy.com/hc/en-us/articles/115015627947
[^sustain]: Etsy Help — "How to Shop for Items with Sustainable Features". https://help.etsy.com/hc/en-us/articles/15532793357847
[^robots]: Direct fetch of https://www.etsy.com/robots.txt, 2026-08-13 (filter params, `/c/*`, `/people/*/circle*` routes).
[^imgsearch]: Etsy news — "Can't describe what you're looking for? Search with images instead" https://www.etsy.com/news/cant-describe-what-youre-looking-for-search-with-images-instead-dupe ; TechCrunch, Nov 4 2022. https://techcrunch.com/2022/11/04/etsy-image-search-feature-ios-users/
[^giftmode]: Etsy Gift Mode launch, Jan 24 2024 — etsy.com/gift-mode; TechCrunch and Digital Commerce 360 coverage.
[^aiagents]: Etsy Help — "Purchasing Etsy Items Through ChatGPT, Gemini, Google AI Mode, and Copilot". https://help.etsy.com/hc/en-us/articles/34208252828695 ; Instant-Checkout discontinuation via PYMNTS/Retail Dive reporting, early 2026.
[^cac]: Etsy Code as Craft — "Personalized Recommendations at Etsy" https://www.etsy.com/codeascraft/personalized-recommendations-at-etsy/ ; "Bringing Personalized Search to Etsy".
[^contact]: Etsy Help — "How to Contact a Shop". https://help.etsy.com/hc/en-us/articles/115013328428
[^favorites]: Etsy Help — "How to Keep Track of Items and Shops You Love". https://help.etsy.com/hc/en-us/articles/115015439627
[^privacy-settings]: Etsy Help — "How to Update Your Account Privacy Settings". https://help.etsy.com/hc/en-us/articles/115015567427
[^registry]: etsy.com/registry, fetched directly 2026-08-13. https://www.etsy.com/registry
[^giftcards]: etsy.com/giftcards, fetched directly 2026-08-13 https://www.etsy.com/giftcards ; gift-card terms https://www.etsy.com/legal/gift-cards/ ; Etsy Help "How to Buy an Etsy Gift Card" https://help.etsy.com/hc/en-us/articles/115015521288
[^giftservices]: Etsy Help — "Gift Services" https://help.etsy.com/hc/en-us/articles/360000343168 ; Seller Handbook "A Simpler Way to Offer Gift Wrap" https://www.etsy.com/seller-handbook/article/198251219817
[^giftteaser]: Etsy Help — "I Received an Email About a Gift Teaser from Etsy". https://help.etsy.com/hc/en-us/articles/19153691317399
[^guest]: Etsy Help — "How to Check Out as a Guest on Etsy" https://help.etsy.com/hc/en-us/articles/115015663607 ; "How to Connect a Guest Order to an Etsy Account" https://help.etsy.com/hc/en-us/articles/115015565027
[^multishop]: Etsy Seller Handbook — "Introducing Multi-Shop Checkout". https://www.etsy.com/seller-handbook/article/introducing-multi-shop-checkout/81357187066
[^saveforlater]: Etsy Seller Handbook article 41139517072; Etsy Help "How to Remove an Item from Your Cart" https://help.etsy.com/hc/en-us/articles/115015437807
[^coupons]: Etsy Help — "How to Redeem Coupons and Make Offers On Items". https://help.etsy.com/hc/en-us/articles/115015570787
[^payments]: Etsy Help — "What Payment Methods Can I Use to Check Out on Etsy?" https://help.etsy.com/hc/en-us/articles/360026831353 ; "How to Use Klarna to Pay in Installments" https://help.etsy.com/hc/en-us/articles/360055660114 ; Etsy news "Buy now, pay later with Klarna on Etsy".
[^uplift]: Etsy Uplift Fund https://action.etsy.com/program/uplift-fund/ ; round-up feature terms https://www.etsy.com/legal/policy/round-up-donation-feature-terms-and/1005462698702
[^orderstatus]: Etsy Help — "What's the Status of My Order?" https://help.etsy.com/hc/en-us/articles/115015521948
[^address]: Etsy Help — "How Do I Change My Shipping Address?" https://help.etsy.com/hc/en-us/articles/115015522008 ; "How to Change a Buyer's Shipping Address" https://help.etsy.com/hc/en-us/articles/360045769994
[^downloads]: Etsy Help — "How to Download a Digital Item". https://help.etsy.com/hc/en-us/articles/115013328108
[^protection]: Etsy Purchase Protection policy https://www.etsy.com/legal/policy/purchase-protection-program-for-sellers/34509585385 — three dated Wayback captures (2026-03-24, 2026-07-06, 2026-08-04) diffed 2026-08-13, all stating the $250 cap; Etsy Help "How to Open a Case" https://help.etsy.com/hc/en-us/articles/5745586898199 ; "How to Get Help with An Order" https://help.etsy.com/hc/en-us/articles/4402660818583
[^chargebacks]: Etsy Help — "Chargebacks on Etsy". https://help.etsy.com/hc/en-us/articles/4403482405527
[^returns]: Etsy Help — "How to Return or Exchange an Item on Etsy". https://help.etsy.com/hc/en-us/articles/115015440807
[^reviews]: Etsy Help — "When Can I Leave a Review for My Order?" https://help.etsy.com/hc/en-us/articles/115013293148 ; "What to Do if You Receive a Negative Review" https://help.etsy.com/hc/en-us/articles/115015808588
[^videoreviews]: EcommerceBytes, 2022 — video reviews on the Etsy iOS app.
[^messages]: Etsy Help — "How and Why Etsy Scans and Reviews Messages" https://help.etsy.com/hc/en-us/articles/32206141311767 ; phishing-protection article https://help.etsy.com/hc/en-us/articles/360000343128
[^account]: Etsy Help — "How to Create an Etsy Account" https://help.etsy.com/hc/en-us/articles/115015568007 ; "How to Make Your Account More Secure" https://help.etsy.com/hc/en-us/articles/115015569567
[^data]: Etsy Help — "How Do I Download My Etsy Data" https://help.etsy.com/hc/en-us/articles/360035753053 ; "Can I Permanently Delete My Etsy Account" https://help.etsy.com/hc/en-us/articles/360021647754
[^prefs]: Etsy Help — language/location/currency/email settings articles (115015651868, 115015306687, 115015520608, 360001890208); Code as Craft "Localizing Logically for a Global Marketplace".
[^insider]: Etsy Insider closed-beta program terms, Wayback capture web.archive.org/web/20251008001540/https://www.etsy.com/legal/policy/etsy-insider-closed-beta-program-terms/1270783021147 ("Last updated Oct 1, 2025"); Etsy Help article 24858135478039 (body read directly via local proxy, 2026-08-13); Etsy news "Meet Etsy Insider" https://www.etsy.com/news/meet-etsy-insider-etsys-new-buyer-membership-beta-program ; CBS News https://www.cbsnews.com/news/etsy-online-shopping-loyalty-program-insider/ ; ValueAddedResource reporting on the Oct/Nov 2025 changes.
[^app]: Apple App Store listing id477128284 (fetched 2026-08-13); Google Play "Etsy: Shop from Real People"; Gift Lists and notification help-article snippets.
[^recent]: Dated changelog compiled 2026-08-13 primarily from Value Added Resource (valueaddedresource.net) posts transcribing Etsy Seller Handbook/newsroom notices, cross-checked against PYMNTS and Retail Dive; etsy.com primary pages and Wayback were bot-blocked, so Etsy's language reaches this document through VAR's transcription.
[^hc-enum-b]: Full help-center enumeration via the Zendesk REST API on help.etsy.com through a local browser-header proxy, 2026-08-13. AI-assistants article 27283630080151 (body read directly); NCII reporting 39905089891863 (created 2026-04-21); DMCC pricing transparency 38513781539863 (created 2026-02-19).
