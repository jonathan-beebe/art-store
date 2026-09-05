# Domain ontology

## People

**Seller**

Also *artist*, *maker*.

Is: a creative human.
Is-Not: a brand, a group, a store.

Owns the copyright to the goods they sell.

**Customer**

Also *buyer*, from a seller's side.

Platform-wide: every storefront visitor, each with a customer row. To a
seller: a person with at least one paid parcel that still stands with them.
Favorites, views, and questions do not make a customer.

Can be anonymous, identified by a `cus_*` id minted for their session. They
become a known customer when a magic link verifies their email; the row then
carries that email.

**Admin**

Also *owner*, *moderator*.

Jonathan Beebe & Anna Schmunk, the founders. An admin moderates the
platform: vets sellers, buyers, products, and conversations, and keeps the
platform true to its goals.

Is: seeded. The admin site has no sign-up.

Relationships: reaches every seller, customer, listing, order, and parcel;
removes a listing; blocks a customer; refunds a parcel; runs the payout.

## Store

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

**Store slug**

One address a store has answered to, under `/s/{slug}`.

Is: the current address (one row per store), or a retired one a rename left
behind.

Lifecycle: a rename retires the old row and mints a new one. A retired
address redirects to the current one for thirty days, then answers 404.

## Catalog

**Product**

Also *work*, *good*, *ware*, *craft*, or a category-specific name such as
*ceramic* or *painting*.

Any hand-made art good that a seller has put up for sale.

Is: hand made, original (only available from this seller), one of a kind or
a small run. A one-of-a-kind piece is a unit; a run is a variant with stock.
Is-Not: mass produced.

**Listing**

A seller's offer of one product for sale. The product is the good; the
listing is its record on the platform — photos, description, price,
configuration, and stock.

Lifecycle: `draft` → `for_sale` → `sold`; `archived` from `draft` or
`for_sale`; `sold` → `for_sale` when a declined charge restores the stock it
took. Draft and archived listings are visible only to their seller. A
removed listing (spec.md §5) leaves the storefront in any status.

**Listing image**

One photo on a listing, in the order the seller set.

Is: the set the listing page shows. The lowest position is the cover, the
image every other surface renders.

Relationships: belongs to one listing.

**Description section**

One typed block of a listing's description: text, specs, a size chart, an
FAQ, care instructions, or a disclaimer.

Is: data the listing page renders by kind.
Is-Not: pasted prose.

Relationships: belongs to one listing; ordered by position.

**Listing removal**

An admin taking a listing off the storefront, with a reason.

Is: the platform's word on a listing. It outranks the seller's status.

Lifecycle: `temporary` may be lifted; `permanent` may not. At most one
removal is active on a listing at a time.

Relationships: belongs to one listing. While it stands, the listing leaves
browse, search, `/art/{slug}`, and favorites, and blocks its cart line at
checkout.

**Favorite**

A customer's bookmark on a listing.

Is: a toggle. It exists or it does not.
Is-Not: a cart line.

Relationships: belongs to one customer and one listing.

## Catalog configuration

**Category**

A node in one tree the platform curates. A listing belongs to one category,
and the category names which properties the listing may use.

Is: one tree, shared by sellers and buyers. A category carries a
materialized `path` for `/browse/{categoryPath}` and a `browsable` flag.

**Property**

A named, typed fact the catalog knows — "Size", "Color", "Material" (enum,
text, or number). A category grants a property to its listings as an
attribute (a stated fact), as an option axis (a buyer choice), or both.

**Property value**

One enumerated value of a property — Gold, Silver for Metal.

Relationships: belongs to one property; named by attributes and option
values that use the catalog vocabulary.

**Attribute**

A fixed property→value fact stated on a listing. Feeds the storefront
highlights panel. The Medium attribute also feeds search.

Is-Not: buyer-selectable.

**Option axis**

A choice the buyer makes on a listing — "Size", "Color". Backed by a catalog
property, or custom to the listing. Priced one of two ways, chosen at
creation: *standalone* (each option carries the full price) or *add-on*
(each option shifts the base price).

**Option value**

One choice on an option axis, with the price delta choosing it adds.

**Variant**

One sellable combination of option values, created by the seller one at a
time or generated from the axes in one action. Carries its own SKU, price
override, and stock.

Is: sparse — only the combinations the seller makes.

**Variant option**

One axis's chosen value inside a variant.

Is: at most one row per axis per variant.

**Unit**

One serialized physical piece under a variant, individually described
(`condition_note`, `specs_json`) and priced. One-of-a-kind and vintage lots
are units. `available`, `reserved`, or `sold`.

**Modifier**

A question asked of the buyer at order time — engraving text, a measurement,
a pick from a seller-defined list. Priced flat or per unit of measure;
scoped to appear only for chosen option values. The answer freezes onto the
order line.

Is-Not: inventory-affecting.

**Modifier option**

One choice on a select-kind modifier — a font, a paper stock — with its own
price delta.

**Modifier scope**

One option value a modifier is gated to show for. A modifier with zero
scopes shows for every configuration.

**Quantity break**

A per-listing discount tier: order at least this many, pay this much less
per piece.

## Buying and money

**Cart**

A customer's in-progress selection, held until checkout.

Is: one per customer, holding cart items from any number of sellers.

Lifecycle: spawns an order at checkout.

**Order**

A customer's purchase, spanning one or more sellers.

Lifecycle: `pending_verification` (guest) or `awaiting_payment` (verified)
→ `paid` or `payment_failed` → `partially_shipped` / `shipped` →
`delivered`. `cancelled` is reached from any state before payment.
`refunded` is reached once every parcel is declined or refunded.

Relationships: placed by one customer; contains order items; attempts
payments; splits by seller into parcels.

**Order item**

A snapshot of one purchased listing: title, unit price, quantity, and the
chosen configuration as they were at checkout.

Is: written once. The order reads the same after the seller edits or
deletes the listing.

**Payment**

One charge attempt against an order's card: `approved` or `declined`.

Is: one row per attempt. A retry after a decline is a new row.

Relationships: belongs to one order.

**Fulfillment**

Also *parcel*; the seller portal's *order*.

One seller's slice of an order: what that seller owes to ship, and what they
are owed once it is delivered.

Lifecycle: `awaiting_shipment` → `shipped` → `delivered`. `declined` (the
seller turns it down before it ships, stock restored) and `refunded` (an
admin settles it) are the two settled endings.

Relationships: belongs to one order and one seller; produces ledger
entries; carries the platform fee taken from its subtotal.

**Refund**

Money sent back to a customer for one parcel, always the whole subtotal.

Is: written once. The row is the refund.

Relationships: at most one per parcel; issued by the seller (declining) or
an admin (settling a dispute); writes a `refunded` ledger entry.

**Ledger entry**

One movement of escrowed money for one seller: `held` (order paid),
`released` (parcel delivered), `refunded` (parcel declined or refunded),
`paid_out` (included in a payout).

Is: append-only. A seller's balance is the fold of their entries.
Is-Not: a mutable balance column.

**Payout**

One weekly settlement of a seller's released, unpaid escrow.

Lifecycle: created once per (seller, Monday–Sunday period) by
`payouts:run`. Its existence is its state.

Relationships: belongs to one seller; writes one `paid_out` ledger entry.

## Fulfillment

**Fulfillment flow**

Also *the seller's flow*.

A seller's own ordered list of steps between a parcel being paid for and
being shipped. A seller's first flow is their default; a listing may name a
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
on one parcel (fulfillment): what the buyer browsed, the order and its
money, the parcel's events, the messages between them.

Is: a merge over sources, read newest first and filterable by kind.
Is-Not: a table — nothing writes a feed row.

## Messaging and identity

**Conversation**

One thread, of one of four kinds: a listing question, a parcel thread,
seller support, customer support. Every kind has two sides. On the two
support kinds one side is the desk: every admin, collectively.

Lifecycle: `open` → `resolved` → `open` again on a reopen or on a reply
from the side that could not resolve it.

Relationships: names its two participants and, on the kinds that carry one,
a listing, a parcel, or an order; holds messages; resolved by a seller or
the desk.

**Message**

One post in a conversation, by a seller, a customer, or an admin. May name
the message it replies to.

Lifecycle: sent, then read by its recipient.

Relationships: belongs to one conversation; may be published as a listing
FAQ.

**Notification**

A line in a seller's or a customer's header: "Item sold", "Order shipped".

Is: in-app today; the same line goes out by email when the config names
`mail`.

Lifecycle: unread → read.

Relationships: addressed to one seller or one customer.

**Magic link**

A one-time, expiring, hashed token that signs someone in without a
password.

Is: the only sign-in for sellers, customers, and admins.

Lifecycle: usable → expired or consumed.

Relationships: matched to a seller, a customer, or an admin by email.

**Customer block**

An admin stopping a customer from buying and posting, with a reason.

Is: a stop on spending and messaging. Browsing, favorites, and reading
threads stay open.

Lifecycle: active until lifted. At most one active block per customer.

**Help article**

One article in the seller support hub, written as a markdown file with a
slug, a group, a title, and a position.

Is: a file under `resources/help/seller/`, parsed per request.
Is-Not: a database row.

## Analytics

**Funnel**

An admin-defined path through the analytics event vocabulary: a name and an
ordered list of event names.

Is: a tile on the admin analytics home, ordered by position. Visitors is
every funnel's implied first step.

Relationships: reads analytics events; scoped to the store, one listing, or
one seller.
