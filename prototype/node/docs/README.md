# Docs

| Doc                                  | Read it for                                                                                                 |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| [`architecture.md`](architecture.md) | System shape: stack, deployables, layers, the path a request takes through the plugins, sites, the outbox,  |
|                                      | readiness and shutdown, conventions, testing, repository layout. Start here.                                |
| [`identity.md`](identity.md)         | Magic-link sign-in for all three actor types, the anonymous customer row, and the merge-as-fold when a      |
|                                      | guest verifies.                                                                                             |
| [`orders.md`](orders.md)             | Checkout → card → fulfillment split → shipping → delivery → cancel, with both state machines.               |
| [`escrow.md`](escrow.md)             | `held` / `released` / `paid_out`, the weekly payout run and why re-running it is safe, and a worked dollar  |
|                                      | example.                                                                                                    |
| [`messaging.md`](messaging.md)       | The four conversation kinds, a listing question becoming a published FAQ, the access rule, and unread       |
|                                      | counts.                                                                                                     |
| [`admin.md`](admin.md)               | What an operator can do: moderation and its effects, the page-view rollup, the outbox as the platform's     |
|                                      | mailbox, and running a payout.                                                                              |
| [`data-model.md`](data-model.md)     | Every table and column, from the migrations, with the caveats each shape carries.                           |
| [`ontology.md`](ontology.md)         | Every entity in the product: who or what it is, why it exists, its lifecycle, its relationships, and the    |
|                                      | vocabulary.                                                                                                 |
| [`review.md`](review.md)             | Every requirement in the brief, its status, and the route and test that prove it.                           |

Every diagram is Mermaid, states the question it answers in the prose above it,
and carries the real names from the code — grep the doc against `src/app/` to
check either one. `make docs-check` renders every block through
`minlag/mermaid-cli` in Docker and fails on any that does not parse.
