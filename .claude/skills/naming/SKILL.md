---
name: naming
description: How to name things — variables, functions, methods, classes, types, modules, and files. Use whenever you need to name anything.
---

# Naming

This file ensures the names of our variables, functions, classes, types,
modules, and files all have meaning and fit our design.

Naming is a design probe: if you can't name a thing honestly and briefly,
the design is wrong — an "and" in a function name means split it; a class
you can only call a bare, unqualified `Manager` or `Helper` means the
concept isn't crisp yet. When a name won't come, go back to the design
skill for that artifact (`write-function`, `write-class`,
`write-class-method`, `architecture`), not to a thesaurus.

## Universal rules (apply to everything)

- Honest — the name covers everything the thing does and nothing it doesn't.
- Intent over implementation — name what it's *for*, not how it works
  (`cache`, not `hashMap`).
- Length proportional to scope — `i` is fine in a 3-line loop; an exported
  symbol gets a fully descriptive name.
- One word per concept, one concept per word — pick `fetch` OR `get` OR `load`
  for a given meaning and use it everywhere. Know the domain of the project
  and the vocabulary of the codebase and defer where a clear vocabulary has
  been established; otherwise stick with the conventions and common language
  you are working in.
- Domain vocabulary over invented terms.
- Role suffixes vs noise words — a shared suffix is *good* when it names a
  real category with one consistent meaning, applied to every member of the
  family (`*View`, `*Repository`, `*Error`, `use*` for hooks). It's noise when
  it adds no information (`data`, `info`, `stuff`) or means something
  different every time it appears (`Manager` that variously caches, validates,
  and coordinates). Two tests: (1) delete the word — if nothing is lost, it's
  noise; (2) do all names carrying it share a shape/contract? If yes, it's a
  family marker; keep it and enforce it. `Manager` is a role some code
  occupies, but there is usually a more specific verb for the kind of
  managing being done — `Processor`, `Publisher`, `Orchestrator` — and a
  more specific verb than those in turn; prefer the most specific word that
  stays honest.
- Coordination is a real level — some layer must orchestrate: sequence steps,
  route between components, own a lifecycle. Role words like `Controller`,
  `Orchestrator`, `Coordinator`, `Router` are legitimate *at that level*, with
  two requirements: (1) qualify the role with its scope — a *workflow*
  orchestrator, a *feature* controller (`CheckoutWorkflowOrchestrator`,
  `ProfileFeatureController`), never a bare `Controller` or `Manager`;
  (2) the name is only honest if the thing *only* coordinates — it delegates
  the real work to the lower-level pieces it sequences. If it also computes,
  validates, or persists, the name is lying. These words never belong on
  low-level functions or plain data objects.
- Always favor the affirmative — what something is or does, not what it isn't
  or doesn't do.
- Booleans read as assertions — `isValid`, `hasChildren`, `canRetry`; never
  negated (`isNotReady`).
- Conventional pairs — begin/end, open/close, add/remove, create/destroy.
- Include units/qualifiers when ambiguous — `timeoutMs`, `widthPx`.
- Searchable and pronounceable.

## Per-artifact rules

- **Variable / constant** — noun naming a *value*. Plural for collections.
  Boolean assertion form. Constants named for meaning (`MAX_RETRIES`), not
  value.
- **Function** — verb phrase, and the name must carry *full context* because a
  function stands alone at the call site.
  - Pure, value-returning: name the result — `computeTotal`, `parseDate`,
    `fullName(user)`.
  - Side-effecting: imperative verb naming the effect — `saveUser`,
    `sendEmail`. The verb is what makes the effect visible.
  - Predicates: `isX` / `hasX` / `canX`. Transformations: `toX` / `xFromY` /
    `formatX`.
  - Banned vague verbs: `handle`, `process`, `manage`, `do`.
- **Method** — same verb rules as functions, but the receiver is the implicit
  subject, so the name *borrows context from the object* and must not repeat
  it: `user.save()` not `user.saveUser()`; `cart.add(item)` not
  `cart.addItemToCart(item)`. Test: read the call site aloud as a sentence —
  `account.withdraw(50)`. Queries named for the value returned, commands named
  for the effect (pairs with command/query separation).
- **Class / object** — noun phrase naming a *role or concept*, never an
  action. Concrete over generic — but split port from adapter: an interface
  is named in domain language for the role it plays (`PaymentGateway`), and
  only the concrete implementation names the technology
  (`StripePaymentGateway`). Vendor and technology words belong on adapters,
  never on the interfaces the rest of the code depends on. Vague role words
  (`PaymentManager`) are wrong at both levels. A verb-in-disguise class name
  (`Validator`, `Processor`) usually means a function wanted to exist —
  qualified coordination-level roles are the exception (see coordination rule
  above). Instances named for their role in context (`primaryButton`, not
  `button1`).
- **Type / interface** — name the capability or shape (`Clock`,
  `Serializable`, `UserRepository`). Meaningful type params when non-trivial
  (`Key`, `Row`) — bare `T` only for simple containers.
- **Event** — past-tense fact in domain language: `OrderPlaced`,
  `PaymentCaptured`. An event records something that *happened*, so it is
  never imperative (`PlaceOrder` is a command) and never technical
  (`OrderUpdateMessage` names the plumbing, not the fact). Applies anywhere
  events appear: DOM/custom events, analytics events, log events, pub/sub
  topics.
- **Module / file** — named for the concept it exports; file name matches its
  primary export.

**Summary of the differences:** a variable names a value, a function name
carries its own full context, a method name is completed by its receiver, a
class names a role, an event names a past fact, a module names a concept.