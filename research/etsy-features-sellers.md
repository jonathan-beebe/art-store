# Etsy.com — Seller Feature Inventory

Research date: 2026-08-13. Method: Track B — no authenticated Shop Manager access; reconstruction from vendor documentation (help center, Seller Handbook, legal pages), the live Open API spec, app store listings, trade-press changelogs (chiefly Value Added Resource transcribing Etsy's own notices), and seller-community reports. Confidence: **confirmed** = vendor doc or visual/machine-readable evidence; **probable** = two independent secondary sources; **reported** = single source.

Verification note: after the initial pass, 20 contested claims were re-verified against primaries fetched through a local browser-header proxy — current help.etsy.com article bodies, the Zendesk help-center API, and Wayback captures of the www.etsy.com legal pages (which block even proxied fetches directly). Rows marked with the verification footnote are primary-sourced verbatim; remaining vendor-doc claims were read through search-index extracts of Etsy's own pages (second-hand).

## Known blind spots

- **Admin and settings screens**: every Shop Manager navigation path here comes from documentation, not observation. No authenticated screen was ever seen. UI details (exact bulk-edit field list, Ads dashboard stat fields, Targeted Offers copy, custom order statuses) are unverified.
- **Tier-gated detail**: Etsy Plus perks are documented, but how gated surfaces actually render (advanced banner editor, restock-request management) was never observed.
- **Anything shipped in the last few months** without a Seller Handbook post or press coverage — mobile-only releases are especially dark (app release notes are generic).
- Still open after verification: Shop Updates retirement date, preorder/waitlist seller flow, shop-page video, review-analytics panel, carrier-by-country label matrix, the "Updates to Handling and Package Fees" article (29186673194135, unread), and the Star Seller 10→5 order-minimum history. Resolved by verification: setup fee (no published amount by design), Make an Offer scope (no longer vintage-only), reserve percentage (account-specific, undisclosed), chargeback fee (none documented), Search Analytics (superseded by Marketplace Insights), listing-video spec (3–15s), Germany EPR (dedicated article created 2026-08-04).

---

## Domain: Shop setup and onboarding

### Opening a shop
- What it does: guided flow collecting shop name, at least one listing, bank details, government ID, and a credit card. A one-time, non-refundable setup fee may be charged during setup. The canonical fees policy publishes **no dollar amount**: "Cost varies by location… the set-up fee will be displayed and charged as part of the shop set-up process… may be waived at our sole discretion." The $15/$29 figures circulating in secondary sources are observations, not policy.
- Where it lives: etsy.com "Sell on Etsy" flow.
- Gating: all new sellers; amount location-dependent, sometimes waived.
- Confidence: confirmed (Fees & Payments Policy, "Last updated Feb 13, 2026", Wayback capture 2026-06-26). Last evidenced: 2026-06-26.
- Evidence: fees policy; help article "How to Open an Etsy Shop".[^onboard][^verified]

### Identity verification
- What it does: government photo-ID verification (run through Persona, a third-party KYC vendor) plus bank and tax-info verification; a 90-day completion deadline or the shop closes. Since December 12, 2025 Etsy's Payments Policy also authorizes third-party verification providers to check seller info against public records and credit reports.
- Where it lives: onboarding prompts; Shop Manager → Finances.
- Gating: new sellers and risk-flagged accounts; INFORM-Act re-verification for high-volume US sellers (see Compliance).
- Confidence: confirmed for the Persona flow; probable for the 90-day deadline.
- Evidence: help articles on identity verification and seller-info confirmation; Dec 2025 Payments Policy update.[^onboard][^payments-policy]

### What may be sold — Creativity Standards
- What it does: items must be handmade, designed by the seller, vintage (20+ years), or craft supplies. The July 9, 2024 Creativity Standards replaced the binary "handmade" rule with four per-listing disclosure categories: **Made by**, **Designed by**, **Handpicked by**, **Sourced by** a seller. A June 10, 2025 tightening required that items made with computerized tools (3D printers, CNC, Cricut) embody the seller's own original design — third-party templates no longer qualify — banned digital scans of vintage material absent original work or licensing, banned reselling unaltered natural items, and narrowed the craft-supply category. AI use must be disclosed in the listing description; selling AI prompt bundles is prohibited. Prohibited-items list bans weapons and mimics, hazardous materials, recalled items, financial instruments, most services, and metaphysical services; a scheduled revision took effect August 11, 2026 — the same date the fur ban (animals killed primarily for pelts, closing the prior vintage exemption; leather, wool, sheepskin, taxidermy excluded) became effective.
- Where it lives: listing creation (creation-method fields); etsy.com/legal/creativity and /legal/prohibited.
- Gating: all sellers, retroactive to existing listings.
- Confidence: confirmed. Last evidenced: 2026-08.
- Evidence: Creativity Standards and prohibited-items policy pages; June 2025 enforcement coverage; fur-ban coverage.[^creativity][^recent]

### Seller referral program
- What it does: refer-a-friend flow for bringing new sellers to Etsy.
- Confidence: confirmed (dedicated help article; body unread; incentive terms unknown).
- Evidence: help article "How to Refer a Friend to Sell on Etsy".[^hc-enum]

---

## Domain: Listings

### Listing editor
- What it does: title (≤140 characters), up to 13 tags, category selection driving a dynamic attribute set, description, price, quantity. The Materials tag was removed in April 2026. AI-suggested attributes ("About this listing") propose values from the category, description, and featured photo. An AI title-suggestion tool shipped August 2025 alongside revised title guidance (~15 words, lead with the item noun).
- Where it lives: Shop Manager → Listings → create/edit.
- Gating: all sellers.
- Confidence: confirmed (Materials removal and AI tools via the dated changelog).
- Evidence: help article "How to Create a Listing"; Seller Handbook AI post; April 2026 Shop Manager update coverage.[^listing][^ai-tools][^recent]

### Photos and video
- What it does: up to 20 photos per listing (raised from 10 around August 2025); up to 2 videos per listing, 3–15 seconds, silent after upload, maximum 100MB, minimum 500px resolution (1080px recommended).
- Gating: all sellers.
- Confidence: video spec confirmed (help article read directly 2026-08-13, resolving the 3s-vs-5s conflict at 3–15s); photo cap probable.
- Evidence: listing-videos help article; secondary guides for the photo cap.[^listing][^verified]

### Variations and personalization
- What it does: up to 2 variation types with up to 70 options each; each combination can carry its own price (entered as the variant's total price), quantity, processing profile, and SKU. Personalization toggle adds a buyer text field with seller-set instructions, character limit, and optional/mandatory flag.
- Where it lives: listing editor.
- Gating: all sellers.
- Confidence: confirmed.
- Evidence: variations and personalization help articles.[^listing]

### Digital listings
- What it does: instant-download (file delivered on purchase) or made-to-order digital (order stays open until the seller uploads the finished file). Buyers download via the web only.
- Gating: all sellers.
- Confidence: confirmed.
- Evidence: digital-listings help article and Seller Handbook.[^listing]

### Lifecycle: renewal, deactivation, states
- What it does: listings run 4 months at $0.20; auto-renew by default, switchable to manual. Deactivation hides a listing without deleting it (the 4-month clock keeps running). States: Active, Draft, Expired, Sold out, Inactive, Featured. Bulk editing batch-changes fields across selected listings. Stable listing URLs shipped April 2026.
- Where it lives: Shop Manager → Listings.
- Gating: all sellers.
- Confidence: confirmed (bulk-edit field-by-field limits unverified).
- Evidence: renewal/deactivation help articles; Listings Manager Seller Handbook post; April 2026 update.[^listing][^recent]

### Local pickup and delivery
- What it does: listings can offer in-person local pickup or local delivery as fulfillment options, distinct from shipped orders.
- Where it lives: listing editor delivery options.
- Gating: all sellers.
- Confidence: confirmed (dedicated help article; body unread).
- Evidence: help article "How to Offer Local Pickup or Delivery".[^hc-enum]

### Image alt text
- What it does: text alternatives can be added to listing images for screen-reader accessibility.
- Where it lives: listing editor → photos.
- Gating: all sellers.
- Confidence: confirmed (dedicated help article; body unread).
- Evidence: help article "How to Add a Text Alternative to Your Listing Images".[^hc-enum]

### Cross-border pricing tools
- What it does: US-specific pricing (October 2025) lets non-US sellers set separate US-facing prices — desktop-only, bulk-editable up to 500 listings — to bake in duties. A US Tariff Calculator beta (June 2026, Zonos-powered) suggests HS codes and estimates duties in the Pricing & Shipping tab; advisory only, no variation-listing support. Regional pricing (domestic/global/US-specific) has a dedicated help article.
- Where it lives: listing editor → Pricing & Shipping.
- Gating: non-US sellers (US pricing); beta cohort (tariff calculator).
- Confidence: confirmed.
- Evidence: help article on domestic/global/US pricing; changelog coverage.[^tariff][^recent]

### Translation
- What it does: manual translation of shop and listings per language, alongside machine translation; the API exposes per-language listing translations.
- Confidence: confirmed.
- Evidence: translation help article; Open API translation endpoints.[^listing]

---

## Domain: Inventory

### Quantity and SKU tracking
- What it does: quantity per listing or per variation option (prevents overselling); free-text SKUs (~32 chars, internal only). There is **no native low-stock alert** for sellers — the only signal is the buyer-facing "Only X left" badge; sellers use third-party tools (Craftybase, Sumtracker) for reorder points.
- Where it lives: listing editor → Manage Inventory.
- Gating: all sellers; restock *requests* (back-in-stock buyer signups) are Etsy Plus (see Subscriptions).
- Confidence: confirmed for tracking; probable for the no-alert gap.
- Evidence: variations article; secondary inventory guides.[^listing]

---

## Domain: Orders and fulfillment

### Orders & Shipping dashboard
- What it does: central queue with buyer info, order/payment status, label purchase, and buyer messaging; tabs for Open / In Progress / Completed / Cancelled (custom statuses are single-source, unconfirmed). An updated "what to ship and when" fulfillment view shipped in Fall 2025.
- Where it lives: Shop Manager → Orders & Shipping.
- Gating: all sellers.
- Confidence: confirmed.
- Evidence: dashboard help article; Fall 2025 What's-New.[^orders][^recent]

### Packing slips, receipts, gift orders
- What it does: print packing slips and order receipts per order; gift orders omit prices, print the buyer's gift message on the slip, and carry a gift badge; Etsy sends the buyer-selected gift teaser itself. Sellers can offer paid gift wrap (flat fee, optional photo); a free gift-message option always exists.
- Where it lives: order detail → More actions → Print.
- Gating: gift wrap is seller-opt-in.
- Confidence: confirmed.
- Evidence: packing-slip and gift-services help articles.[^orders]

### Refunds and cancellations
- What it does: seller-initiated full or partial refunds (3–5 days to land); refunding does not cancel — cancellation is a separate seller-only action that triggers a full refund. Refunds can also be issued from the Seller app and from within Messages.
- Where it lives: order detail; Seller app; Messages.
- Gating: sellers only — no buyer self-serve cancellation was found.
- Confidence: confirmed.
- Evidence: cancellation help article; "Newly Crafted" update article.[^orders]

### Order annotations
- What it does: seller-only private notes per order; buyer notes and private notes now surface as buttons at the top of the order (a recent UI change). Personalization text shows on the order and packing slip. No native one-click combine-orders flow — combining is manual (one label, copy tracking to sibling orders).
- Confidence: probable (private-note UI change is a single community report).
- Evidence: community threads; tracking help article.[^orders]

---

## Domain: Shipping

### Shipping profiles and calculated shipping
- What it does: reusable shipping-rule templates; calculated shipping for US/Canada sellers (USPS, Global Postal Shipping, Canada Post) computed from origin, destination, and package size/weight.
- Where it lives: Shop Manager → Settings → Shipping.
- Gating: calculated shipping is US/CA only.
- Confidence: confirmed.
- Evidence: calculated-shipping help article.[^shipping]

### Shipping labels
- What it does: in-platform label purchase — USPS, UPS, FedEx, Global Postal Shipping documented directly; Canada Post, Evri, Royal Mail, Australia Post via aggregators (country matrix unverified). Discounts up to ~30% off USPS retail and ~35% off UPS (single-source). Automatic coverage: up to $100 on USPS Priority/Express/Ground Advantage and FedEx, $200 on USPS Priority/Express International. Optional Shipsurance insurance to $5,000 with claims filed directly with Shipsurance. Labels auto-attach tracking; manual tracking entry marks orders shipped. Customs forms auto-generate for international labels; the seller is responsible for accuracy including HS codes. As of July 2026 Canada Post and USPS labels were temporarily unavailable for some EU destinations (new EU handling fee), with Global Postal Shipping as the workaround.
- Where it lives: order detail → Buy shipping label.
- Gating: country-dependent.
- Confidence: confirmed for USPS/UPS/FedEx/insurance/customs; probable for the full carrier list; reported for the discount percentages and the July 2026 disruption.
- Evidence: label, insurance, and international-shipping help articles.[^shipping]

### Delivery-date machinery
- What it does: processing times and reusable processing profiles ("ship by" dates), feeding buyer-facing estimated delivery dates (processing + carrier transit).
- Where it lives: listing editor and shipping settings.
- Confidence: confirmed.
- Evidence: processing-times and estimated-delivery help articles.[^shipping]

### Free Shipping Guarantee (US)
- What it does: opt-in free US shipping on orders $35+; participating listings get priority placement in US search; auto-applies to new $35+ listings once enabled; open to international sellers shipping to the US. A smart-pricing tool bulk-adjusts $35+ listing prices to absorb the cost.
- Where it lives: Shop Manager → Free shipping guarantee.
- Gating: opt-in; US-bound orders.
- Confidence: confirmed.
- Evidence: Seller Handbook and help articles.[^shipping]

### Mandatory DDP for US-bound imports
- What it does: since July 9, 2026 all non-US sellers shipping to US buyers must ship Delivered Duty Paid — tariffs prepaid or baked into price. Non-compliant orders lose Purchase Protection; a buyer surprise-billed at delivery gets refunded at the seller's expense.
- Gating: non-US sellers shipping to the US.
- Confidence: confirmed. Last evidenced: effective 2026-07-09.
- Evidence: changelog coverage of Etsy's notice.[^recent]

---

## Domain: Messages

### Seller messaging tools
- What it does: one thread per buyer; saved replies (categorized templates); auto-reply for 1 hour–5 days with a temporary announcement (beyond that, vacation mode); message labels including a Help Requests label; no auto-deletion. The Seller app tags repeat buyers in Messages. A "Top Buyer" badge test (November 2025) flags top-15%-by-spend US buyers to sellers.
- Where it lives: Shop Manager → Messages.
- Gating: all sellers; Top Buyer badge is a test cohort.
- Confidence: confirmed for the core; probable for repeat-buyer tags and the Top Buyer test.
- Evidence: messages/vacation help articles; changelog.[^messages][^recent]

### Help requests
- What it does: buyers must open a help request before a case; the seller gets 48 hours to resolve before escalation to Etsy is possible.
- Where it lives: Messages (Help requests label).
- Confidence: confirmed.
- Evidence: "How to Answer a Help Request from a Buyer".[^messages]

### Writing Assistant
- What it does: AI drafting of buyer-message replies "in your own voice"; broad rollout September 2, 2025, on desktop and the Seller app. Etsy cites 27% higher conversion when sellers reply within 2 days.
- Gating: all sellers (post-rollout).
- Confidence: confirmed.
- Evidence: Seller Handbook via changelog.[^ai-tools][^recent]

---

## Domain: Shop presentation and structure

### Storefront elements
- What it does: shop name/URL, icon/logo, single static banner (advanced layouts are Etsy Plus), announcement and shop title, About section with photos/video slots, shop sections (categories), featured listings. Whether a shop-page video distinct from listing video exists is unconfirmed.
- Where it lives: Shop Manager → Settings / "Your Shop → Edit shop".
- Gating: all sellers; advanced customization gated (see Subscriptions).
- Confidence: confirmed for the elements; the exact current Edit-shop field list was never observed.
- Evidence: storefront/customization help articles.[^storefront]

### Shop members and production partners
- What it does: public About-page profiles for shop members with nine preset roles (Owner, Assistant, Maker, Curator, Customer Service, Designer, Marketer, Photographer, Shipper) plus custom roles — these do **not** grant Shop Manager login access. Production partners (outside manufacturers producing the seller's own design) must be disclosed; managed in settings and exposed read-only in the API.
- Where it lives: Shop Manager → Settings → About / Production Partners.
- Gating: disclosure mandatory when applicable.
- Confidence: confirmed.
- Evidence: shop-member-roles and production-partner help articles.[^storefront]

### Shop policies
- What it does: structured policy sections — Shipping, Payment options, Returns & exchanges, Cancellations, Privacy, plus free-form FAQs. Return policies are set per listing, with a 30-day simple template offered. Policies are not editable from the Seller app.
- Where it lives: Shop Manager → Settings → Policies.
- Confidence: confirmed.
- Evidence: shop-policies and listing-return-policy help articles.[^storefront]

### Vacation mode, closing, multiple shops
- What it does: vacation mode hides the shop without closing it (banner shown, pairs with auto-reply); close-shop is a separate flow with a reason dropdown; reopening restores listings to search within ~10 minutes. Multiple shops are allowed with no cap, but each needs its own account and email, and all other shops must be disclosed on the public profile.
- Where it lives: Shop Manager → Settings → Options.
- Confidence: confirmed; multiple-shop rules probable.
- Evidence: vacation/close/reopen help articles; "How to Open a Second Shop".[^storefront]

### Pattern by Etsy
- What it does: standalone-website builder fed by the Etsy shop — $15.00/month after a 30-day free trial, per the Feb 2026 fees policy. Active as of 2026 (the policy bills it, and the help center carries a 22-article Pattern section), though community reports describe it as under-maintained. Domain purchases through Pattern go via partner Tucows.
- Where it lives: separate product; Pattern section in the help center.
- Gating: separate subscription.
- Confidence: confirmed (fees-policy capture 2026-06-26 + help-center enumeration).
- Evidence: fees policy; Pattern help articles.[^pattern][^verified]

### Custom domain redirect
- What it does: sellers can redirect an owned domain to their Etsy shop. The former Etsy Plus Hover discount (50% off select TLDs, first year) no longer appears in any current documentation — consistent with the reported February 21, 2026 signup cutoff — and the disclosed domain partner is now Tucows.
- Confidence: confirmed for the redirect and the discount's absence from current docs (2026-08-13).
- Evidence: domain-redirect help article; fees policy; Hover support (historical).[^plus][^verified]

---

## Domain: Marketing and advertising

### Etsy Ads (onsite)
- What it does: daily-budget model ($1 minimum), sealed generalized second-price auction with Etsy setting per-listing bids automatically; placements across search, category pages, and similar-listing modules on web and app with an "Ad" badge; per-listing on/off toggles; performance stats (impressions, clicks, spend, attributed orders). An "Ads Strategies" beta (from ~Aug 2025) adds three bid-optimization modes — Increase Orders / Balance / Increase Return — gated behind a $25/day minimum budget. In October 2025 Etsy ran an unannounced promo covering CPC costs on selected listings with no seller opt-out.
- Where it lives: Shop Manager → Marketing → Etsy Ads.
- Gating: all sellers; Ads Strategies is beta.
- Confidence: confirmed for the core; probable for CPC ranges (~$0.20–0.50), the beta, and the promo.
- Evidence: Etsy Ads help articles; ad-placement article; changelog.[^ads][^recent]

### Offsite Ads
- What it does: Etsy-run ads on Google, Bing, Facebook, Instagram, Pinterest, YouTube, and publisher placements; the seller pays only on attributed sales within a 30-day click window — 15% of order total under $10k trailing-365-day sales (opt-out allowed), 12% at $10k+ (mandatory for the life of the shop once crossed), capped at $100 per order.
- Where it lives: Shop Manager → Settings → Offsite Ads.
- Gating: threshold-driven mandatory tier.
- Confidence: confirmed.
- Evidence: Offsite Ads help article; fees policy.[^offsite]

### Sales, coupons, targeted offers
- What it does: run sales (percentage off or free standard shipping, scoped by region and duration); promo codes (5–75% whole-number, fixed amount, or free shipping; scoped to listings; one-use-per-buyer option); automated targeted offers to buyers who abandoned a cart (24h+ in cart), recently favorited (48h after), or completed a purchase (thank-you coupon) — the seller sets the discount, Etsy controls timing and delivery. Etsy also issues Etsy-funded coupons redeemable in shops at no seller cost.
- Where it lives: Shop Manager → Marketing → Sales and Discounts / Targeted Offers.
- Gating: all sellers.
- Confidence: confirmed for sales/coupons and Etsy-funded coupons; probable for targeted-offer trigger mechanics.
- Evidence: sales-and-discounts and Etsy-funded-coupon help articles; secondary triangulation.[^promos]

### Share & Save
- What it does: 4% credit against Etsy fees on sales attributed to the seller's own trackable share links (30-day attribution), effectively cutting the transaction fee from 6.5% to 2.5% on those orders.
- Where it lives: Shop Manager sharing tools.
- Gating: opt-in, all sellers.
- Confidence: confirmed.
- Evidence: Share & Save program terms and help article.[^shareandsave]

### Social media tool
- What it does: connect Facebook, Instagram, Pinterest (X also cited); templated post types (new listings, milestones) pushed to connected accounts; per-listing "share listing" with photo/video selection. Meta's Commerce Manager partnership (in-app Instagram product tagging) is gone.
- Where it lives: Shop Manager → Marketing → Social Media.
- Confidence: confirmed for the tool; probable for the Meta retirement.
- Evidence: share-to-social help article.[^promos]

### Make an Offer
- What it does: opt-in price negotiation on **some or all of a seller's listings** — the March 2023 vintage-only launch scope no longer applies; the current help article states it works on any listing the seller enables. Per-listing enable with a minimum-price floor; offers arrive in Messages; accept/counter/decline with a 48-hour buyer window on counters. Still USD-currency shops only and not available in the Seller app.
- Where it lives: Shop Manager (web) → listing settings.
- Gating: USD shops, web-only, per-listing opt-in.
- Confidence: confirmed (current help article read directly 2026-08-13; corrects the vintage-only claim).
- Evidence: Make an Offer help article; launch coverage for the 2023 history.[^offer][^verified]

### Affiliates and Creator Collective
- What it does: Etsy runs an affiliate program and a "Creator Collective" for content creators driving traffic; documented in the help center.
- Confidence: confirmed to exist (article title); mechanics unread.
- Evidence: help article "The Etsy Affiliates Program and Creator Collective".[^promos]

---

## Domain: Analytics

### Shop Stats
- What it does: visits, orders, conversion rate, revenue over selectable ranges; traffic-source breakdown (Etsy search, Etsy Ads, Offsite Ads, direct, social, external search, email); listing-level views/favorites/revenue; buyer search terms.
- Where it lives: Shop Manager → Stats.
- Gating: all sellers.
- Confidence: confirmed.
- Evidence: Shop Stats help articles and glossary.[^stats]

### Marketplace Insights (supersedes Search Analytics)
- What it does: direct access to real Etsy search data — demand trends and keyword research; 15 free keyword searches per week, unlimited with Etsy Plus. Rolled out fall 2025. The older Search Analytics tool is gone: its help article (360001947367) now 404s at the API level, and the Marketplace Insights article contains no "beta" label — resolving the earlier ambiguity as **superseded**.
- Where it lives: Shop Manager (Growing Your Shop tooling).
- Gating: metered free tier; Plus unlocks unlimited.
- Confidence: confirmed (article body read directly 2026-08-13).
- Evidence: help article "How Do I Use Etsy's Marketplace Insights Tool?" (35122361353239); deleted Search Analytics article.[^stats][^verified]

---

## Domain: Reputation

### Star Seller
- What it does: monthly badge computed on a rolling 3-month window: ≥95% first-message response within 24 hours (saved replies count), ≥95% on-time ship with carrier-scanned tracking, ≥4.8 average rating, plus minimum activity (≥5 orders and ≥$300 sales; the shop's first sale ≥90 days old). Badge shows on shop and search; buyers can filter to Star Sellers. Etsy states the badge itself doesn't boost rank but feeds the Customer Experience Score, which does. Perks: live-chat support and reserve exemption. A single-source claim says the order minimum dropped from 10 to 5 "starting in July" (year unconfirmed).
- Where it lives: Shop Manager → Customer service stats.
- Gating: performance-based.
- Confidence: confirmed on thresholds (heavily corroborated); reported for the 10→5 history.
- Evidence: Star Seller help articles; ranking-disclosure page.[^star]

### Reviews (seller side)
- What it does: public response to any review within 100 days of the buyer's last edit; responding locks the buyer's rating (changes then require Etsy support). Reviews/photos/videos/responses reportable for policy violations, confidentially. Since March 2026 the displayed shop rating uses lifetime reviews with each review's weight halving annually (previously trailing 12 months); Star Seller math is unaffected. No distinct review-analytics panel was found.
- Where it lives: Shop Manager → Reviews.
- Confidence: confirmed.
- Evidence: review-system and review-reporting help articles; March 2026 recalc coverage.[^star][^recent]

---

## Domain: Finances

### Etsy Payments and payouts
- What it does: unified payment processing; native in most seller countries, extended via Payoneer to ~16 more (Argentina through UAE). Deposit schedule configurable — daily (country minimums, US $15), weekly (default), biweekly, monthly; deposit currency set by the bank's country. Instant Transfer beta (US, from Nov 5, 2025): payout in ~30 minutes for 1% ($0.25–$1.00), $500/request, one per 24h, $25 minimum.
- Where it lives: Shop Manager → Finances → Payment account.
- Gating: country eligibility; Instant Transfer is US beta.
- Confidence: confirmed.
- Evidence: Etsy Payments, deposit, and Payoneer help articles; Instant Transfer coverage.[^finances][^recent]

### Payment account reserves
- What it does: a portion of sale funds held until shipment confirmation or a hold period. The current help article publishes **no universal percentage**: "the reserve holding time and percentage… can vary based on your account," with removal "within 90 days" for most sellers. Star Sellers are exempt ("If you're already a Star Seller, a reserve will not be placed on your shop"). The 75%→30% history (2023) comes from secondary reporting; today's terms are account-specific and undisclosed. Triggers: recent first sale, sales spikes, missing tracking on $100+ orders, policy issues.
- Where it lives: Payment account (reserve shown separately).
- Gating: risk-based.
- Confidence: confirmed (article body read directly 2026-08-13); historical percentages probable.
- Evidence: reserve help article; Seller Handbook; Indie Sellers Guild.[^finances][^verified]

### Statements, invoices, exports
- What it does: running payment-account ledger (sales, fees, refunds, reserves, Etsy-remitted tax lines, deposits); monthly statements generated the 1st; VAT invoices for ~13 countries; CSV export of sold transactions (a dedicated help article exists: "How to Download a Spreadsheet of Your Sold Transactions"). A billing card on file covers fees where the payment balance doesn't.
- Where it lives: Shop Manager → Finances.
- Confidence: confirmed.
- Evidence: finances help articles; CSV-download article.[^finances]

### Payment legal entities (2025–2027 restructuring)
- What it does: Canadian seller funds are held and disbursed by **Etsy Canada Limited** in a dedicated trust account under Canada's Retail Payment Activities Act (effective September 8, 2025). EEA sellers are being migrated (rolling through early 2027) to **Etsy Payments Ireland Limited**, a regulated EEA payment entity, and asked to reconfirm shop info and accept new terms.
- Where it lives: prompts in Shop Manager / Payment account.
- Gating: Canada and EEA sellers respectively.
- Confidence: confirmed (article bodies fetched directly).
- Evidence: help articles "What is RPAA?" and "What is EPIL?".[^hc-enum]

### Found business checking
- What it does: invite-only partnership giving select US sellers a Found business checking account — banking (direct deposit, debit and virtual cards, mobile check deposit), real-time tax estimates and auto-withholding, Schedule C generation, bookkeeping — with a $100 bonus for $2,500 deposited within 120 days. Signup from Shop Manager → Finances → Payment settings.
- Gating: invite-only, US.
- Confidence: confirmed (article body fetched directly). Last evidenced: article updated 2026-08-03.
- Evidence: help article "What is Found?".[^hc-enum]

### YouLend cash advances
- What it does: revenue-linked cash advances from third-party provider YouLend for sellers in the US, UK, France, Germany, and Poland — repayments scale with sales and pause if sales stop.
- Gating: eligibility-based, five countries.
- Confidence: confirmed (article body fetched directly). Last evidenced: article updated 2026-08-04.
- Evidence: help article "How to Get a Cash Advance Through YouLend".[^hc-enum]

### Finance and accounting integrations
- What it does: documented integrations for Square (sell in person through an Etsy-linked Square POS), Xero, and QuickBooks Self-Employed/TurboTax bookkeeping.
- Gating: all sellers.
- Confidence: confirmed (dedicated help articles exist; bodies unread).
- Evidence: help articles "How to Use Square in Your Etsy Shop", "How to Use Xero on Etsy", "Using QuickBooks Self-Employed and TurboTax on Etsy".[^hc-enum]

### Fee schedule
- What it does (verified against the canonical Fees & Payments Policy, "Last updated Feb 13, 2026", Wayback capture 2026-06-26, and current help articles read directly 2026-08-13):
  - Listing fee: $0.20 per listing per 4 months, auto-renew default.
  - Transaction fee: 6.5% of listed price plus shipping and gift wrapping.
  - Payment processing (verbatim from the current ~50-country table): US 3% + $0.25; UK 4% + £0.20; most Eurozone 4% + €0.30; Canada 3% + $0.25 CAD domestic / 4% international; Australia 3%/4% + $0.25 AUD; India 5% + ₹25; Japan 6.0% + $0.30; China/Brazil and others 6.5% + $0.30; Türkiye 6.5% + 14 TRY.
  - Offsite Ads: 15% under $10,000 trailing-365-day sales (opt-out allowed), 12% at/over $10,000 for the lifetime of the shop, $100 cap per attributed order.
  - Currency conversion: 2.5%.
  - Regulatory Operating Fee by shop country: Canada 0.50%, France 1.14%, Hungary 1.97%, India 0.05%, Italy 0.80%, Spain 0.88%, Türkiye 1.67%, UK 0.48%, Vietnam 1.24%. (Corrects the earlier VAR-derived Canada figure of 1.15%.)
  - Deposit fees: per-country minimum/threshold/fee tables published (e.g., Türkiye: 50 TRY minimum, 42 TRY fee below the 600 TRY threshold); US deposit minimum $25 for daily schedules.
  - Also on the canonical page: Pattern $15.00/month after a 30-day free trial; a $0.20 "Square manual" fee for non-synced in-person sales; Instant Transfer 1%; Etsy Plus $10/month; the variable shop setup fee (no published amount).
  - Since December 12, 2025, sales tax applies to certain seller fees.
- Confidence: confirmed (primary-sourced). Gap: the "Updates to Handling and Package Fees" article (29186673194135) exists in the taxonomy but was not read.
- Evidence: Fees & Payments Policy capture; payment-processing, regulatory-fee, currency-conversion, and deposit help articles.[^fees][^verified]

---

## Domain: Taxes and compliance

### Tax handling
- What it does: US 1099-K at the restored federal >$20,000 AND >200-transaction threshold for tax year 2025 onward (OBBBA, July 2025), with lower state thresholds in some states. Etsy is marketplace facilitator for US sales tax in every sales-tax state plus DC and PR. Etsy collects/remits import VAT on EU orders ≤€150, UK VAT ≤£135, AU GST ≤A$1,000, and VAT/GST on digital downloads across many jurisdictions regardless of value. VAT is charged on Etsy's own fees for sellers in VAT countries. Since December 2025 Etsy's collection-agent role applies to sellers globally.
- Where it lives: automatic; visible as ledger line items; Legal & tax info settings.
- Confidence: confirmed for mechanisms and the 1099-K reversal; thresholds probable.
- Evidence: 1099-K, sales-tax, and VAT help articles; Payments Policy update.[^tax][^payments-policy]

### Regulatory tooling
- What it does: EU GPSR fields (Responsible Person, safety info) on EU/NI-facing listings since December 2024, with geo-restriction or removal for non-compliance; EPR registration requirements for France, and — per dedicated help articles created August 4, 2026 — Germany and Spain; INFORM Consumers Act verification for high-volume US sellers (200+ transactions and $5k+ GMV) with annual re-verification; US CPSC eFiling compliance guidance (article created July 9, 2026); EU Platform-to-Business Regulation and India Consumer Protection (E-Commerce) Rules documented as applicable regimes; DSA trader-traceability obligations are Etsy-side with no seller-facing dashboard found.
- Where it lives: listing compliance fields; account verification prompts.
- Confidence: confirmed for GPSR/EPR (FR/DE/ES)/INFORM/CPSC (article existence via full help-center enumeration); probable for DSA.
- Evidence: GPSR Seller Handbook FAQs; EPR, INFORM, and CPSC help articles.[^compliance][^hc-enum]

---

## Domain: Trust, cases, and account health

### Purchase Protection for sellers
- What it does: for qualifying cases (not received, late, not-as-described) Etsy refunds the buyer up to **$250** without charging the seller — automatic, no enrollment. The cap did not move in 2026: the March 24, July 6, and August 4, 2026 policy captures all state $250 (the "higher-value coverage" phrasing in trade coverage was wrong). What changed is eligibility — the version effective July 9, 2026 adds two requirements: respond to the buyer's Help with Order message within 48 hours while meeting minimum Customer Service Standards, and use DDP shipping for US-bound orders. Cases opened before May 7, 2026 fall under the prior policy. Coverage was temporarily doubled to $500 for qualifying 2025-holiday orders.
- Where it lives: applied at case resolution.
- Gating: eligibility list in the policy (valid tracking, compliant listing, 48h response, DDP for US-bound).
- Confidence: confirmed (three dated policy versions diffed via Wayback, 2026-08-13).
- Evidence: seller-protection help article and dated policy captures.[^cases][^verified]

### Chargebacks
- What it does: Etsy notifies the seller and requests evidence; qualifying chargebacks fall under the $250 protection; losses debit the Payment account. The current help article contains **no chargeback fee** — the fee rumored in secondary sources is not documented anywhere in the fetched primaries.
- Confidence: confirmed (article body read directly 2026-08-13).
- Evidence: chargeback help article.[^cases][^verified]

### Account health and enforcement
- What it does: a Policy Violations Dashboard (shipped fall 2025) tracks removed listings and warnings; a Listing Appeal Pilot covers Creativity Standards removals (post-July 15, 2025). Suspensions are temporary (resolvable) or permanent; permanent suspensions appealable within 6 months, decided in ~2 weeks. IP reporting portal at etsy.com/legal/ip/report (1-business-day action target, status tracking, DMCA counter-notice flow).
- Where it lives: Shop Manager → Settings → Legal & Compliance; help-center appeal form; legal portal.
- Confidence: confirmed for appeals/IP portal and the dashboard's launch; probable for dashboard detail.
- Evidence: appeal and IP help articles; Holidays 2025 bundle.[^cases][^recent]

---

## Domain: Subscriptions and programs

### Etsy Plus
- What it does: $10/month — 15 listing credits (worth $3) and $5 Etsy Ads credit monthly, restock-request signups on sold-out listings, advanced shop customization (carousel banner up to 4 linkable images, collage banners, mixed-grid featured layouts, auto color themes — new themes added fall 2025), unlimited Marketplace Insights searches. The Hover domain discount is **gone from current documentation** — no Etsy Plus page mentions a domain perk, and Etsy's disclosed domain-registration partner as of the Feb 2026 fees policy is Tucows (in the Pattern fees section). Etsy Premium, announced in 2018 for 2019, never demonstrably launched.
- Where it lives: Shop Manager → Settings → Subscriptions.
- Gating: paid tier.
- Confidence: confirmed (fees-policy capture + current help articles, 2026-08-13); Premium's fate unresolved.
- Evidence: Etsy Plus help articles; fees policy.[^plus][^verified]

### Community and education
- What it does: Seller Handbook (announcement channel); Community Hub — forums, Teams with captains, events — migrated from Khoros to Bevy in October 2025 (AI search, badges; access restricted to active sellers since 2024); Etsy Up annual seller conference (2025 edition September 18); Etsy Design Awards; 24/7 live seller support added for Holidays 2025 (live chat otherwise a Star Seller perk).
- Confidence: confirmed.
- Evidence: community help articles; changelog.[^community][^recent]

### Retired: Shop Updates
- What it does: the photo-post "Shop Updates" feature is retired (authoring lived in the legacy Sell on Etsy app); no official retirement date exists, and a "Newly Crafted: Etsy Updates for Your Shop" help article suggests a renamed successor concept — ambiguous.
- Confidence: probable.
- Evidence: community reports; help article 10603291042967.[^community]

---

## Domain: Seller mobile app

### Etsy Seller app
- What it does: "Etsy Seller: Manage Your Shop" (iOS 2.4.1 at capture, ~58k ratings). App-verified capabilities: barcode label scan opening the matching order; in-app US label purchase; listing photo/video capture with editing (Spring 2025: background processing, advanced editing, saveable custom filters); full category tree in-app (Spring 2025); push notifications for purchases, messages, favorites; repeat-buyer tags; refund issuance. Not in the app: Make an Offer, policy editing, Shop Updates authoring.
- Gating: all sellers.
- Confidence: confirmed for the app and Spring-2025 features; probable for repeat-buyer tags.
- Evidence: App Store listing; Seller Handbook Spring 2025 What's-New.[^app]

---

## Recent seller-facing changes (Aug 2025 – Aug 2026)

| Date | Change | Status | Confidence |
|---|---|---|---|
| 2025-08 | Ads Strategies beta (3 bidding modes, $25/day min) | Beta | Probable |
| 2025-08-21 | AI title suggestions + revised title guidance | Live | Confirmed |
| 2025-09-02 | Writing Assistant broad rollout | Live | Confirmed |
| 2025-09 | Holidays-2025 bundle: Top Tasks panel, Marketplace Insights, Policy Violations Dashboard, Listing Appeal Pilot, Plus color themes, $500 holiday Purchase Protection, 24/7 support | Live | Confirmed |
| 2025-10 | Community forums → Bevy platform | Live | Confirmed |
| 2025-10 | US-specific pricing tool for non-US sellers | Live | Confirmed |
| 2025-11-05 | Instant Transfer payout beta (US, 1% fee) | Beta | Confirmed |
| 2025-11-10 | Top Buyer badge test in Messages | Test | Probable |
| 2025-12-12 | Payments Policy update (global collection agent, third-party verification, sales tax on fees) | Live | Confirmed |
| 2026-03 | Shop-rating recalculation (lifetime, recency-weighted) | Live | Confirmed |
| 2026-03/07 | Purchase Protection revisions: cap stays $250; new eligibility from Jul 9 — 48h help-request response + DDP for US-bound | Live | Confirmed (policy versions diffed) |
| 2026-04 | Stable listing URLs; AI-assistant discovery integrations; Materials tag removed | Live | Confirmed |
| 2026-04-23 → 06-22 | Regulatory Operating Fee changes (7 countries) | Live | Confirmed |
| 2026-06-12 | US Tariff Calculator beta (Zonos); dedicated help article created 2026-05-07 | Beta | Confirmed |
| 2026-07-09 | Mandatory DDP shipping to US; CPSC eFiling compliance article same day | Live | Confirmed |
| 2026-07-13 | Self-serve "seller app" API registration tier documented | Live | Confirmed |
| 2026-08-04 | EPR Germany and EPR Spain help articles created | Live | Confirmed |
| 2026-08-11 | Fur ban + prohibited-items revision effective | Live | Confirmed |

Context: leadership changed January 1, 2026 (Kruti Patel Goyal replacing Josh Silverman as CEO), Depop was sold to eBay (closed July 30, 2026) after Reverb's 2025 sale, and ~12% of staff (220 roles, mostly Product & Engineering) were cut August 5, 2026 — a narrowing to the core marketplace that frames the tooling above.[^recent]

---

[^onboard]: Etsy Help — "How to Open an Etsy Shop" https://help.etsy.com/hc/en-us/articles/115015672808 ; "How to Verify Your Identity on Etsy" https://help.etsy.com/hc/en-us/articles/22481159004567 ; "Etsy Asked Me to Confirm My Seller Info" https://help.etsy.com/hc/en-us/articles/14553858116759 ; Seller Handbook "Strengthening New-Shop Onboarding" article 1241780194948; setup-fee language via help.erank.com, craftybase.com, litcommerce.com.
[^creativity]: Etsy Creativity Standards https://www.etsy.com/legal/creativity/ ; prohibited items https://www.etsy.com/legal/prohibited/ ; June 2025 enforcement coverage (tctmagazine.com, efulfillmentservice.com); AI-stance Seller Handbook article 1275449912004.
[^listing]: Etsy Help — "How to Create a Listing" 115015628707; variations 115015664047; personalization 360000344528; digital listings 115015628347; renewal 360000344368; deactivation 360000336187; listing videos 360053206073; attributes 115014502508; translation 360000343048; Listings Manager Seller Handbook article 22851122487.
[^ai-tools]: Etsy Seller Handbook — "How Etsy Uses AI to Support Sellers" article 1402347260856; Writing Assistant and AI-title coverage via VAR (Aug–Sep 2025).
[^tariff]: Etsy Help — "How to Add Domestic, Global, and US-Specific Pricing" https://help.etsy.com/hc/en-us/articles/4403156582039 ; Tariff Calculator beta coverage via VAR (June 2026).
[^orders]: Etsy Help — orders dashboard 360000343908; packing slips 115015692187; gift services 360000343168; cancellation 115015587347; tracking 115015774228; "Newly Crafted" 10603291042967; Seller Handbook gift-wrap article 198251219817.
[^shipping]: Etsy Help — calculated shipping 115013946647; label purchase 360001967188; USPS 360000336887; UPS 18309523715735; FedEx 360000337447; insurance/claims 360001988847; international 360001987487; processing times 115015588087; estimated delivery 360001922768; Free Shipping Guarantee 360024198553 and Seller Handbook 540244125961; smart pricing 360025907754.
[^messages]: Etsy Help — vacation mode 115015662947; help requests 13241489600919; communication best practices 33491206306711; saved replies/auto-reply mechanics via secondary synthesis (help.erank.com, insightagent.app).
[^storefront]: Etsy Help — storefront setup 360000338047; customize appearance 115015663247; shop sections 360000345048; announcement/title 360000343708; shop-member roles 360000336867; production partners 360000336547; shop policies 115014372467; listing return policies 7869401615255; vacation/close/reopen 115015662947, 115015777088, 4410110016663; second shop 360017604474.
[^pattern]: Etsy Help — "What is Pattern?" 360000344088, "Getting Started With Pattern" 360000343188; still-active status per billing article 360000336507 and community threads (2026).
[^plus]: Etsy Help — "What is Etsy Plus?" https://help.etsy.com/hc/en-us/articles/360001589928 ; advanced customization 360001730447; restock requests 360001712528; domain redirect 360021664693 and Hover support pages; Etsy Premium announcement, investors.etsy.com June 2018 (no launch evidence found).
[^ads]: Etsy Help — set up Etsy Ads 360033701174; ads in search 115015745808; per-listing toggle 360044099373; performance 360034223613; Seller Handbook "7 Common Questions About Etsy Ads" 1044333907217.
[^offsite]: Etsy Help — "How Etsy's Offsite Ads Work" https://help.etsy.com/hc/en-us/articles/360000338367 ; Etsy fees policy https://www.etsy.com/legal/fees/
[^promos]: Etsy Help — sales and discounts 115014260108; Etsy-funded coupons 1500005142242; targeted offers 360036985673; share to social 360000344428; affiliates/Creator Collective 360000335987; targeted-offer mechanics triangulated via alura.io and others.
[^shareandsave]: Share & Save program terms https://www.etsy.com/legal/policy/etsys-share-save-terms-program-terms/1162874007996 ; Seller Handbook article 1187231088945; help article 16981332744087.
[^offer]: Etsy Help — "How to Use the Make an Offer Tool" https://help.etsy.com/hc/en-us/articles/16792774373143 ; March 2023 launch coverage (pymnts.com, valueaddedresource.net).
[^stats]: Etsy Help — Etsy Stats 115015774268; Shop Stats glossary 115015628207; revenue calculation 360016388633; Search Analytics 360001947367; Marketplace Insights 35122361353239.
[^star]: Etsy Help — Star Seller badge 4403058372503; progress tracking 29665255990039; metrics vs. customer-service standards 29654393638679; review system for sellers 360000572708; review reporting 360000442208; search/ads ranking disclosures https://www.etsy.com/legal/policy/search-advertisement-recommendation
[^finances]: Etsy Help — Etsy Payments countries 115015710408; deposits 360046998234; currency conversion 360000344668; reserves 360058722214 and Seller Handbook 1177649220109; Payoneer 16999319005207; sold-transactions CSV 360000343328; billing card 115015728867; monthly statements/VAT invoices 360000337247; Indie Sellers Guild reserve analysis.
[^fees]: Etsy fees policy https://www.etsy.com/legal/fees/ (unfetchable directly; figures converged across help articles 115014483627, 115015628847, 1500011073202 and independent fee guides).
[^tax]: Etsy Help — 1099-K 360000336447 (OBBBA threshold reversal corroborated by tax-advisory sources); US sales tax 360000343968; VAT 360000337247; VAT on seller fees 360040584433.
[^compliance]: Etsy Seller Handbook GPSR FAQs articles 1093438529659 and 1364599291081; France EPR help article 4419102662167; INFORM Act help article 14553858116759; DSA position via advocacy.etsy.com.
[^cases]: Etsy Help — Purchase Protection for sellers 5850122619287 and policy page https://www.etsy.com/legal/policy/purchase-protection-program-for-sellers/34509585385 ; chargebacks 115015729027; suspension appeals 6298920789271; IP portal https://www.etsy.com/legal/ip/report
[^community]: Etsy Help — Community Hub 115015570367; ranks/badges 4411944634775; Team Captains 360000343268; Etsy Design Awards 360022359394; "Newly Crafted" 10603291042967.
[^app]: Apple App Store — "Etsy Seller: Manage Your Shop" id1534619962 (fetched 2026-08-13); Seller Handbook "What's New on Etsy: Spring 2025" article 1364991912257.
[^recent]: Dated changelog compiled 2026-08-13, primarily from Value Added Resource (valueaddedresource.net) transcriptions of Etsy Seller Handbook/newsroom notices, cross-checked against PYMNTS and Retail Dive; etsy.com primary pages and Wayback were bot-blocked throughout.
[^payments-policy]: Etsy Payments Policy update effective 2025-12-12, via VAR transcription (global collection-agent role, third-party verification providers, Instant Transfer codification, sales tax on certain fees).
[^verified]: Primary-source verification pass, 2026-08-13: current help.etsy.com article bodies fetched via local proxy (payment processing 115015628847; regulatory fee 1500011073202; currency conversion 360000344668; deposits 360046998234; reserves 360058722214; Star Seller 4403058372503; Make an Offer 16792774373143; Etsy Plus 360001589928 + 360001730447; listing videos 360053206073; chargebacks 115015729027; Marketplace Insights 35122361353239; seller protection 5850122619287) and Wayback captures of www.etsy.com legal pages: Fees & Payments Policy web.archive.org/web/20260626044342/https://www.etsy.com/legal/fees ("Last updated Feb 13, 2026"); prohibited items web.archive.org/web/20260812080944/https://www.etsy.com/legal/prohibited/ ("Last updated Aug 11, 2026", fur ban verbatim); Creativity Standards web.archive.org/web/20260731053500/https://www.etsy.com/legal/creativity/ ("Last updated Jun 10, 2025"); Purchase Protection for sellers, three dated captures (2026-03-24, 2026-07-06, 2026-08-04) diffed.
[^hc-enum]: Full help-center enumeration via the Zendesk REST API on help.etsy.com, fetched through a local browser-header proxy 2026-08-13 (3 categories, 50 sections, 340 articles; tree with IDs and created/updated dates on file). Article bodies for Found (30767345994903), YouLend (26622808089367), RPAA (33802088617623), EPIL (25021886246935), and seller-app registration (41918478450967) were read directly. Square 360000905467; Xero 35010804164887; QuickBooks/TurboTax 360000337367; local pickup 360000338067; alt text 4406604492823; referral 360039270493; EPR Germany 42466548931479; EPR Spain 42462390264087; CPSC eFiling 41840762260759; P2B 360051541413; India e-commerce rules 360056070533; planet-friendly packaging 4408816010775.
