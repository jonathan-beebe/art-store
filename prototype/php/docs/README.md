# Docs

| Doc                                  | Read it for                                                                                                 |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| [`architecture.md`](architecture.md) | System shape: deployables, layers, sites, repository layout. Start here.                                    |
| [`identity.md`](identity.md)         | Magic-link sign-in for sellers, customers, and admins; anonymous-customer merge (including messaging        |
|                                      | tables); `ResolveCustomerIdentity`.                                                                         |
| [`orders.md`](orders.md)             | Checkout → finalize → seller notification; `OrderStatus` and `FulfillmentStatus` state diagrams.            |
| [`escrow.md`](escrow.md)             | Ledger entry types (`held` / `released` / `paid_out`), `payouts:run`, a worked dollar example.              |
| [`admin.md`](admin.md)               | The admin site: every page and filter, the one guard and the one 404, and why balances are folded from a    |
|                                      | single read of the ledger.                                                                                  |
| [`log-store.md`](log-store.md)       | The SQLite mirror of every stdout line, its ingest via a Monolog tap, and the `/admin/logs` viewer with its |
|                                      | filters, grouping, and story view.                                                                          |
| [`analytics.md`](analytics.md)       | The analytics store: the one `Analytics` entry point, buffered recording,   |
|                                      | the flush lifecycle, `analytics_events`/`page_view_counts`, and the readers.                                |
| [`funnel.md`](funnel.md)             | The funnel's query/component boundary: the `FunnelStep` contract, the       |
|                                      | session-unit decision, the accepted design's drawing rules, and what an     |
|                                      | admin-defined funnel needs.                                                                                  |
| [`messaging.md`](messaging.md)       | The one `conversations`/`messages` table serving four kinds of thread, who may read and post, the           |
|                                      | listing-question-to-FAQ path, the live SSE badge, and the admin site's block.                               |
| [`backups.md`](backups.md)           | **Design, not yet built.** The hourly SQLite snapshot and the nightly archive to Cloudflare R2, why they    |
|                                      | run inside the web service, the disk floor that keeps them from taking the store down, and                  |
|                                      | `/admin/backups`.                                                                                           |
| [`data-model.md`](data-model.md)     | ER diagram generated from `database/migrations/`.                                                           |
| [`item-configurator.md`](item-configurator.md) | The item configurator: taxonomy, option axes, sparse variants, serialized units, scoped modifiers, |
|                                      | quantity breaks; price/availability resolution; seller and customer flows; what v1 defers.                  |
| [`ontology.md`](ontology.md)         | Every entity in the product: who/what it is, why it exists, its lifecycle, and its relationships. One       |
|                                      | concept-level diagram.                                                                                      |
| [`seller-portal.md`](seller-portal.md) | The seller portal's tools beyond the backbone, one section per tool as its lane lands. Listings: list/table/grid, |
|                                      | the query vocabulary, and the overlay/takeover.                                                              |
| [`review.md`](review.md)             | Every requirement in the brief, its status, and the route and test that prove it. Known gaps and next       |
|                                      | steps.                                                                                                      |

Every diagram is Mermaid, states the question it answers in the prose above
it, and uses the real names from the code — grep the doc against `src/app/`
to check either one.
