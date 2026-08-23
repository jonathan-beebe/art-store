# Identity

Passwordless sign-in for both sites, plus the anonymous-customer cookie the
storefront hangs favorites, carts, and guest orders on before anyone verifies
an address. Code: `app/models/{magic_link,seller,customer}.rb`,
`app/models/concerns/email_address.rb`,
`app/controllers/concerns/{magic_link_sender,customer_identity}.rb`.

## Seller magic-link sign-in

Question: what happens between a seller submitting an email and landing on
the dashboard?

```mermaid
sequenceDiagram
    actor Seller
    participant Login as Auth::SellerSessionsController
    participant MagicLinks as MagicLink
    participant Verify as Auth::MagicLinksController
    participant Sellers as Seller

    Seller->>Login: POST /seller/login (email)
    Login->>MagicLinks: issue(email:, actor_type: :seller)
    MagicLinks-->>Login: [link, token] — only the digest is stored
    Login-->>Seller: flash[:debug_magic_link] (layout prints the URL)

    Seller->>Verify: GET /auth/magic/:token
    Verify->>MagicLinks: find_by_token(token)
    Verify->>MagicLinks: consume!
    Verify->>Sellers: claim(link.email)
    Sellers->>Sellers: find_or_initialize_by(email:), email_verified_at ||= now
    Verify->>Verify: sign_in_seller(seller) — session[:seller_id] = seller.id
    Verify-->>Seller: redirect to seller_root_path (or link.redirect_to)
```

Caveats: a first-time email creates the seller row — there is no separate
sign-up step. An address that is not an address comes back from
`MagicLink.issue` unsaved and undelivered, and the form re-renders with
`422`. A link that is not `usable?` (consumed, or past `expires_at`)
redirects back to `seller_login_path` with an error instead of reaching
`Seller.claim`.

## Customer guest verification with anonymous merge

Question: when a guest verifies an email mid-checkout, which customer row
ends up owning the order, and what happens to the anonymous one?

```mermaid
sequenceDiagram
    actor Customer
    participant Cookie as customer_id cookie
    participant Checkout as Shop::CheckoutsController
    participant Verify as Auth::MagicLinksController
    participant Customers as Customer

    Note over Customer,Cookie: anonymous customer row already exists (cookie set on first request)
    Customer->>Checkout: POST /checkout, email unverified
    Checkout->>Checkout: send_magic_link(email, redirect_to: /orders/:id/pay)
    Checkout-->>Customer: flash[:debug_magic_link]

    Customer->>Verify: GET /auth/magic/:token
    Verify->>Verify: consume!
    Verify->>Customers: claim(link.email, current: customer_from_cookie)
    alt no row owns the address yet
        Customers->>Customers: create!(email:, email_verified_at: now)
    else address already held, cookie points elsewhere or nowhere
        Customers->>Customers: owner.verify! (sets email_verified_at if unset)
    else cookie's row is anonymous, address unclaimed
        Customers->>Customers: anonymous.claim_address(email)
    else cookie's row is anonymous, a different row already owns the address
        Customers->>Customers: owner.verify!.absorb(anonymous)
    end
    Customers-->>Verify: resulting customer
    Verify->>Verify: sign_in_customer(customer) — session[:customer_id], cookie updated
    Verify-->>Customer: redirect to link.redirect_to (/orders/:id/pay)
```

Caveats: a cookie already pointing at the account signs in without a merge —
`Customer.claim` only treats `current` as an anonymous row when it holds no
address. `Customer#absorb` moves favorites, carts, orders, listing events, and
notifications through the associations `Customer` declares
(`Customer::MERGED_ASSOCIATIONS`) inside a transaction. The anonymous row is
never deleted — the `customer_merges` row lets a stale cookie on a second
device resolve forward to the verified customer (`Customer.from_cookie`
follows it).

## Which identity a storefront request resolves to

Question: given a request, which customer does `current_customer` return?

```mermaid
flowchart TD
    start(["storefront request"]) --> guard{"signed in?\nsession[:customer_id]\nverified? scope"}
    guard -- yes --> useGuard["use the signed-in customer"]
    guard -- no --> cookie{"customer_id cookie\nresolves to a row?"}
    cookie -- yes --> useCookie["use that customer\n(Customer.from_cookie follows\na customer_merges row if merged)"]
    cookie -- no --> create["Customer.create!\n(new anonymous row)"]
    useGuard --> remember["remember_customer:\nrewrite the signed cookie"]
    useCookie --> remember
    create --> remember
```

Caveats: this is `CustomerIdentity#current_customer`, run by `Shop::BaseController`'s
`before_action :resolve_customer_identity` on every storefront request. It
never runs on `/auth/magic/:token`, `/login`, or `/logout`
(`Auth::BaseController` reads the cookie directly through
`Customer.from_cookie`), so a seller clicking a seller link cannot
accidentally create a customer row. A signed-in customer must also be
`verified` (`Customer.verified` scope, `email` not null) — the cookie alone
never reaches `/account` (`require_customer!`).
