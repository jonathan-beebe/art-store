# shmetsy — Tooling Cost Reference (2 users)

**Compiled:** 2026-08-28 · **Updated:** 2026-08-28 (added domain registration, Claude Max, CDN investigation)
**Scope:** Base/entry tiers for Supabase, Cloudflare, Canva, GitHub, domain registration, and Claude — sized for a 2-person team.
**Sources:** Vendor-owned pages and docs only (links at the bottom). All figures USD.

## Confidence key

| Flag | Meaning |
|---|---|
| **HIGH** | Quoted directly from a vendor-owned page in this research pass (2026-08-28). |
| **MEDIUM** | From a vendor page but ambiguous or partially rendered, or derived arithmetically from a vendor-stated figure. |
| **UNVERIFIED** | Not confirmed in this pass. Do not put in a model without checking. |

---

## 1. Master table

| Vendor | Free tier | Base paid tier | Billing unit | 2 users (monthly) | 2 users (annual, prepaid) | Annual prepay cheaper? | Confidence |
|---|---|---|---|---|---|---|---|
| **Supabase** | $0 — 500 MB DB, 5 GB egress, 1 GB storage, 50k MAU; pauses after 7 days idle; max 2 active projects | Pro **from $25/mo** | Per **organization** | **$25** | **$300** | **No** — no discount published | HIGH |
| **Cloudflare** (website/app) | $0 — DNS, **CDN**, SSL, DDoS | Pro **$25/mo**, or **$20/mo billed annually** | Per **domain/zone** | **$0** (Free covers CDN — see §5) | **$0** | **Yes if upgraded** — $240 vs $300, saves $60/yr | HIGH |
| **Cloudflare Workers** | $0 — 100k req/day, 10 ms CPU/invocation | Paid **$5/mo minimum** (10M req + 30M CPU-ms incl.) | Per **account** | **$5** | **$60** | Not published | HIGH |
| **Canva** | $0 — 1 person | Pro **US$180/yr/person**; Business **US$250/yr/person** | Per **person** | ~$35.72 *derived* | Pro ×2: **$360** · Business ×2: **$500** | **Yes — "Save 16%"** | HIGH (annual) / MEDIUM (monthly) |
| **GitHub** | $0 — unlimited private repos, unlimited collaborators, 2,000 Actions min/mo, 500 MB Packages | Team **$4/user/mo** | Per **user** | **$0** (Free) | **$0** | Likely, amount UNVERIFIED | HIGH (Free) / MEDIUM (Team) |
| **Domain** (Namecheap .com) | — | Register **$11.08** (reg. price $14.98); **renew $18.48/yr**; + **$0.20 ICANN** per year | Per **domain** | ~$1.55 | **$18.68/yr** at renewal rate | **No real discount — see §2** | HIGH (prices) / MEDIUM (multi-year billing) |
| **Claude Max** | — | **Max 5x $100/mo** · **Max 20x $200/mo** | Per **user** | **$200** (5x) or **$400** (20x) | **n/a — monthly only** | **No — Max has no annual option** | HIGH |

---

## 2. Domain registration — 5-year prepay analysis

**Namecheap .com, published 2026-08-28:** register **$11.08** (sale; regular $14.98) · renew **$18.48/yr** · **ICANN fee $0.20/yr** added at checkout. Max term is **10 years total** between today and the expiration date, in full-year increments only.

**Whether years 2–5 bill at the register rate or the renewal rate is not published.** So the 5-year cost is a range, not a number:

| Scenario | 5-year total (incl. $1.00 ICANN) | Confidence |
|---|---|---|
| Best case — all 5 years at $11.08 sale rate | **$56.40** | UNVERIFIED (optimistic) |
| Mid case — all 5 years at $14.98 regular register rate | **$75.90** | UNVERIFIED |
| Likely case — yr 1 at $11.08, yrs 2–5 at $18.48 renewal | **$86.00** | MEDIUM |
| **Baseline: pay 1 year at a time** — yr 1 $11.08, yrs 2–5 at $18.48 | **$85.98** | MEDIUM |

**Conclusion: prepaying 5 years at Namecheap is almost certainly not a discount.** Under the likely billing model it costs $86.00 vs $85.98 paid annually — a two-cent difference. Namecheap's own marketing for advance renewal argues the benefit is **locking today's price against future increases**, not a multi-year discount. Prepay if you want price-increase insurance and want the renewal off your plate; do not prepay expecting to save money.

**Budget line: ~$18.68/yr (~$1.55/mo)** at the renewal rate. Verify actual multi-year cart pricing at checkout before committing.

---

## 3. Claude Max — and the alternative worth checking

**Max is the single largest line item in this stack — larger than everything else combined, by 3×.**

| Option | Price (2 users, monthly) | Price (2 users, annual) | Notes |
|---|---|---|---|
| **Max 5x ×2** | **$200/mo** | **$2,400/yr** | "Monthly subscription only" — no annual discount exists |
| **Max 20x ×2** | **$400/mo** | **$4,800/yr** | Monthly only |
| Claude Team, standard seat ×2 | $50/mo | **$480/yr** ($20/seat/mo billed annually) | Team minimum is 2 seats — you qualify |
| Claude Team, premium seat ×2 | $250/mo | **$2,400/yr** ($100/seat/mo billed annually) | Same annual cost as Max 5x ×2 |
| Claude Pro ×2 | $40/mo | **$400/yr** ($17/seat/mo billed annually) | Baseline for comparison |

**Open question — flagged, not answered:** Anthropic's Team plan bills at a lower rate annually ($20 standard / $100 premium per seat/month) while Max has no annual option at all. Whether a Team premium seat delivers usage limits equivalent to Max 5x was **not confirmed in this pass**. If it does, Team premium is the same annual spend with team admin controls added; if standard seats suffice, the saving is **$1,920/yr**. Worth 15 minutes of checking before committing $2,400/yr.

---

## 4. Do we need GitHub? — yes, Free. But read the workspace caveat.

GitHub Free (personal or organization) includes **unlimited private repositories with unlimited collaborators**, 2,000 Actions minutes/month, 500 MB Packages storage. A 2-person team hits none of the standard paid gates.

### Caveat for using GitHub as the docs workspace

**Wikis do not work on private repos on Free.** GitHub's docs state: *"Wikis are available in public repositories with GitHub Free and GitHub Free for organizations, and in public and private repositories with GitHub Pro, GitHub Team, GitHub Enterprise Cloud and GitHub Enterprise Server."*

**GitHub Pages on private repos** requires GitHub Enterprise Cloud — not available on Free or Team.

**What still works fine on Free, in a private repo:** Markdown files committed to the repo (which is how the shmetsy docs are being written today), Issues, and Projects. If the workspace pattern is *"Claude writes .md files into the repo"*, Free covers it completely and Google Workspace is genuinely unnecessary. If the pattern drifts toward wikis or a published internal doc site, that forces **Team at $4/user/mo ($96/yr for two)** or higher.

**Other triggers to upgrade to Team:** protected branches / required PR reviewers / code owners on private repos, or CI exceeding 2,000 Actions minutes/month.

**Recommendation: GitHub Free. Keep docs as Markdown in the repo, not as wikis.**

---

## 5. Do we need a separate CDN? — no.

**Cloudflare's Free plan includes the CDN.** Cloudflare describes the Free plan as providing "free SSL, CDN, DDoS protection and more," and the caching documentation states caching is **"Available on all plans."** Confidence: HIGH that CDN and caching are included on Free.

**What this means:** a separate CDN vendor (Fastly, Bunny, KeyCDN, CloudFront) is **redundant**. Putting the domain behind Cloudflare gives you global edge caching, TLS, and DDoS protection at $0. There is no CDN line item in this budget.

**Two things to verify before you rely on it — flagged as UNVERIFIED:**

1. **Bandwidth metering.** Cloudflare has long positioned bandwidth as unmetered on all plans, but I could not confirm that statement on a Cloudflare-owned page in this pass. Confirm before assuming unlimited image/asset delivery is free.
2. **Non-HTML content restrictions.** Cloudflare's self-serve terms have historically restricted serving a disproportionate amount of large non-HTML content (notably video) on the free/self-serve CDN. If shmetsy serves large media, check the current self-serve subscription agreement. Product images at normal sizes are not a concern; video would be.

**What Cloudflare Pro ($20/mo annual / $25/mo monthly, per domain) would add** — WAF managed rules, image optimization, better analytics. **Not needed pre-traffic.** Upgrade when you have traffic worth protecting or images worth optimizing.

**If you later outgrow it:** Cloudflare R2 (object storage, zero egress fees) is the natural next step for media, not a third-party CDN.

---

## 6. Scenarios

### A — everything free, no Claude
| Line | Monthly | Annual |
|---|---|---|
| Supabase Free, Cloudflare Free, Canva Free ×2, GitHub Free | $0 | $0 |
| Domain (renewal rate) | $1.55 | $18.68 |
| **Total** | **~$1.55** | **~$18.68** |

*Supabase Free pauses projects after 7 days idle — a prototype tier, not production.*

### B — realistic pre-launch baseline, with Claude Max ×2
| Line | Monthly | Annual (best prepay) | Prepay note |
|---|---|---|---|
| Supabase Pro | $25.00 | $300.00 | No discount available |
| Cloudflare Free (covers CDN) | $0.00 | $0.00 | — |
| Cloudflare Workers Paid | $5.00 | $60.00 | Only if app runs on Workers/Pages Functions |
| Canva Pro ×2 | ~$30.00 | **$360.00** | **Prepay annually — saves 16%** |
| GitHub Free | $0.00 | $0.00 | — |
| Domain ×1 | $1.55 | $18.68 | Prepay saves nothing; see §2 |
| **Claude Max 5x ×2** | **$200.00** | **$2,400.00** | **No annual option** |
| **Total** | **~$261.55/mo** | **~$3,138.68/yr** | |

**Claude is 76% of this budget.** Every other decision in this table is rounding error next to it. If the goal is cost control, §3 is the only line worth optimizing.

### C — same, but Claude Team standard seats instead of Max
| Line | Annual |
|---|---|
| Everything in B except Claude | $738.68 |
| Claude Team ×2 standard seats, billed annually | $480.00 |
| **Total** | **~$1,218.68/yr** |

*Saves $1,920/yr vs Scenario B — contingent on standard-seat usage limits being sufficient. Verify before assuming.*

---

## 7. Annual-prepay summary (the recurring question)

| Vendor | Prepay cheaper? | Saving |
|---|---|---|
| **Canva** | **Yes** | 16% — page toggle states "Save 16%" |
| **Cloudflare Pro/Business** | **Yes** (if you upgrade) | Pro $240 vs $300 = **$60/yr**; Business $2,400 vs $3,000 = **$600/yr** |
| **Claude Pro / Team** | **Yes** | Pro $17 vs $20/mo; Team standard $20 vs $25/seat/mo; Team premium $100 vs $125/seat/mo |
| **Claude Max** | **No** | Monthly subscription only |
| **Supabase** | **No** | No annual or prepay discount published |
| **Namecheap domain** | **Effectively no** | ~$0.02 over 5 years — insurance against price increases, not a discount |
| **GitHub Team** | Probably | Amount UNVERIFIED |

---

## 8. Still not covered

- Etsy's own fee stack — see `etsy-fees-and-pricing-model.md`
- Supabase overages above Pro quotas ($0.00325/MAU beyond 100k, plus egress/storage)
- Cloudflare Workers usage above the $5 minimum; R2 storage if media grows
- Canva AI Pass add-on (price not published)
- Claude API spend (separate from subscriptions), and "usage credits" that continue work at API rates past subscription limits
- Email hosting — Google Workspace judged unnecessary while docs live in the GitHub repo (see §4 caveat)

---

## Sources

- Supabase pricing — https://supabase.com/pricing
- Supabase billing docs — https://supabase.com/docs/guides/platform/billing-on-supabase
- Cloudflare plans — https://www.cloudflare.com/plans/ · Free plan — https://www.cloudflare.com/plans/free/
- Cloudflare Workers pricing — https://developers.cloudflare.com/workers/platform/pricing/
- Cloudflare cache docs — https://developers.cloudflare.com/cache/
- Canva pricing — https://www.canva.com/pricing/
- GitHub pricing — https://github.com/pricing · plans docs — https://docs.github.com/en/get-started/learning-about-github/githubs-plans · wikis — https://docs.github.com/en/communities/documenting-your-project-with-wikis/about-wikis
- Namecheap .com — https://www.namecheap.com/domains/registration/gtld/com/ · advance renewal — https://www.namecheap.com/blog/renew-domains-advance-save-money/ · max term — https://www.namecheap.com/support/knowledgebase/article.aspx/770/35/
- Claude pricing — https://claude.com/pricing · Max plan — https://support.claude.com/en/articles/11049741-what-is-the-max-plan
