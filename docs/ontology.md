# Domain ontology

**Sellers**

Also *artists*, *makers*.

Is: a creative human.
Is-Not: a brand, a group, a store.

Owns the copyright to the goods they sell.

Could a seller be a studio? Maybe, if headed by a human and creating hand-made art.

**Product**

Also *works*, *goods*, *wares*, *crafts*, or any category-specific name like *ceramics*, *painting*, etc.

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

**Customers**

Also *buyers*.

Can be anonymous, identified by a `cus_*` id minted for their session.
Eventually, when they create an account or make a purchase the move to a known customer, associated with a verified email.

**Admins**.

Also *owners*.

Jonathan Beebe & Anna Schmumk, the founders.

**Moderator**.

Anyone responsible for vetting a seller, buyer, product, conversation, behavior, etc. A moderator's job is to keep this platform true to its goals, ensuring the quality of this platform, its products, and the community that emerges around it.