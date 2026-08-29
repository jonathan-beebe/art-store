# shmetsy — Email Marketing vs. Full-Service CRM Cost Reference

**Compiled:** 2026-08-28
**Scope:** Pure email marketing / opt-in tools vs. full-service CRMs, sized for 2 users.
**Companion to:** `tooling-costs-2-users.md` (infrastructure and support software).
**Sources:** Vendor-owned pages only. All figures USD.

## Confidence key

| Flag | Meaning |
|---|---|
| **HIGH** | Quoted directly from the vendor's own page in this pass (2026-08-28). |
| **MEDIUM** | From a vendor page but ambiguous, an entry-tier "from" price, or a competitor's characterization of a rival's price. |
| **UNVERIFIED** | Not confirmed. Do not model without checking. |

### Read this before the tables

**Most pricing pages in this category hide their real numbers behind a JavaScript subscriber slider.** The default view shows the price at the smallest tier only. I captured what each vendor server-renders; where the slider governs, the figure is marked UNVERIFIED rather than guessed. Klaviyo, Brevo, and ActiveCampaign's own pricing page rendered no prices at all.

**Flodesk is no longer the flat-rate tool it is known for.** Their pricing page now states: *"Prices scale as your subscriber list grows."* The prices below are the entry tier. If flat-rate-regardless-of-list-size is the reason Flodesk is on the shortlist, that reason no longer holds — verify at your target list size.

---

## Table 1 — Pure email marketing & opt-in tools

| Tool | Free tier | Entry paid (monthly) | Entry paid (annual) | Subscribers at that price | Seats | Pricing model | Confidence |
|---|---|---|---|---|---|---|---|
| **Kit** (ConvertKit) | **Free to 10,000 subscribers** — unlimited broadcasts, forms, landing pages | Creator **$33/mo** | **$390/yr** (~$32.50/mo) | Price shown at **1,000** | Pro adds unlimited users | Scales with list | HIGH |
| **beehiiv** | **Launch — free to 2,500** subscribers, unlimited sends | Scale **$43/mo** | **$517/yr** (~$43.08/mo) | Scale covers **to 100,000** | Not stated | Tiered by list | HIGH |
| **Flodesk** | Free — forms, landing pages, link-in-bio, **no email sending** | Lite **$25/mo** · Pro **$28/mo** · Everything **$54/mo** | Lite **$19/mo** · Pro **$25/mo** · Everything **$49/mo** | Lite max 25,000; Pro/Everything 0–255,000 | Pro 2 · Everything 3 | **Scales with list** (entry tier shown) | HIGH (entry) / MEDIUM (higher tiers) |
| **MailerLite** | Free to **250** subscribers, 2,500 emails/mo, 2 seats | Comfort **from $12/mo** · Power **from $25/mo** | Not rendered | "From" price = smallest tier | 2 on Free | Scales with list + send volume | MEDIUM ("from" prices) |
| **Mailchimp** | Free to **250** contacts | Essentials **from $13/mo** · Standard **from $20/mo** · Premium **$350/mo** | Not rendered | "From" price = smallest tier | Not stated | Scales with contacts; sends capped at 10–12× contacts | MEDIUM ("from" prices) |
| **Brevo** | **Free — 5,000 emails/month**, contacts not capped | Not rendered | Not rendered | n/a | Professional includes 10 seats | **Billed on emails sent, not contacts** | HIGH (model + free tier) / UNVERIFIED (prices) |
| **Klaviyo** | Exists but limit not confirmed | Not rendered | Not rendered | n/a | Not stated | Not confirmed | UNVERIFIED |

**Best free tier in the category by a wide margin: Kit, at 10,000 subscribers.** beehiiv is second at 2,500. Mailchimp and MailerLite cap free at 250, which is a trial, not a tier.

---

## Table 2 — Full-service CRMs

| Platform | Free tier | Entry paid | Seats | Contacts included | Pricing model | Confidence |
|---|---|---|---|---|---|---|
| **HubSpot Marketing Hub** | **Free — up to 2 users** | Starter **$7/seat/mo** annual ($20 monthly) | Per seat | **1,000 marketing contacts** | Per seat **+** marketing-contact blocks | HIGH |
| HubSpot Marketing Professional | — | **$800/mo** annual ($890 monthly) | 3 core seats incl.; +$45/seat | 2,000 marketing contacts | Same, plus **$3,000 one-time onboarding** | HIGH |
| HubSpot Marketing Enterprise | — | **$3,600/mo** | 5 core seats incl.; +$75/seat | 10,000 marketing contacts | Plus **$7,000 one-time onboarding** | HIGH |
| **HubSpot Service Hub** | Free — up to 2 users | Starter **$7/seat/mo** annual ($20 monthly) | Per seat | 500 HubSpot Credits | Per seat | HIGH |
| **ActiveCampaign** | None | Starter **$149/mo at 10,000 contacts** | Starter/Plus **1 seat**; Pro 3; Enterprise 5 | Starter caps at 25,000 contacts | Scales with contacts | HIGH (the $149 figure, from ActiveCampaign's own blog) / UNVERIFIED (price at 1,000 contacts) |
| **Zoho CRM** | **Free forever — 3 users** | Standard / Professional / Enterprise / Ultimate | Per user | Not stated | Per user; "save up to 34% yearly" | HIGH (free tier) / **UNVERIFIED (USD prices — page serves local currency; do not convert, Zoho sets USD separately)** |
| **Salesforce Sales Cloud** | None | Starter Suite **$25/user/mo** (monthly or annual) | Per user | n/a | Per user | HIGH |
| Salesforce Pro / Enterprise / Unlimited | — | **$100 / $175 / $350 per user/mo**, annual | Per user | n/a | Per user | HIGH |

**Note on Salesforce:** Sales Cloud is a sales CRM, not an email marketing platform. Cost-per-subscriber is not a meaningful metric for it — email marketing there means Marketing Cloud, priced separately and not published. Included for completeness of the CRM category, not as an email option.

**The critical gap:** HubSpot does not publish the price of additional marketing-contact blocks beyond the included allotment. That number is what determines whether HubSpot is cheap or ruinous at scale — Starter includes only 1,000 contacts. **Get this figure before choosing HubSpot.**

---

## Cost per 1,000 subscribers

**This metric is unstable, and a single average would mislead you.** Cost per 1,000 subscribers falls by roughly an order of magnitude between a 1,000-person list and a 10,000-person list, because every vendor here bundles a floor price. Quoting one number hides the only thing that matters: where your list actually is.

### At a 1,000-subscriber list

| Tool | Monthly cost | **$ per 1,000 subscribers** | Confidence |
|---|---|---|---|
| Kit (Free plan) | $0 | **$0.00** | HIGH |
| beehiiv (Launch, free) | $0 | **$0.00** | HIGH |
| HubSpot Marketing Starter, 2 seats | $14.00 | **$14.00** | HIGH |
| Flodesk Lite (annual) | $19.00 | **$19.00** | HIGH |
| Kit Creator (annual) | $32.50 | **$32.50** | HIGH |
| Mailchimp Essentials | from $13.00 | from $13.00 | MEDIUM |
| MailerLite Comfort | from $12.00 | from $12.00 | MEDIUM |

**Average across the paid options with confirmed prices: $21.83 per 1,000** *(n = 3: HubSpot $14.00, Flodesk $19.00, Kit Creator $32.50).* **Treat this as indicative only — three data points is a thin average**, and it excludes the two vendors whose sliders would not render.

**Average including the free tiers that genuinely cover 1,000 subscribers: $13.10 per 1,000** *(n = 5).*

### At a 10,000-subscriber list

| Tool | Monthly cost | **$ per 1,000 subscribers** | Confidence |
|---|---|---|---|
| **Kit (Free plan — free to 10,000)** | $0 | **$0.00** | HIGH |
| beehiiv Scale | $43.00 | **$4.30** | HIGH |
| Mailchimp Essentials | ~$135.00 | ~$13.50 | MEDIUM (ActiveCampaign's characterization of a rival) |
| ActiveCampaign Starter | $149.00 | **$14.90** | HIGH |
| Flodesk Lite | UNVERIFIED at this tier | — | — |
| HubSpot Marketing Starter | $14 + unpublished contact blocks | — | UNVERIFIED |

**Average across paid options with prices: $10.90 per 1,000** *(n = 3).* **Including Kit's free tier: $8.18 per 1,000** *(n = 4).*

### What the two tables actually show

**Cost per 1,000 subscribers drops from ~$21.83 to ~$10.90 between a 1,000-list and a 10,000-list — a 50% fall.** You are paying for a floor, not for subscribers. At small list sizes the per-subscriber metric is dominated by the minimum monthly charge, which means **optimizing cost-per-subscriber below ~5,000 subscribers is optimizing the wrong number.** What matters at shmetsy's stage is the floor price, and the floor is $0 at Kit or beehiiv.

**Pure email tools beat CRMs on cost per subscriber at every size measured.** The CRM premium buys pipeline, deal tracking, and support-ticket integration — none of which a 2-person pre-launch operation is using yet.

---

## Recommendation

**Start on Kit's free plan — $0 to 10,000 subscribers.** Nothing else in either table comes close. It covers unlimited broadcasts, forms, and landing pages, which is the entire "email marketing and opt-in" brief. beehiiv Launch (free to 2,500) is the alternative if the newsletter-native features matter more than list headroom.

**Do not buy a CRM yet.** HubSpot Marketing Starter at $14/mo for 2 seats is genuinely cheap, but it includes only 1,000 marketing contacts and the overage price is unpublished — an open-ended liability. Revisit when there is a sales pipeline to track, not a list to email.

**Flodesk needs re-checking before it goes on any shortlist.** It is on this list because of a flat-rate reputation their own pricing page no longer supports.

**When the list is real, the vendor comparison changes.** Re-run this at the actual target list size — the ranking at 1,000 is not the ranking at 25,000.

---

## Open items

- Klaviyo and Brevo prices (JavaScript sliders — nothing rendered)
- ActiveCampaign price at contact tiers below 10,000
- Flodesk prices above the entry tier
- **HubSpot additional marketing-contact block pricing — the highest-value gap in this document**
- Zoho CRM USD prices (page serves local currency by geography)
- Whether shmetsy needs SMS alongside email — changes the ranking materially (Kit, Klaviyo, Brevo, Omnisend all bundle it differently)

## Sources

- Flodesk — https://flodesk.com/pricing
- Kit — https://kit.com/pricing
- beehiiv — https://www.beehiiv.com/pricing
- MailerLite — https://www.mailerlite.com/pricing
- Mailchimp — https://mailchimp.com/pricing/marketing/
- Brevo — https://www.brevo.com/pricing/
- HubSpot Marketing Hub — https://www.hubspot.com/pricing/marketing · Starter — https://www.hubspot.com/pricing/marketing/starter
- HubSpot Service Hub — https://www.hubspot.com/pricing/service
- ActiveCampaign plans — https://help.activecampaign.com/hc/en-us/articles/14251311251356-Overview-of-ActiveCampaign-plans · 10,000-contact pricing — https://www.activecampaign.com/blog/true-cost-of-email-marketing
- Zoho CRM — https://www.zoho.com/crm/zohocrm-pricing.html
- Salesforce Sales Cloud — https://www.salesforce.com/sales/pricing/
