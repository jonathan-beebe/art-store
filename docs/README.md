# Product and design docs

| Doc                                            | Read it for                                                                                       |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| [spec.md](spec.md)                             | identifiers, log payload and event vocabulary, rate limits, the order/fulfillment/refund          |
|                                                | lifecycle, the admin feature set, make targets and the commit gate                                |
| [database.md](database.md)                     | how the app uses SQLite, and what stays portable toward Postgres                                  |
| [item-configuration.md](item-configuration.md) | how a seller configures a listing and a buyer configures one on the listing page                  |
| [logging.md](logging.md)                       | the log store and the admin log viewer                                                            |
| [routing.md](routing.md)                       | the site split, storefront URL scheme, and query-parameter conventions                            |
| [ontology.md](ontology.md)                     | the domain vocabulary — sellers, listings, orders, and the words around them                      |
| [principles.md](principles.md)                 | the product-stewardship principles the app is held to                                             |

For how a specific part of the app is actually built — architecture, data
model, admin, messaging, analytics, and the rest — see
[app/docs/README.md](../app/docs/README.md).
