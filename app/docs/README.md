# Docs

| Doc                                  | Read it for                                                                                                 |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| [`architecture.md`](architecture.md) | System shape: deployables, layers, sites, repository layout. Start here.                                    |
| [`identity.md`](identity.md)         | Magic-link sign-in for sellers, customers, and admins; anonymous-customer merge (including messaging        |
|                                      | tables); `ResolveCustomerIdentity`.                                                                         |
| [`orders.md`](orders.md)             | Checkout → finalize → seller notification; `OrderStatus` and `FulfillmentStatus` state diagrams.            |
| [`escrow.md`](escrow.md)             | Ledger entry types (`held` / `released` / `paid_out` / `refunded`), `payouts:run`, a worked dollar example. |
| [`admin.md`](admin.md)               | The admin site: every page and filter, the one guard and the one 404, and why balances are folded from a    |
|                                      | single read of the ledger.                                                                                  |
| [`log-store.md`](log-store.md)       | The SQLite mirror of every stdout line, its ingest via a Monolog tap, and the `/admin/logs` viewer with its |
|                                      | filters, grouping, and story view.                                                                          |
| [`analytics.md`](analytics.md)       | The analytics store: the one `Analytics` entry point, buffered recording,   |
|                                      | the flush lifecycle, `analytics_events`/`page_view_counts`, and the readers.                                |
| [`funnel.md`](funnel.md)             | The funnel's query/component boundary: the `FunnelStep` contract, the       |
|                                      | session-unit decision, the accepted design's drawing rules, and what an     |
|                                      | admin-defined funnel needs.                                                                                  |
| [`seller-portal.md`](seller-portal.md) | The seller's own site, one section per tool: the store profile and its public page, listings as list,       |
|                                        | table, and grid, the activity feed, earnings, support, and the nine tables the portal added.                |
| [`messaging.md`](messaging.md)       | The one `conversations`/`messages` table serving four kinds of thread, who may read and post, the           |
|                                      | listing-question-to-FAQ path, unread counts, and the admin site's block.                                      |
| [`theming.md`](theming.md)           | The token system: `config/theme.php`, `DesignTokens`, the `@theme inline` mapping, dark mode, the atoms,    |
|                                      | and the `/design-system` living reference.                                                                  |
| [`data-model.md`](data-model.md)     | ER diagram generated from `database/migrations/`.                                                           |
| [`item-configurator.md`](item-configurator.md) | The item configurator: taxonomy, option axes, sparse variants, serialized units, scoped modifiers, |
|                                      | quantity breaks; price/availability resolution; seller and customer flows; what v1 defers.                  |
| [`mcp.md`](mcp.md)                   | The MCP endpoint: `POST /mcp` behind an admin's api key, the tools over the log and analytics readers, and |
|                                      | the self-describing guide.                                                                                 |
| [`ontology.md`](ontology.md)         | Every entity in the product: who/what it is, why it exists, its lifecycle, and its relationships. One       |
|                                      | concept-level diagram.                                                                                      |

Every diagram is Mermaid, states the question it answers in the prose above
it, and uses the real names from the code — grep the doc against `app/src/app/`
to check either one.
