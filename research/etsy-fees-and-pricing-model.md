# Etsy Fees & Pricing Model — Reference

**Compiled:** 2026-08-27
**Primary source:** Etsy's own Fees & Payments Policy (last updated Feb 13, 2026) and Etsy Help Center.
**Purpose:** Baseline cost structure for evaluating marketplace economics in the shmetsy project.

## Confidence key

| Flag | Meaning |
|---|---|
| **HIGH** | Quoted directly from an Etsy-owned page (etsy.com / help.etsy.com) in this research pass. |
| **MEDIUM** | From an Etsy-owned page but ambiguous, or from a single secondary source. |
| **UNVERIFIED** | Not confirmed in this pass. Do not use in a model without checking. |

Everything below is US-seller-default unless noted. Rates are seller-side; Etsy also charges buyers separately in some jurisdictions.

---

## 1. Fee summary — the stack

| Fee | Rate | Charged on | Confidence |
|---|---|---|---|
| Shop set-up fee | One-time, non-refundable; amount shown at signup | New shops | HIGH (existence) / MEDIUM (amount) |
| Listing fee | $0.20 USD | Per listing, per 4-month term | HIGH |
| Listing auto-renew (expired) | $0.20 USD | Per renewal | HIGH |
| Listing auto-renew (sold, multi-qty) | $0.20 USD | Per additional unit sold | HIGH |
| Private/custom listing fee | $0.20 USD | Per listing | HIGH |
| Transaction fee | **6.5%** | Item price + shipping + gift wrap | HIGH |
| Payment processing fee | **3% + $0.25 USD** (US) | Total sale price incl. shipping **and sales tax** | HIGH |
| Offsite Ads fee | **15%** or **12%** | Total order amount; capped at $100/order | HIGH |
| Etsy Ads | Seller-set daily budget, CPC | Clicks | HIGH |
| Currency conversion | **2.5%** | Sale amount, when listing currency ≠ payment account currency | HIGH |
| Regulatory Operating fee | 0.05%–1.97% by country (not US) | Item price + shipping | HIGH |
| Etsy Plus | **$10 USD/month** | Optional subscription | HIGH |
| Pattern | **$15.00 USD/month** after 30-day trial | Optional subscription | HIGH |
| In-person (Square) | **$0.20 USD** per non-synced transaction + Square's own processing | Per transaction | HIGH |
| Shipping labels | Carrier-dependent | Per label | HIGH |
| Instant transfer | Charged and displayed at transfer time; amount not published | Per transfer | MEDIUM |
| VAT on Etsy fees | Applies "where applicable" | Etsy's fees to the seller | MEDIUM |

---

## 2. Fee-by-fee detail

### 2.1 Shop set-up fee
- Etsy policy: *"To open your Etsy shop, you may be required to pay a one-time set-up fee... the set-up fee will be displayed and charged as part of the shop set-up process."* Non-refundable once paid; may be waived in promotional periods.
- **Etsy no longer publishes a fixed figure.** The February 21, 2024 Seller Handbook announcement introducing it stated *"a one-time set-up fee of $15."* Treat **$15 USD** as the anchor, **MEDIUM confidence**, and verify at signup for the specific market.

### 2.2 Listing fee — $0.20
- Flat $0.20 USD per listing. Listings expire after **4 months**.
- Auto-renew charges $0.20 again on expiry.
- **Multi-quantity listings renew at $0.20 per additional unit sold.** This is the one people miss: in a multi-qty listing the listing fee behaves as a **per-unit COGS line**, not a fixed cost.
- Custom/private listings are also $0.20 each.
- **Modeling implication:** listing fee ≈ $0.20 per unit sold + $0.20 per unsold SKU per 4 months. A 500-SKU catalog with 10% sell-through costs ~$100 every 4 months in dead-listing renewals alone.

### 2.3 Transaction fee — 6.5%
- *"6.5% of the price you display for each listing plus the amount you charge for shipping and gift wrapping."*
- Includes personalization charges.
- **Does not** include US sales tax.
- **Free shipping is not free:** if you bury $8 of shipping in the item price, you still pay 6.5% on it either way. There is no way to route revenue around this fee.

### 2.4 Payment processing fee — varies by country
Charged on *"the item's total sale price, including its shipping fees, and any applicable sales tax."* Note the asymmetry with the transaction fee: **processing is charged on sales tax, transaction fee is not.**

| Seller country | Rate |
|---|---|
| United States | 3% + $0.25 USD |
| United Kingdom | 4% + £0.20 GBP |
| Canada (domestic / US orders) | 3% + $0.25 CAD |
| Canada (international orders) | 4% + $0.25 CAD |
| Australia (domestic) | 3% + $0.25 AUD |
| Australia (international) | 4% + $0.25 AUD |
| Eurozone (AT, BE, FR, DE, IE, IT, NL, ES, etc.) | 4% + €0.30 EUR |

Confidence: **HIGH** for these seven rows. Other countries exist and were not pulled — check Etsy Payments Policy per market before modeling.

### 2.5 Regulatory Operating fee
Charged on item price + shipping (incl. gift wrap and personalization). Introduced because *"the cost of doing business in many countries increased with new regulations."* Subject to VAT where applicable.

| Country | Rate |
|---|---|
| Canada | 0.50% |
| France | 1.14% |
| Hungary | 1.97% |
| India | 0.05% |
| Italy | 0.80% |
| Spain | 0.88% |
| Türkiye | 1.67% |
| United Kingdom | 0.48% |
| Vietnam | 1.24% |

**Not charged to US sellers.** Etsy has revised these rates more than once (UK moved from 0.25% to 0.32% at one point, now listed at 0.48%) — treat as a **moving number**, re-check quarterly.

### 2.6 Currency conversion — 2.5%
- *"You will be charged a 2.5% currency conversion fee on the sale amount"* when the shop's listing currency differs from the payment account currency.
- Subtracted before funds land in the Payment account.
- **Modeling implication:** for a cross-border seller this is a near-invisible 2.5% margin leak. It is avoidable by matching listing currency to bank currency — at the cost of exposing buyers to FX-driven price optics.

---

## 3. Advertising

### 3.1 Etsy Ads (on-platform, optional)
- **Cost-per-click.** You are only charged on a click, not an impression.
- Minimum daily budget **$1**. Maximum starts at **$25/day** for all sellers and is *"recalculated weekly based on your ad spend over the last 7 days."*
- Unspent budget does not roll over. Ads stop when the daily budget is exhausted.
- CPC is variable per listing and per impression — Etsy does not publish a rate card.
- Etsy Plus includes **$5/month** in Etsy Ads credit.

### 3.2 Offsite Ads (off-platform, partly mandatory)
This is the single most important fee for viability analysis, because participation is **not fully optional**.

- **15%** fee for shops under **$10,000 USD** in sales over any consecutive 365-day period.
- **12%** fee once a shop hits **$10,000 USD or more** in that window.
- **Shops under $10K can opt out. Shops that cross $10K are required to participate "for the lifetime of your shop."** The opt-out is permanently revoked.
- Attribution window: a buyer clicks an offsite ad and purchases from your shop **within 30 days**.
- Fee basis: *"charged as a percentage of the total order amount, which comprises the price you display for each listing plus the amount you charge for shipping and gift wrapping and in some jurisdictions, taxes."* For US sellers, sales tax is excluded.
- **Cap: $100 USD per individual order.**

**Strategic read:** crossing $10K in trailing-twelve-month sales converts a variable cost into a permanent, unavoidable one. Any model that projects a shop scaling past $10K must assume Offsite Ads fees apply to some share of orders forever. The share is the unknown — Etsy does not publish an attribution rate, and it varies wildly by category. **This is the biggest single gap in the model.**

### 3.3 Share & Save (fee rebate, optional)
- Etsy refunds **4% of the qualifying transaction total** from your transaction fees when a buyer arrives via your unique Share & Save URL from an authorized off-Etsy channel (your social, site, blog, email) and purchases within 30 days.
- Cannot be combined with Etsy's Affiliate Program or Creator Co.
- Excluded if the buyer's purchase is instead attributed to an Offsite Ad in the window, or if refunded.
- **Effect:** for self-driven traffic, this cuts the effective transaction fee from 6.5% to 2.5% and blocks the 15%/12% Offsite Ads fee on that order. For any seller with their own audience, this is the highest-leverage fee lever Etsy offers.

---

## 4. Subscriptions and other services

| Service | Cost | Includes |
|---|---|---|
| Etsy Plus | $10 USD/month | 15 listing credits ($3 value) + $5 Etsy Ads credit/month |
| Pattern (standalone site) | $15.00 USD/month after 30-day free trial | No listing fees, no transaction fees on Pattern sales; payment processing still applies |
| Pattern domain privacy | $3.00 USD/year | Optional WHOIS privacy |

- **Etsy Plus net value:** $10/month buys $8 of credits. It is functionally a ~$2/month fee for shop customization features. Do not model it as a growth lever.
- **Pattern "no transaction fee" claim** is from Etsy's Pattern pricing help page — **MEDIUM confidence**, worth re-verifying, as it materially changes the DTC-vs-marketplace comparison.

---

## 5. Fee stacking — worked examples

### 5.1 Effective fee rate by order size (US seller, no ads, USD listing, no sales tax)

| Order total (item + shipping) | Listing | Transaction 6.5% | Processing 3% + $0.25 | Total fees | % of order |
|---|---|---|---|---|---|
| $10 | $0.20 | $0.65 | $0.55 | $1.40 | **14.0%** |
| $25 | $0.20 | $1.63 | $1.00 | $2.83 | **11.3%** |
| $50 | $0.20 | $3.25 | $1.75 | $5.20 | **10.4%** |
| $100 | $0.20 | $6.50 | $3.25 | $9.95 | **9.9%** |
| $250 | $0.20 | $16.25 | $7.75 | $24.20 | **9.7%** |
| $500 | $0.20 | $32.50 | $15.25 | $47.95 | **9.6%** |

Asymptote is ~9.5%. The two fixed components ($0.20 listing + $0.25 processing) make **low-AOV catalogs structurally more expensive** — a $10 AOV shop pays a ~40% higher effective rate than a $100 AOV shop for the identical product economics.

### 5.2 The same $50 order, four scenarios (US seller)

| Scenario | Fees | % of $50 |
|---|---|---|
| Baseline (no ads, USD listing) | $5.20 | 10.4% |
| + Offsite Ads attributed, shop <$10K (15%) | $12.70 | **25.4%** |
| + Offsite Ads attributed, shop ≥$10K (12%) | $11.20 | **22.4%** |
| Baseline + Offsite Ads 15% + 2.5% FX | $13.95 | **27.9%** |

**Range: ~10% to ~28% of gross order value**, before COGS, shipping cost, Etsy Ads spend, or returns. The delta is driven almost entirely by Offsite Ads attribution and currency mismatch.

### 5.3 UK seller, £30 item + £4 shipping = £34

| Line | Amount |
|---|---|
| Listing fee | $0.20 USD (~£0.15, FX-dependent) |
| Transaction 6.5% | £2.21 |
| Payment processing 4% + £0.20 | £1.56 |
| Regulatory Operating 0.48% | £0.16 |
| **Subtotal, Etsy fees ex-VAT** | **£3.93** (+ the ~£0.15 listing fee) |
| VAT on Etsy fees | Applies "where applicable" — rate and applicability **UNVERIFIED** |

Ex-VAT effective rate ~11.6%. If 20% UK VAT applies to Etsy's fees for a non-VAT-registered seller, add £0.79 → ~13.9%. **Verify before using.**

### 5.4 The $10 price point — why low-AOV physical goods break

Worked in detail because it is the clearest demonstration that Etsy's fee structure is not scale-neutral.

**Etsy's cut on a $10 item, free shipping (US seller, USD listing, no sales tax)**

| Scenario | Fees | % of order | Net to seller |
|---|---|---|---|
| Baseline (no ads) | $1.40 | **14.0%** | $8.60 |
| Offsite Ads attributed, 12% tier | $2.60 | 26.0% | $7.40 |
| Offsite Ads attributed, 15% tier | $2.90 | **29.0%** | $7.10 |
| + 2.5% currency mismatch | $3.15 | 31.5% | $6.85 |

Baseline breakdown: $0.20 listing + $0.65 transaction (6.5%) + $0.55 processing (3% + $0.25).

**$0.45 of the $1.40 is fixed** ($0.20 listing + $0.25 flat processing) — 4.5 points of drag that a $100-order seller carries at 0.45 points. Same product economics, ~40% worse fee rate purely from order size.

**Contribution margin once real costs are layered in (physical, free shipping)**

| | No ads | Offsite Ads attributed (15%) |
|---|---|---|
| Net after Etsy fees | $8.60 | $7.10 |
| − shipping (assumed $4.50) | $4.10 | $2.60 |
| − COGS at 20% | **+$2.10** (+21%) | **+$0.60** (+6%) |
| − COGS at 30% | **+$1.10** (+11%) | **−$0.40** (−4%) |
| − COGS at 40% | **+$0.10** (+1%) | **−$1.40** (−14%) |

⚠️ **The $4.50 shipping cost is an illustrative assumption, not researched — UNVERIFIED.** It is the most sensitive input in this table; replace it with a real carrier quote before relying on any of these figures.

**At 30% COGS, an Offsite Ads-attributed order is cash-negative.** Once a shop crosses $10K trailing-365-day sales, that column is not optional (see §3.2).

To clear a 20% contribution margin on a $10 physical item, landed COGS must be under **$2.10** with no ads, and under **$0.60** on an attributed order.

**Charging shipping separately does not fix it**

$10 item + $5 shipping = $15 order → fees $1.88 (12.5%), net $13.12. The *rate* improves because the fixed $0.45 spreads over a larger order, but the buyer now faces a $15 checkout for a $10 item — a conversion problem substituted for a margin problem. The 6.5% transaction fee applies to shipping either way; there is no routing around it.

**Where the $10 price point does work: digital**

| Digital product, $10 | Net | Margin |
|---|---|---|
| No ads | $8.60 | **86%** |
| Offsite Ads attributed (15%) | $7.10 | **71%** |

No shipping, no COGS, no per-unit fulfilment. Even the worst case clears 71%. This is the structural reason Etsy's digital-download category is saturated — it is the only place a $10 price point survives the fee stack.

**Blended rate, not per-order rate**

The 29% figure is the cost of a single attributed order, not a portfolio rate. Blended:

| Attribution share | Blended effective rate on a $10 item |
|---|---|
| 0% | 14.0% |
| 10% | 15.5% |
| 20% | 17.0% |
| 30% | 18.5% |
| 50% | 21.5% |

**Read-across for shmetsy:** if the target seller sits at a $10 physical price point, "cheaper than Etsy" is not a viable pitch — saving 5 points on a business running −4% contribution margin does not create a business. The binding constraint is the $0.45 of fixed per-order cost, not the percentage rate. Establish what makes such a seller viable at all before positioning against Etsy's rate card.

---

## 6. What Etsy captures at the platform level

Useful sanity check on how much of GMS Etsy converts to revenue in aggregate — this is *not* the same as an individual seller's fee load.

| Metric (Q2 2026, quarter ended Jun 30 2026) | Value |
|---|---|
| Consolidated GMS | ~$2.58B |
| Revenue | ~$668M |
| **Revenue take rate** | **25.9%** |
| GMS YoY growth | ~7.5% |

Confidence: **MEDIUM-HIGH** — 25.9% and ~$2.6B GMS were consistent across two secondary sources reporting the same quarter; I did not read Etsy's 10-Q directly. One source states these figures exclude the Depop marketplace, sold to eBay on July 30, 2026 — **the Depop divestiture is single-sourced and should be verified.**

**Why 25.9% ≫ the ~10% a typical seller pays:** the take rate includes services revenue — Etsy Ads spend, shipping label markup, and subscriptions — plus Offsite Ads fees, concentrated among a subset of sellers. The gap between 10% (marketplace fees) and 25.9% (realized take) is the size of the ads-and-services layer. **Etsy's revenue growth is coming from services, not from marketplace fee increases.** Any competitive model should assume the ads layer, not the listing/transaction fee, is where the real economics sit.

---

## 7. Implications for viability analysis

1. **The advertised fee is not the real fee.** "6.5% + payment processing" is Etsy's public framing and describes ~10% of the actual cost stack for a well-run shop. The realized number is 10–28% depending on Offsite Ads attribution. Any competitive positioning built against "6.5%" is attacking a number Etsy sellers do not actually pay.

2. **The $10K Offsite Ads trigger is the sharpest pain point.** It is irreversible, applies for the lifetime of the shop, and it fires exactly when a seller starts to matter to their own business. This is the most defensible wedge in the fee structure for a competitor.

3. **Low-AOV sellers are over-taxed.** Fixed per-listing and per-transaction components make sub-$25 AOV categories materially worse on Etsy than the headline rate implies. Worked in full at the $10 price point in **§5.4** — where a physical product at 30% COGS goes cash-negative on any Offsite Ads-attributed order.

4. **Catalog-heavy sellers pay a carrying cost.** $0.20 per SKU per 4 months on unsold inventory is a real, recurring drag that scales with catalog breadth, not with revenue.

5. **Share & Save reveals Etsy's own priority.** Etsy will hand back 4% for traffic a seller brings themselves. That is Etsy pricing its own demand generation — and telling you what it thinks its traffic is worth relative to a seller's.

6. **Cross-border sellers pay a stacked penalty:** higher processing (4% vs 3%), the Regulatory Operating fee, 2.5% FX if currencies mismatch, and VAT on fees. A UK or EU seller's effective rate runs 2–4 points above a US seller's on identical economics.

---

## 8. Open questions — verify before building a model

- [ ] **Current shop set-up fee amount by market.** Etsy stopped publishing it; $15 USD is the Feb 2024 announced figure.
- [ ] **Offsite Ads attribution rate** — what share of a typical shop's orders get attributed? Etsy publishes nothing. This single unknown swings the effective fee rate by 10+ points.
- [ ] **VAT treatment of Etsy fees** by seller country and VAT-registration status.
- [ ] **Pattern transaction fee** — Etsy's help page says none; confirm.
- [ ] **Payment processing rates outside the seven countries listed** in §2.4.
- [ ] **Instant transfer fee** amount — not published.
- [ ] **Shipping label margin** — Etsy's markup on carrier rates is not disclosed and is a component of the 25.9% take rate.
- [ ] **Depop divestiture** (eBay, July 30 2026) — single-sourced; confirm and check what it does to comparables.
- [ ] **Real landed shipping cost** for a light, low-value US parcel — §5.4 assumes $4.50, which is an illustrative placeholder and drives that entire analysis.
- [ ] **Free-shipping search ranking preference.** Etsy historically privileged US orders with free shipping over $35 in search. Whether this is still enforced in 2026 is **unverified**, and it materially affects whether a low-AOV seller can charge shipping separately.
- [ ] Etsy has revised the Regulatory Operating fee more than once. Set a **quarterly re-check** on §2.5.

---

## Sources

All Etsy-owned unless noted.

- [Fees & Payments Policy](https://www.etsy.com/legal/fees/) — last updated Feb 13, 2026
- [What are the Fees and Taxes for Selling on Etsy?](https://help.etsy.com/hc/en-us/articles/115014483627-What-are-the-Fees-and-Taxes-for-Selling-on-Etsy)
- [Etsy Fee Basics](https://help.etsy.com/hc/en-us/articles/360035902374-Etsy-Fee-Basics)
- [What are Payment Processing Fees for Selling on Etsy?](https://help.etsy.com/hc/en-us/articles/115015628847-What-are-Payment-Processing-Fees-for-Selling-on-Etsy)
- [What is a Regulatory Operating Fee?](https://help.etsy.com/hc/en-us/articles/1500011073202-What-is-a-Regulatory-Operating-Fee)
- [How Etsy's Offsite Ads Work](https://help.etsy.com/hc/en-us/articles/360000338367-How-Etsy-s-Offsite-Ads-Work)
- [How to Set Up and Manage an Etsy Ads Campaign](https://help.etsy.com/hc/en-us/articles/360033701174-How-to-Set-Up-and-Manage-an-Etsy-Ads-Campaign)
- [Etsy's Share & Save Terms](https://www.etsy.com/legal/policy/etsys-share-save-terms-program-terms/1162874007996)
- [Currency Conversion Fees](https://help.etsy.com/hc/en-us/articles/360000344668-Currency-Conversion-Fees)
- [Pricing and Fees for Pattern](https://help.etsy.com/hc/en-us/articles/360000337067-Pricing-and-Fees-for-Pattern)
- [How to Open an Etsy Shop](https://help.etsy.com/hc/en-us/articles/115015672808-How-to-Open-an-Etsy-Shop)
- [Strengthening New-Shop Onboarding to Keep Our Community Safe](https://www.etsy.com/seller-handbook/article/1241780194948) — Seller Handbook, Feb 21 2024, $15 set-up fee announcement
- Q2 2026 financials (secondary): [StockTitan 10-Q summary](https://www.stocktitan.net/sec-filings/ETSY/10-q-etsy-inc-quarterly-earnings-report-775df6d362ca.html), [AllInvestView Q2 2026](https://www.allinvestview.com/earnings/ETSY/q2-2026/)
- [Etsy Investor Relations — quarterly results](https://investors.etsy.com/financials/quarterly-results/default.aspx) (primary, not read in full)
