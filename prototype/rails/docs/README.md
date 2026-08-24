# Docs

| Doc | Read it for |
| --- | --- |
| [`architecture.md`](architecture.md) | System shape: deployables, layers, sites, repository layout. Start here. |
| [`identity.md`](identity.md) | Magic-link sign-in for sellers and customers, anonymous-customer merge, `CustomerIdentity#current_customer`. |
| [`orders.md`](orders.md) | Checkout → payment → seller notification; order and fulfillment status diagrams. |
| [`escrow.md`](escrow.md) | Ledger entry types (`held` / `released` / `paid_out` / `refunded`), how a refund folds, `payouts:run`, a worked dollar example. |
| [`data-model.md`](data-model.md) | ER diagram generated from `src/db/schema.rb`. |
| [`ontology.md`](ontology.md) | Every entity in the product: who/what it is, why it exists, its lifecycle, and its relationships. One concept-level diagram. |
| [`admin.md`](admin.md) | The admin site: the directory pages, the filter rule, the folded-balance rule, the dashboard tallies, accounting, the ledger browser, the page-view roll-up, and where the write actions attach. |
| [`review.md`](review.md) | Every requirement in the brief, its status, and the route and test that prove it. Known gaps and next steps. |

Every diagram is Mermaid, states the question it answers in the prose above
it, and uses the real names from the code — grep the doc against `src/app/`
to check either one.
