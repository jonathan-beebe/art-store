# Identity

Passwordless sign-in for both sites, plus the anonymous-customer cookie the
storefront hangs favorites, carts, and guest orders on before anyone verifies
an address. Code: `app/actions/auth/`, `app/actions/customers/`,
`app/controllers/concerns/customer_identity.rb`,
`app/domain/customers/identity_plan.rb`.

## Seller magic-link sign-in

Question: what happens between a seller submitting an email and landing on
the dashboard?

```mermaid
sequenceDiagram
    actor Seller
    participant Login as Auth::SellerSessionsController
    participant Send as Auth::SendMagicLink
    participant MagicLinks as magic_links
    participant Verify as Auth::MagicLinksController
    participant Claim as Auth::ClaimSellerIdentity

    Seller->>Login: POST /seller/login (email)
    Login->>Send: call(email:, actor_type: SELLER)
    Send->>MagicLinks: create!(token_digest, email, actor_type, expires_at)
    Send-->>Seller: flash[:debug_magic_link] (layout prints the URL)

    Seller->>Verify: GET /auth/magic/:token
    Verify->>MagicLinks: for_token(token).first
    Verify->>MagicLinks: consume!(now)
    Verify->>Claim: call(email:)
    Claim->>Claim: Seller.find_or_initialize_by(email:), email_verified_at ||= now
    Verify->>Verify: sign_in_seller(seller) — session[:seller_id] = seller.id
    Verify-->>Seller: redirect to seller_root_path (or link.redirect_to)
```

Caveats: a first-time email creates the seller row — there is no separate
sign-up step. An expired or already-consumed link (`Domain::Auth::MagicLinkStatus`)
redirects back to `seller_login_path` with an error instead of reaching
`ClaimSellerIdentity`.

## Customer guest verification with anonymous merge

Question: when a guest verifies an email mid-checkout, which customer row
ends up owning the order, and what happens to the anonymous one?

```mermaid
sequenceDiagram
    actor Customer
    participant Cookie as customer_id cookie
    participant Checkout as Shop::CheckoutsController
    participant Verify as Auth::MagicLinksController
    participant Claim as Customers::ClaimCustomerIdentity
    participant Plan as Domain::Customers::IdentityPlan
    participant Merge as Customers::MergeAnonymousCustomer

    Note over Customer,Cookie: anonymous customer row already exists (cookie set on first request)
    Customer->>Checkout: POST /checkout, email unverified
    Checkout->>Checkout: SendMagicLink(email, redirect_to: /orders/:id/pay)
    Checkout-->>Customer: flash[:debug_magic_link]

    Customer->>Verify: GET /auth/magic/:token
    Verify->>Verify: consume!(now)
    Verify->>Claim: call(email:, current: customer_from_cookie)
    Claim->>Plan: decide(anonymous_customer_id, verified_customer_id)
    alt no row owns the address yet
        Plan-->>Claim: create_verified
        Claim->>Claim: Customer.create!(email:, email_verified_at: now)
    else address already verified, cookie points elsewhere or nowhere
        Plan-->>Claim: sign_in_existing
        Claim->>Claim: owner.update!(email_verified_at: now) if unset
    else cookie's row is anonymous, address unclaimed
        Plan-->>Claim: claim_anonymous
        Claim->>Claim: anonymous.update!(email:, email_verified_at: now)
    else cookie's row is anonymous, a different row already owns the address
        Plan-->>Claim: merge_anonymous_into
        Claim->>Merge: call(anonymous:, verified: owner)
        Merge->>Merge: re-point favorites/carts/orders/listing_events/notifications, insert customer_merges row
    end
    Claim-->>Verify: resulting customer
    Verify->>Verify: sign_in_customer(customer) — session[:customer_id], cookie updated
    Verify-->>Customer: redirect to link.redirect_to (/orders/:id/pay)
```

Caveats: `Domain::Customers::IdentityPlan.decide` folds to `sign_in_existing`
whenever `anonymous_customer_id == verified_customer_id` too — a customer
re-verifying an address they already hold. `MergeAnonymousCustomer` walks
`Domain::Customers::OwnedTables::ALL` inside a transaction and skips any
table/column that does not exist yet (a guard for schema drift across tickets
landing in parallel). The anonymous row is never deleted — the
`customer_merges` row lets a stale cookie on a second device resolve forward
to the verified customer (`Customers::ResolveCustomerFromCookie` follows it).

## Which identity a storefront request resolves to

Question: given a request, which customer does `current_customer` return?

```mermaid
flowchart TD
    start(["storefront request"]) --> guard{"signed in?\nsession[:customer_id]\nverified? scope"}
    guard -- yes --> useGuard["use the signed-in customer"]
    guard -- no --> cookie{"customer_id cookie\nresolves to a row?"}
    cookie -- yes --> useCookie["use that customer\n(ResolveCustomerFromCookie follows\na customer_merges row if merged)"]
    cookie -- no --> create["Customer.create!\n(new anonymous row)"]
    useGuard --> remember["remember_customer:\nrewrite the signed cookie"]
    useCookie --> remember
    create --> remember
```

Caveats: this is `CustomerIdentity#current_customer`, run by `Shop::BaseController`'s
`before_action :resolve_customer_identity` on every storefront request. It
never runs on `/auth/magic/:token`, `/login`, or `/logout`
(`Auth::BaseController` reads the cookie directly through
`Customers::ResolveCustomerFromCookie`), so a seller clicking a seller link
cannot accidentally create a customer row. A signed-in customer must also be
`verified` (`Customer.verified` scope, `email` not null) — the cookie alone
never reaches `/account` (`require_customer!`).
