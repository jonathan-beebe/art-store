# shmetsy / art-store — Business Model Analysis

**Compiled:** 2026-08-29
**Companion to:** `etsy-fees-and-pricing-model.md`, `tooling-costs-2-users.md`, `email-crm-costs.md`
**Scope:** Platform economics at 10 / 100 / 1,000 / 10,000 sellers, across flat fee rates of 6% / 7% / 8% / 9%.
**Live model:** `business-model-analysis.xlsx` — every assumption is an editable input cell. Tabs: Assumptions · Costs · Model · Sensitivity · Profit Targets · 10K Scenario.

---

## Bottom line up front

**1. Adding chargebacks at a conservative basis roughly doubled every profit target. This is the biggest change in the model.**
You chose to model losses as *the platform eats the full order value*. At a 1% loss rate that removes 1 point of GMV — against a net take of 4.5% at an 8% fee, **the loss consumes 22% of net revenue.** At a 2% loss rate it consumes 44%. $300,000 of profit at 8% in the mid band moved from ~1,140 sellers to **1,843**.

**2. At a 6% fee and a 2% loss rate, your net take is 0.5%. That combination does not survive.**
Fee minus processing minus loss: 6% − 2.9% − 0.6% − 2.0% = **0.5%**. You would be running a marketplace, absorbing all fraud, and keeping half a cent per dollar. **The 6% tier is not viable under conservative loss assumptions at any seller count.**

**3. Hiring your first person at 200 sellers creates a valley you have to grow out of.**
At 200 sellers you take on $80,000 of salary. In the mid band at 8% you are profitable at 15 sellers, **underwater from 200, and do not recover until 396.** In the low band you do not recover until **1,800 sellers** — a 9× growth requirement to get back to zero. The hire is defensible; the valley is real and needs to be funded.

**4. At 10,000 sellers the business becomes robust — that is the real case for your critical-mass thesis.**
10,000 sellers at $500/mo and 8%, with 1% losses and 3 support staff, produces **$1,848,329 of profit**, **$924,165 retained**. More importantly it stays strongly profitable across the whole loss band. **What critical mass buys is not a bigger number — it is that being wrong about your assumptions stops being fatal.** Everything before 10,000 sellers is the part you have to survive. Note this does *not* rescue 6%: even at 10,000 sellers, 6% with 2% losses clears $48,329 on $60M of GMV.

**5. Seller quality still dominates every other variable.**
$300,000 of profit at 8% needs **1,843 sellers** at $500/mo — or **11,941** at $110/mo. Same target, 6.5× the acquisition cost, and it crosses two hiring thresholds on the way.

**6. Still recommend 8%.** It is the lowest fee that survives a 2% loss rate with a positive take (2.5%), and it still undercuts Etsy's 10.4% baseline. 6% does not survive; 9% is defensible but 8% leaves negotiating room.

---

## Confidence key

| Flag | Meaning |
|---|---|
| **HIGH** | Taken directly from the research files in this repo, which cite vendor-owned sources. |
| **MEDIUM** | Derived arithmetically from a HIGH figure, or a vendor figure with a caveat attached. |
| **YOUR DIRECTION** | A parameter you set. Not researched — recorded so it is visible as a choice, not a fact. |
| **UNVERIFIED** | Neither researched nor reliably estimable. Named so it does not hide. |

---

## 1. Assumption register — read this before any number below

| # | Input | Value | Confidence | Why it matters |
|---|---|---|---|---|
| 1 | "User" = seller/artist with a shop | — | Confirmed by you | Internal team scales separately |
| 2 | GMV per seller per month | $110 / $500 / $2,000 | **YOUR DIRECTION** (band) | **Largest driver in the model.** $110 approximates Etsy's platform-wide GMS ÷ active sellers incl. dormant shops — a conservative floor, LOW confidence. |
| 3 | Average order value | **$50** | **UNVERIFIED** | Sets orders/month and the cost of the fixed $0.30 processing fee. |
| 4 | Payment processing | **2.9% + $0.30** | **UNVERIFIED** | Stripe US card standard, from general knowledge — **not sourced in this repo.** Verify first. |
| 5 | Chargeback / refund loss | **0.5% / 1% / 2% of GMV** | **UNVERIFIED** | Modeled as a band at your direction. No sourced figure exists for art/handmade. **1% is the base case below.** |
| 6 | Loss basis | **Platform eats full order value** | **YOUR DIRECTION** | Deliberately conservative — see §3. |
| 7 | Support labour | **$80,000/FTE**; 1 FTE at 200 sellers, 2 at 2,000, 3 at 10,000 | **YOUR DIRECTION** | Creates the valley in §5d. |
| 8 | Retained earnings | **50% retained / 50% distributed** | **YOUR DIRECTION** | Losses are absorbed whole, not split. |
| 9 | Customer acquisition cost | **$50 per seller** | **UNVERIFIED** | Illustrative only. Used solely to translate retained earnings into fundable growth (§7). |
| 10 | Founder compensation | **$0 — not modeled** | **Not modeled** | Every profit figure is *before paying yourself*. |

---

## 2. Cost stack

**Literal (lowest tier of each tool, free where use cases overlap):** Supabase Free, Cloudflare Free, Workers Free, Canva Free, GitHub Free, Help Scout Free, Kit Free, domain $1.56/mo, Claude Pro ×2 $33.33/mo — **$34.89/mo, $418.68/yr, held flat.** This column is a fiction above ~100 sellers: Supabase Free pauses after 7 days idle and cannot run a live storefront. It is retained for reference only and does not appear in the model below.

**Realistic (used throughout this document):**

| Line | 10 | 100 | 1,000 | 10,000 | Confidence |
|---|---|---|---|---|---|
| Supabase | $25.00 | $25.00 | $50.00 | $225.00 | HIGH at 10/100 · **ESTIMATE** at 1k/10k |
| Cloudflare zone | $0.00 | $0.00 | $20.00 | $20.00 | HIGH |
| Cloudflare Workers | $5.00 | $5.00 | $25.00 | $150.00 | HIGH at 10/100 · **ESTIMATE** at 1k/10k |
| Canva Pro | $30.00 | $30.00 | $60.00 | $60.00 | HIGH |
| GitHub | $0.00 | $0.00 | $16.00 | $16.00 | HIGH |
| Domain | $1.56 | $1.56 | $1.56 | $1.56 | HIGH |
| Customer support | Free $0.00 | Free $0.00 | Free $0.00 | Free $0.00 | HIGH · your decision (1 inbox) |
| Email (Kit) | $0.00 | $0.00 | $0.00 | $100.00 | HIGH ≤10k subs · **UNVERIFIED** above |
| Claude Max 5x | $200.00 | $200.00 | $400.00 | $400.00 | HIGH |
| **Software / month** | **$261.56** | **$261.56** | **$572.56** | **$972.56** | |
| **Software / year** | **$3,139** | **$3,139** | **$6,871** | **$11,671** | |
| **Support labour / year** | **$0** | **$0** | **$80,000** | **$240,000** | YOUR DIRECTION |
| **Total operating cost / year** | **$3,139** | **$3,139** | **$86,871** | **$251,671** | |

**Labour now dwarfs software at every scale past 200 sellers.** At 10,000 sellers software is 4.6% of operating cost and salaries are 95.4%. Every software optimisation discussed in earlier versions of this document is now noise.

---

## 3. Chargebacks — what you chose and what it costs

You chose the **conservative basis: the platform absorbs the full order value on every loss.** That is the right way to model a floor, and I want to be explicit about how pessimistic it is.

| Loss rate | Lost per $100 of GMV | As % of pre-loss net revenue at 8% fee |
|---|---|---|
| 0.5% | $0.50 | **11%** |
| 1.0% (base) | $1.00 | **22%** |
| 2.0% | $2.00 | **44%** |

**In practice you would not lose the full order value on most disputes.** A refund reverses the sale — you lose your fee, and the seller's balance covers the goods, provided you hold payouts long enough. You eat the full amount only on genuine fraud where the seller has already withdrawn. A realistic split (≈80% refunds / 20% fraud) would cost roughly a fifth of what this model charges you.

**So treat these numbers as the floor, not the forecast.** The gap between this floor and reality is a *payout-hold policy* — how many days you sit on a seller's money before releasing it. That is a product decision worth real money, and it is not in the research files.

### Net take rate after processing and loss

| Fee | Loss 0.5% | Loss 1.0% (base) | Loss 2.0% |
|---|---|---|---|
| **6%** | **2.0%** | **1.5%** | **0.5%** |
| **7%** | **3.0%** | **2.5%** | **1.5%** |
| **8%** | **4.0%** | **3.5%** | **2.5%** |
| **9%** | **5.0%** | **4.5%** | **3.5%** |

**Read the bottom-left cell.** A 6% fee with 2% losses nets **0.5%** — 6% charged, 5.5% consumed. No seller count fixes that; it is a structurally dead combination. **7% at 2% losses nets 1.5%**, which is survivable but thin. **This is the strongest argument in the document for pricing at 8% or above.**

---

## 4. AOV sensitivity

The $0.30 fixed processing fee does not scale down. Net rate at the 1% base loss:

| AOV | 6% | 7% | 8% | 9% |
|---|---|---|---|---|
| **$10** | -0.9% | 0.1% | 1.1% | 2.1% |
| **$25** | 0.9% | 1.9% | 2.9% | 3.9% |
| **$50** | 1.5% | 2.5% | 3.5% | 4.5% |
| **$100** | 1.8% | 2.8% | 3.8% | 4.8% |
| **$250** | 2.0% | 3.0% | 4.0% | 5.0% |

**At a $10 AOV, 6% and 7% are both negative.** Establish art-store's real AOV before locking a fee.

---

## 5. Full model — base case (1% loss)

Annual figures. Operating cost = software + support labour. Profit is **before founder compensation**. Retained/distributed split 50/50; losses are absorbed whole rather than split.

### 5a. Low band — $110/seller/month

| Sellers | Annual GMV | Fee | Gross fee rev | Processing | Loss @1% | **Net rev** | Software | Labour | **Profit** | Retained 50% | Distributed 50% |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 10 | $13,200 | 6% | $792 | ($462) | ($132) | $198 | ($3,139) | $-0 | **($2,941)** | ($2,941) | $0 |
| 10 | $13,200 | 7% | $924 | ($462) | ($132) | $330 | ($3,139) | $-0 | **($2,809)** | ($2,809) | $0 |
| 10 | $13,200 | 8% | $1,056 | ($462) | ($132) | $462 | ($3,139) | $-0 | **($2,677)** | ($2,677) | $0 |
| 10 | $13,200 | 9% | $1,188 | ($462) | ($132) | $594 | ($3,139) | $-0 | **($2,545)** | ($2,545) | $0 |
| 100 | $132,000 | 6% | $7,920 | ($4,620) | ($1,320) | $1,980 | ($3,139) | $-0 | **($1,159)** | ($1,159) | $0 |
| 100 | $132,000 | 7% | $9,240 | ($4,620) | ($1,320) | $3,300 | ($3,139) | $-0 | **$161** | $81 | $81 |
| 100 | $132,000 | 8% | $10,560 | ($4,620) | ($1,320) | $4,620 | ($3,139) | $-0 | **$1,481** | $741 | $741 |
| 100 | $132,000 | 9% | $11,880 | ($4,620) | ($1,320) | $5,940 | ($3,139) | $-0 | **$2,801** | $1,401 | $1,401 |
| 1,000 | $1,320,000 | 6% | $79,200 | ($46,200) | ($13,200) | $19,800 | ($6,871) | ($80,000) | **($67,071)** | ($67,071) | $0 |
| 1,000 | $1,320,000 | 7% | $92,400 | ($46,200) | ($13,200) | $33,000 | ($6,871) | ($80,000) | **($53,871)** | ($53,871) | $0 |
| 1,000 | $1,320,000 | 8% | $105,600 | ($46,200) | ($13,200) | $46,200 | ($6,871) | ($80,000) | **($40,671)** | ($40,671) | $0 |
| 1,000 | $1,320,000 | 9% | $118,800 | ($46,200) | ($13,200) | $59,400 | ($6,871) | ($80,000) | **($27,471)** | ($27,471) | $0 |
| 10,000 | $13,200,000 | 6% | $792,000 | ($462,000) | ($132,000) | $198,000 | ($11,671) | ($240,000) | **($53,671)** | ($53,671) | $0 |
| 10,000 | $13,200,000 | 7% | $924,000 | ($462,000) | ($132,000) | $330,000 | ($11,671) | ($240,000) | **$78,329** | $39,165 | $39,165 |
| 10,000 | $13,200,000 | 8% | $1,056,000 | ($462,000) | ($132,000) | $462,000 | ($11,671) | ($240,000) | **$210,329** | $105,165 | $105,165 |
| 10,000 | $13,200,000 | 9% | $1,188,000 | ($462,000) | ($132,000) | $594,000 | ($11,671) | ($240,000) | **$342,329** | $171,165 | $171,165 |

### 5b. Mid band — $500/seller/month

| Sellers | Annual GMV | Fee | Gross fee rev | Processing | Loss @1% | **Net rev** | Software | Labour | **Profit** | Retained 50% | Distributed 50% |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 10 | $60,000 | 6% | $3,600 | ($2,100) | ($600) | $900 | ($3,139) | $-0 | **($2,239)** | ($2,239) | $0 |
| 10 | $60,000 | 7% | $4,200 | ($2,100) | ($600) | $1,500 | ($3,139) | $-0 | **($1,639)** | ($1,639) | $0 |
| 10 | $60,000 | 8% | $4,800 | ($2,100) | ($600) | $2,100 | ($3,139) | $-0 | **($1,039)** | ($1,039) | $0 |
| 10 | $60,000 | 9% | $5,400 | ($2,100) | ($600) | $2,700 | ($3,139) | $-0 | **($439)** | ($439) | $0 |
| 100 | $600,000 | 6% | $36,000 | ($21,000) | ($6,000) | $9,000 | ($3,139) | $-0 | **$5,861** | $2,931 | $2,931 |
| 100 | $600,000 | 7% | $42,000 | ($21,000) | ($6,000) | $15,000 | ($3,139) | $-0 | **$11,861** | $5,931 | $5,931 |
| 100 | $600,000 | 8% | $48,000 | ($21,000) | ($6,000) | $21,000 | ($3,139) | $-0 | **$17,861** | $8,931 | $8,931 |
| 100 | $600,000 | 9% | $54,000 | ($21,000) | ($6,000) | $27,000 | ($3,139) | $-0 | **$23,861** | $11,931 | $11,931 |
| 1,000 | $6,000,000 | 6% | $360,000 | ($210,000) | ($60,000) | $90,000 | ($6,871) | ($80,000) | **$3,129** | $1,565 | $1,565 |
| 1,000 | $6,000,000 | 7% | $420,000 | ($210,000) | ($60,000) | $150,000 | ($6,871) | ($80,000) | **$63,129** | $31,565 | $31,565 |
| 1,000 | $6,000,000 | 8% | $480,000 | ($210,000) | ($60,000) | $210,000 | ($6,871) | ($80,000) | **$123,129** | $61,565 | $61,565 |
| 1,000 | $6,000,000 | 9% | $540,000 | ($210,000) | ($60,000) | $270,000 | ($6,871) | ($80,000) | **$183,129** | $91,565 | $91,565 |
| 10,000 | $60,000,000 | 6% | $3,600,000 | ($2,100,000) | ($600,000) | $900,000 | ($11,671) | ($240,000) | **$648,329** | $324,165 | $324,165 |
| 10,000 | $60,000,000 | 7% | $4,200,000 | ($2,100,000) | ($600,000) | $1,500,000 | ($11,671) | ($240,000) | **$1,248,329** | $624,165 | $624,165 |
| 10,000 | $60,000,000 | 8% | $4,800,000 | ($2,100,000) | ($600,000) | $2,100,000 | ($11,671) | ($240,000) | **$1,848,329** | $924,165 | $924,165 |
| 10,000 | $60,000,000 | 9% | $5,400,000 | ($2,100,000) | ($600,000) | $2,700,000 | ($11,671) | ($240,000) | **$2,448,329** | $1,224,165 | $1,224,165 |

### 5c. High band — $2,000/seller/month

| Sellers | Annual GMV | Fee | Gross fee rev | Processing | Loss @1% | **Net rev** | Software | Labour | **Profit** | Retained 50% | Distributed 50% |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 10 | $240,000 | 6% | $14,400 | ($8,400) | ($2,400) | $3,600 | ($3,139) | $-0 | **$461** | $231 | $231 |
| 10 | $240,000 | 7% | $16,800 | ($8,400) | ($2,400) | $6,000 | ($3,139) | $-0 | **$2,861** | $1,431 | $1,431 |
| 10 | $240,000 | 8% | $19,200 | ($8,400) | ($2,400) | $8,400 | ($3,139) | $-0 | **$5,261** | $2,631 | $2,631 |
| 10 | $240,000 | 9% | $21,600 | ($8,400) | ($2,400) | $10,800 | ($3,139) | $-0 | **$7,661** | $3,831 | $3,831 |
| 100 | $2,400,000 | 6% | $144,000 | ($84,000) | ($24,000) | $36,000 | ($3,139) | $-0 | **$32,861** | $16,431 | $16,431 |
| 100 | $2,400,000 | 7% | $168,000 | ($84,000) | ($24,000) | $60,000 | ($3,139) | $-0 | **$56,861** | $28,431 | $28,431 |
| 100 | $2,400,000 | 8% | $192,000 | ($84,000) | ($24,000) | $84,000 | ($3,139) | $-0 | **$80,861** | $40,431 | $40,431 |
| 100 | $2,400,000 | 9% | $216,000 | ($84,000) | ($24,000) | $108,000 | ($3,139) | $-0 | **$104,861** | $52,431 | $52,431 |
| 1,000 | $24,000,000 | 6% | $1,440,000 | ($840,000) | ($240,000) | $360,000 | ($6,871) | ($80,000) | **$273,129** | $136,565 | $136,565 |
| 1,000 | $24,000,000 | 7% | $1,680,000 | ($840,000) | ($240,000) | $600,000 | ($6,871) | ($80,000) | **$513,129** | $256,565 | $256,565 |
| 1,000 | $24,000,000 | 8% | $1,920,000 | ($840,000) | ($240,000) | $840,000 | ($6,871) | ($80,000) | **$753,129** | $376,565 | $376,565 |
| 1,000 | $24,000,000 | 9% | $2,160,000 | ($840,000) | ($240,000) | $1,080,000 | ($6,871) | ($80,000) | **$993,129** | $496,565 | $496,565 |
| 10,000 | $240,000,000 | 6% | $14,400,000 | ($8,400,000) | ($2,400,000) | $3,600,000 | ($11,671) | ($240,000) | **$3,348,329** | $1,674,165 | $1,674,165 |
| 10,000 | $240,000,000 | 7% | $16,800,000 | ($8,400,000) | ($2,400,000) | $6,000,000 | ($11,671) | ($240,000) | **$5,748,329** | $2,874,165 | $2,874,165 |
| 10,000 | $240,000,000 | 8% | $19,200,000 | ($8,400,000) | ($2,400,000) | $8,400,000 | ($11,671) | ($240,000) | **$8,148,329** | $4,074,165 | $4,074,165 |
| 10,000 | $240,000,000 | 9% | $21,600,000 | ($8,400,000) | ($2,400,000) | $10,800,000 | ($11,671) | ($240,000) | **$10,548,329** | $5,274,165 | $5,274,165 |

### 5d. The hiring valley

Your first hire lands at **200 sellers**, before the revenue does. In the mid band at 8%:

| Sellers | Status |
|---|---|
| 15 | First profitable — software only, no salary |
| 15–199 | Profitable, growing |
| **200** | **First hire. $80,000 of salary lands. Back underwater.** |
| 200–395 | Loss-making |
| **396** | **Profitable again** |

**In the low band the valley runs from 200 sellers to 1,800** — you would need **9× growth** to climb back to breakeven after that hire.

**This is not an argument against hiring at 200.** If support genuinely breaks there, you hire. It is an argument for knowing the valley exists, sizing the cash to cross it, and treating "when does support actually break" as a question worth real evidence rather than a guess. **The difference between hiring at 200 and hiring at 600 is roughly a year of runway.**

---

## 6. What would have to be true — profit targets

**Base case: 1% loss rate.** Sellers required to hit each target. FTE count shown where staff are triggered.

| Profit target | Fee | Low band ($110/mo) | Mid band ($500/mo) | High band ($2,000/mo) |
|---|---|---|---|---|
| **$100,000** | 6% | **17,762** · 3 FTE | **2,966** · 2 FTE | **509** · 1 FTE |
| **$100,000** | 7% | **8,087** · 2 FTE | **1,246** · 1 FTE | **172** · 0 FTE |
| **$100,000** | 8% | **5,777** · 2 FTE | **873** · 1 FTE | **123** · 0 FTE |
| **$100,000** | 9% | **4,493** · 2 FTE | **679** · 1 FTE | **96** · 0 FTE |
| **$300,000** | 6% | **27,863** · 3 FTE | **5,188** · 2 FTE | **1,075** · 1 FTE |
| **$300,000** | 7% | **16,718** · 3 FTE | **3,113** · 2 FTE | **639** · 1 FTE |
| **$300,000** | 8% | **11,941** · 3 FTE | **1,843** · 1 FTE | **457** · 1 FTE |
| **$300,000** | 9% | **7,860** · 2 FTE | **1,433** · 1 FTE | **355** · 1 FTE |
| **$500,000** | 6% | **37,964** · 3 FTE | **7,410** · 2 FTE | **1,631** · 1 FTE |
| **$500,000** | 7% | **22,778** · 3 FTE | **4,446** · 2 FTE | **972** · 1 FTE |
| **$500,000** | 8% | **16,270** · 3 FTE | **3,176** · 2 FTE | **695** · 1 FTE |
| **$500,000** | 9% | **12,655** · 3 FTE | **2,470** · 2 FTE | **540** · 1 FTE |

### 6a. How the loss rate moves the answer (at 8%, mid band)

| Profit target | Loss 0.5% | Loss 1.0% | Loss 2.0% |
|---|---|---|---|
| **$100,000** | **764** | **873** | **1,246** |
| **$300,000** | **1,612** | **1,843** | **3,113** |
| **$500,000** | **2,779** | **3,176** | **4,446** |

**Halving the loss rate from 2% to 1% is worth more than a full point of fee.** At $300,000 of profit it removes 738 sellers from the requirement; moving 8% → 9% removes 410. **Payout-hold policy and fraud screening are higher-leverage than pricing** — and neither appears anywhere in the research files.

### 6b. Monthly GMV per seller required, at fixed seller counts (1% loss)

| Profit target | Fee | 10 sellers | 100 sellers | 1,000 sellers | 10,000 sellers |
|---|---|---|---|---|---|
| **$100,000** | 6% | $57,299 | $5,730 | $1,038 | $195 |
| **$100,000** | 7% | $34,380 | $3,438 | $623 | $117 |
| **$100,000** | 8% | $24,557 | $2,456 | $445 | $84 |
| **$100,000** | 9% | $19,100 | $1,910 | $346 | $65 |
| **$300,000** | 6% | $168,410 | $16,841 | $2,149 | $306 |
| **$300,000** | 7% | $101,046 | $10,105 | $1,290 | $184 |
| **$300,000** | 8% | $72,176 | $7,218 | $921 | $131 |
| **$300,000** | 9% | $56,137 | $5,614 | $716 | $102 |
| **$500,000** | 6% | $279,522 | $27,952 | $3,260 | $418 |
| **$500,000** | 7% | $167,713 | $16,771 | $1,956 | $251 |
| **$500,000** | 8% | $119,795 | $11,979 | $1,397 | $179 |
| **$500,000** | 9% | $93,174 | $9,317 | $1,087 | $139 |

**The 10-seller column is not a business** — it is there to show the curve's shape. **The 10,000-seller column is the finding:** at 8%, each seller needs only **$131/month** to clear $300,000 of profit — above the $110 conservative floor, but only just, and comfortably below the $500 mid band.

---

## 7. The 10,000-seller scenario — your critical-mass case

You believe 10,000 sellers is the threshold for attracting buyers. **The model supports that belief financially**, which is worth stating plainly because most of this document has been cautionary.

At 10,000 sellers the platform carries its full cost load — $11,671 of software, **3 support staff at $240,000**, $251,671 total. Here is what it produces:

### 7a. Profit at 10,000 sellers, by fee and GMV band (1% loss)

| GMV/seller | Fee | Annual GMV | Net revenue | Operating cost | **Profit** | **Retained (50%)** | Sellers fundable at $50 CAC |
|---|---|---|---|---|---|---|---|
| $110 | 6% | $13,200,000 | $198,000 | ($251,671) | **($53,671)** | ($53,671) | 0 |
| $110 | 7% | $13,200,000 | $330,000 | ($251,671) | **$78,329** | $39,165 | 783 |
| $110 | 8% | $13,200,000 | $462,000 | ($251,671) | **$210,329** | $105,165 | 2,103 |
| $110 | 9% | $13,200,000 | $594,000 | ($251,671) | **$342,329** | $171,165 | 3,423 |
| $500 | 6% | $60,000,000 | $900,000 | ($251,671) | **$648,329** | $324,165 | 6,483 |
| $500 | 7% | $60,000,000 | $1,500,000 | ($251,671) | **$1,248,329** | $624,165 | 12,483 |
| $500 | 8% | $60,000,000 | $2,100,000 | ($251,671) | **$1,848,329** | $924,165 | 18,483 |
| $500 | 9% | $60,000,000 | $2,700,000 | ($251,671) | **$2,448,329** | $1,224,165 | 24,483 |
| $2,000 | 6% | $240,000,000 | $3,600,000 | ($251,671) | **$3,348,329** | $1,674,165 | 33,483 |
| $2,000 | 7% | $240,000,000 | $6,000,000 | ($251,671) | **$5,748,329** | $2,874,165 | 57,483 |
| $2,000 | 8% | $240,000,000 | $8,400,000 | ($251,671) | **$8,148,329** | $4,074,165 | 81,483 |
| $2,000 | 9% | $240,000,000 | $10,800,000 | ($251,671) | **$10,548,329** | $5,274,165 | 105,483 |

### 7b. The same scenario stressed by loss rate (mid band, $500/seller/mo)

| Fee | Loss 0.5% | Loss 1.0% | Loss 2.0% |
|---|---|---|---|
| **6%** | $948,329 | $648,329 | $48,329 |
| **7%** | $1,548,329 | $1,248,329 | $648,329 |
| **8%** | $2,148,329 | $1,848,329 | $1,248,329 |
| **9%** | $2,748,329 | $2,448,329 | $1,848,329 |

**Every cell is positive — but read the corner before celebrating.** The worst case (6% fee, 2% loss) clears only **$48,329** on **$60,000,000** of GMV. That is a 0.08% margin on volume: technically profitable, operationally indistinguishable from zero, and one bad quarter from negative. **6% remains disqualified even at critical mass.**

**The real finding is the 8% row.** At 8% the business clears $1,248,329 even at a 2% loss rate — a 2.5× swing in loss assumptions moves it from $2,148,329 to $1,248,329, and it stays comfortably profitable throughout. **That is what critical mass buys you: at 10,000 sellers, being wrong about the loss rate stops being fatal.** It does not make 6% work.

### 7c. The flywheel — what retained earnings actually buy

At 10,000 mid-band sellers and 8%, retained earnings are **$924,165/year**. At $50 blended CAC that funds **18,483 new sellers annually** — more than the existing base. The business becomes self-funding for growth.

**Two honest caveats on that number:**

- **$50 CAC is illustrative, not researched.** It is the single number that makes the flywheel look this good. At $200 CAC the same retained earnings fund 4,621 sellers — still healthy, but no longer explosive. **Get a real CAC figure before relying on §7c for anything.**
- **The flywheel does not help you reach 10,000.** It describes the state *after* critical mass. Getting from 200 to 10,000 sellers is the actual problem, and it must be funded from outside the model — through the hiring valley in §5d, at 6.5× the acquisition cost if sellers turn out to be low-band rather than mid-band.

### 7d. What 10,000 sellers means operationally

| Metric | Value at mid band | Note |
|---|---|---|
| Platform annual GMV | $60,000,000 | Roughly 0.6% of Etsy's annual GMS |
| Orders per year (at $50 AOV) | 1,200,000 | ~3,288 orders/day |
| Sellers per support head | 3,333 | vs Etsy's ~4,000 per employee at maturity (MEDIUM confidence) |
| Annual chargeback loss @1% | $600,000 | **2.4× your entire salary bill** |

**That last row is the one to sit with.** At 10,000 sellers, a single point of loss rate costs $600,000 a year — more than double what you pay three people. **Fraud and dispute management is not an operations detail at this scale; it is a larger line item than your team.**

---

## 8. What this model still does not contain

| Missing | Why it matters | Rough magnitude |
|---|---|---|
| **Founder compensation** | The largest remaining hole. Every profit figure is before paying yourself. | Two founders at $120,000 turns a $300,000 target into $540,000 |
| **Customer acquisition cost as a P&L line** | CAC appears only in §7c as a use of retained earnings, never as a cost of getting there. | At $50/seller, reaching 1,843 sellers costs $92,150 — spent before any profit arrives |
| **Churn** | Seller counts are stock, not flow. Holding 1,843 active sellers may mean acquiring 3,000+. | UNVERIFIED |
| **Buyer-side demand generation** | The thing sellers actually pay a marketplace fee for. Your 10,000-seller thesis assumes sellers attract buyers; the reverse is at least as true. | UNVERIFIED — **the core product risk** |
| **Stripe Connect payout fees** | Beyond base processing. | UNVERIFIED |
| **Marketplace facilitator sales tax, 1099-K** | Obligations in most US states. | UNVERIFIED |
| **Payroll burden on salaries** | $80,000/FTE is base pay only. W-2 adds ~20%. | $48,000/yr at 3 FTE |

---

## 9. Recommendation

**Price at 8%.** The chargeback analysis strengthened this considerably:

1. **6% is now disqualified, not merely thin.** At a 2% loss rate it nets 0.5%. Even at the 1% base it nets 1.5% — a third of what 8% nets. There is no scenario in this model where 6% is the right choice.
2. **8% is the lowest fee that survives the pessimistic case** with a workable 2.5% take.
3. **8% still undercuts Etsy's 10.4% baseline** and massively undercuts its 22–29% ads-attributed reality.
4. **9% is defensible** and worth modelling as an opening position — but 8% leaves room to discount for early sellers without going underwater.

**Then stop optimising price and go work on three things that matter more:**

- **Payout-hold policy and fraud screening.** Halving the loss rate beats a full point of fee (§6a).
- **When support actually breaks.** The 200-seller hire creates a valley of up to 1,800 sellers (§5d). Evidence here is worth a year of runway.
- **Whether sellers average $110 or $500 a month.** It is a 6.5× difference in the seller count you need. Nothing else in this document comes close.

---

## 10. Open questions

- [ ] **Real payment processing rate and Connect payout fees.** Still unsourced. Highest-value gap.
- [ ] **Actual chargeback/refund rate for art and handmade goods**, and the refund-vs-fraud split.
- [ ] **Payout-hold policy** — determines how much of the conservative loss basis you actually absorb.
- [ ] **Real blended CAC.** §7c's flywheel rests entirely on the $50 placeholder.
- [ ] **Expected AOV.** Determines whether low fee tiers work at all (§4).
- [ ] **Realistic GMV per active seller.** The $110 floor includes dormant Etsy shops.
- [ ] **When support genuinely breaks** — the 200-seller trigger is a guess with year-of-runway consequences.
- [ ] **Churn rate.** Turns every seller count in §6 into a larger acquisition number.
- [ ] Supabase and Workers cost at 1,000+ sellers — my estimates have no basis.
- [ ] Claude Team standard vs Max 5x usage limits — now minor next to salaries.

---

## Sources

Figures trace to the three research files in this folder, which cite vendor-owned pages: `etsy-fees-and-pricing-model.md`, `tooling-costs-2-users.md`, `email-crm-costs.md`.

**Not sourced in this repo, and flagged throughout:** Stripe's 2.9% + $0.30 processing rate, the 0.5–2% chargeback band, the $50 CAC placeholder, the $50 AOV, and Etsy's ~4,000-sellers-per-employee ratio. All are general knowledge or illustrative placeholders, not researched figures.
