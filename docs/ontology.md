# Domain ontology

**Seller**

Also *artist*, *maker*.

Is: a creative human.
Is-Not: a brand, a group, a store.

Owns the copyright to the goods they sell.

Could a seller be a studio? Maybe, if headed by a human and creating hand-made art.

**Store profile**

Also *store*, *shop page*.

How a seller presents on the site: a name, an address of their own, a
tagline, where they work, a portrait, a cover, links, and an ordered list of
sections. One per seller.

Is: the seller's public face, and a page a buyer can be sent to.
Is-Not: a second account, a brand separate from the human, or a place stock
lives — the listings on it are the seller's listings.

Lifecycle: hidden until the seller publishes it. Every address it has ever
answered to is kept, so a rename can redirect.

**Store section**

One typed, ordered block of a store page: a story, a gallery.

Is: the only place a store page's content lives — a new kind of content is a
new kind of section.
Is-Not: free-form markup, or a column on the profile.

**Customer**

Also *buyer*, from a seller's side.

A storefront visitor who has bought. Platform-wide, every visitor gets a
customer row; to a seller, a customer is a person with at least one live
fulfillment with them. Favorites, views, and questions do not make a customer.

Can be anonymous, identified by a `cus_*` id minted for their session.
Eventually, when they create an account or make a purchase the move to a known 
customer, associated with a verified email.

**Product**

Also *work*, *good*, *ware*, *craft*, or any category-specific name like *ceramic*, *painting*, etc.

Any hand-made art good that a seller has put up for sale.

Is: hand made, one of a kind, original (only available from this seller.)
Is-Not: mass produced.

**Listing**

A seller's offer of one product for sale. The product is the good; the listing is its record on the platform — photos, description, price, configuration, and stock.

Lifecycle: `draft` → `for sale` → `sold` / `archived`. Draft and archived listings are visible only to their seller.

**Category**

A node in one tree the platform curates. A listing belongs to one category, and the category names which properties the listing may use.

Is: one tree, shared by sellers and buyers.

**Property**

A named, typed fact the catalog knows — "Size", "Color", "Material" (enum, text, or number). A category grants a property to its listings as an attribute (a stated fact), as an option axis (a buyer choice), or both.

**Attribute**

A fixed property→value fact stated on a listing. Feeds the storefront highlights panel and search.

Is-Not: buyer-selectable.

**Option axis**

A choice the buyer makes on a listing — "Size", "Color". Backed by a catalog property, or custom to the listing. Priced one of two ways, chosen at creation: *standalone* (each option carries the full price) or *add-on* (each option shifts the base price).

**Option value**

One choice on an option axis.

**Variant**

One sellable combination of option values, created by the seller one row at a time. Carries its own SKU, price override, and stock.

Is: sparse — only the combinations the seller actually makes.

**Unit**

One serialized physical piece under a variant, individually photographed, described, and priced. One-of-a-kind and vintage lots are units. `available` or `sold`.

**Modifier**

A question asked of the buyer at order time — engraving text, a measurement, a pick from a seller-defined list. Priced flat or per unit of measure; scoped to appear only for chosen option values. The answer freezes onto the order line.

Is-Not: inventory-affecting.

**Quantity break**

A per-listing discount tier: order at least this many, pay this much less per piece.

**Fulfillment flow**

Also *the seller's flow*.

A seller's own ordered list of steps between a parcel being paid for and
being shipped. Every seller has a default flow; a listing may name a
different one.

Is: the seller's working method, written down so the platform can follow it.
Is-Not: the fulfillment state machine, which the platform owns and every
seller shares.

**Flow step**

One step in a flow — label printed, packed, kiln cooled, framed. Carries the
words the seller gave it, its place in the order, and what completing it does
beyond recording it: nothing, or print a shipping label.

Is: completed in order, one at a time, by the seller who owns the parcel.

**Fulfillment event**

One appended row saying something happened to a parcel: a step completed, or
the parcel shipped, delivered, declined, refunded.

Is: the record — append-only, never edited, keeping the step's words as they
were at the time.
Is-Not: the parcel's status, which is the projection of these rows and stays
the platform's contract.

**Activity feed**

One ordered list of everything between a seller and one buyer, or everything
on one order: what the buyer browsed, the order and its money, the parcel's
events, the messages between them.

Is: a merge over sources, read newest first and filterable by kind.
Is-Not: a table — nothing writes a feed row.

**Admin**.

Also *owner*.

Jonathan Beebe & Anna Schmumk, the founders.

**Moderator**.

Anyone responsible for vetting a seller, buyer, product, conversation, behavior, etc. A moderator's job is to keep this platform true to its goals, ensuring the quality of this platform, its products, and the community that emerges around it.