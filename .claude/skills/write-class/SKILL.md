---
name: write-class
description: How to design and write a class — justify its existence, draw its boundary, design the public contract, encapsulate state, keep logic in pure functions. Use whenever you create or substantially rework a class. Method bodies defer to write-class-method.
---

# Writing a class

A class bundles state with the operations that guard it. Writing one is a
design act, not just a coding act — you're drawing a boundary, defining a
public contract, and deciding what to encapsulate, before any method body is
written.

Related skills: name the class with the `naming` skill (noun phrase naming a
role, concrete over generic). Write each method body with the
`write-class-method` skill. Extract logic into pure functions with the
`write-function` skill. Layer placement and dependency direction come from
the `architecture` skill.

## Rules

- **Named first** — use the `naming` skill (class rules: noun phrase naming a
  role, concrete over generic, no verb-in-disguise names). If you can't name
  the role crisply, the boundary is wrong — go back to shaping, not to a
  thesaurus.
- **Justify its existence** — a class exists to *model*: (1) a real-world
  object or concept that makes sense as an encapsulated unit, or (2)
  something best expressed as an object in an object-oriented codebase. A
  class is a convenient way to group state with the methods that operate on
  it to model that object — typically owning an invariant to protect across
  operations. It is *not* a grouping of utility functions: everything that
  can be a plain function should be one (see `write-function`), and a drawer
  of related functions with no object to model wanted to be a module. The
  methods of a justified class may still be thin wrappers over pure functions
  (see "keep the class light" below) — the justification is the modeled
  concept, not the method bodies.
- **Single responsibility** — one role, one reason to change, describable in
  a sentence without "and". A class is *one* concept made operational, not a
  drawer of related utilities.
- **Boundaries before methods** — decide what the class *owns* before
  writing what it *does*: which state it holds, which invariant it guards,
  which decisions are its versus its collaborators'. A method that mostly
  operates on another object's data marks a boundary drawn in the wrong
  place.
- **The public API is a contract**
  - Design it from the caller's side — sketch the ideal call sites first,
    then build the class that satisfies them.
  - Small and deliberate: every public member is a promise you must keep;
    everything defaults to private and is promoted only when a caller
    demonstrates the need.
  - Expose behavior, not state — no getter/setter pairs mirroring fields.
    Callers ask the object to do things; they don't reach in, compute, and
    write back.
- **Encapsulate the private bits** — the invariant must be unbreakable from
  outside: no leaking mutable internals, no back doors. Internals are
  mechanism, free to change without touching any caller; if changing a
  private field breaks callers, encapsulation has already failed.
- **Keep the class light — delegate to the functional core**
  - The class is a thin stateful wrapper; the real computation lives in pure
    functions it imports (the core, trusted functional libraries). A method's
    shape: read own state → call pure function → store or return the result.
  - Balance point: the class owns *state, lifecycle, and sequencing*; the
    functions own *logic*. Lean hard toward the functions — a heavy class is
    untestable logic hiding behind a constructor.
  - When a method grows domain `if`s, extract them to a pure function
    (`write-function`) and call it — logic drifts toward the core (see
    `architecture`).
- **Construction establishes the invariant**
  - An instance is valid from birth: the constructor receives everything
    needed and rejects bad values. No two-phase `init()`, no objects that
    exist in a not-yet-usable state.
  - Collaborators arrive via the constructor as interfaces the inner layer
    owns (dependency inversion) — never reached for from inside.
  - Constructors store and validate; they don't compute or do I/O. Work
    happens in methods, wiring happens in the composition root.
- **Hierarchy: as flat as possible, composition first**
  - Default is *no* hierarchy. Inheritance only for a true is-a with a
    stable base contract where every subtype substitutes cleanly (LSP);
    otherwise compose, or share an interface instead of a parent.
  - When a hierarchy is genuinely sensible, keep it one level deep from an
    abstract base or interface. Deep trees couple every leaf to every
    ancestor's decisions.
- **SOLID, applied at this altitude** — single responsibility (above); open
  for extension via new implementations and composition, closed to edits of
  working code; substitutable subtypes; small role-specific interfaces
  rather than one fat one; depend on abstractions the core owns, not on
  concretions.
- **Testable** — constructible in isolation: real core functions, fakes only
  for injected adapter interfaces. If constructing it for a test is painful,
  it owns too much; if testing it requires mocking its own internals, the
  boundary is wrong.
- **Communicates to peers** — the public surface *is* the documentation: a
  peer can use the class correctly without reading its internals. The class
  name plus its method list should read as a coherent role, not a grab bag.
