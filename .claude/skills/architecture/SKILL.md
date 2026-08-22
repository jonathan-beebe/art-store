---
name: architecture
description: How to organize software at every altitude — system shape, deployables, layers (functional core / imperative shell), and files & folders. Use when designing a feature or system, shaping a module, defining boundaries or contracts, or deciding where code and files belong.
---

# Architecture

Architecture is deciding how to organize the solutions to problems at each
altitude, and within each deployable. It is concerned with how systems
interact: interfaces, contracts, composability, data flows.

Related skills: name every boundary, layer, and module with the `naming`
skill. Draw the shapes you design — systems, layers, data flows — with the
`diagramming` skill. When architecture work reaches the unit level, hand
off: `write-class` for classes, `write-function` for functions.

## Altitudes

Software exists at five altitudes: system ⊃ feature ⊃ module ⊃ unit ⊃ line.
Architecture operates at each one. Know which altitude you're working at, and
produce the artifacts of that altitude.

| Altitude | Scope | Activities | Artifacts produced |
| --- | --- | --- | --- |
| **5 — System** | the whole product | design the architecture, define sub-systems and boundaries, audit system health, scope work | layers, boundaries, sub-systems, tickets |
| **4 — Feature** | one user-facing capability | design a feature, plan/break down the work, build the feature, verify end-to-end, ship | feature folder, wiring in the composition root, e2e tests |
| **3 — Module** | one concept / folder | shape a module, define its public API, place files, assign layer membership | files, folders, exports |
| **2 — Unit** | one function / class / type | write a function, write a class, write a method, design a type, write a unit test | functions, classes, methods, types, tests |
| **1 — Line** | one statement / expression | name a variable, choose a data structure, shape a conditional, extract a constant | statements, expressions, names |

## System shape

- Favor simple systems. Every system should be small enough to hold in your
  head and explain in a sentence. When one grows past that, don't manage the
  complexity — split it into sub-systems, each simple again, with a clear
  boundary between them.
- Favor composability. Necessary complexity lives in the *organization and
  composition* of simple parts, not inside the parts themselves. Build small
  pieces with clean contracts and compose them into larger behavior; a
  complex organization of simple systems is debuggable and testable
  piecewise, a simple organization of complex systems is neither.

## Deployables

- A software system must be built into a deployable. Sometimes a system maps
  cleanly to one deployable unit; as it grows it subdivides into sub-systems,
  which map to one or more deployables that together deliver the working
  system.
- A system splits first into deployables — client, server, data store.
  Deployables are systems (or sub-systems): separately deployed, connected
  only by contracts (API schemas, DTOs). The full-stack pipeline is composed
  of pieces chained end to end: data → server API → client fetch → view.
- The patterns are fractal and repeat *inside every deployable*. The server
  has its own core (domain model), adapters (DB access, outbound HTTP),
  coordination (route handlers), and entry (server bootstrap). The client has
  its own core (models, state transitions), adapters (API client, DOM
  rendering), coordination (feature controllers), and entry (app bootstrap).
- A deployable's boundary is a hard contract: schema-validated at the edge
  (parse, don't validate). Neither deployable imports the other's internals;
  they share only the contract types.

## Layers (within a deployable)

Base pattern: functional core / imperative shell. Four levels, from inside
out:

1. **Core** — pure functions and types. Domain logic, state transitions,
   calculations. No I/O, no DOM, no clock, no random.
2. **Adapters** — the code that touches the world: persistence, network, DOM
   rendering. Each adapter wraps one external thing. This is where side
   effects live.
3. **Coordination** — orchestrators/controllers that sequence core and
   adapters into features (see the `naming` skill's coordination rule).
   Delegates only; owns no business logic.
4. **Entry point** — the composition root (`main`/`app`). Constructs
   everything, wires it together, starts it. The only place that knows the
   whole graph.

Rules that bind across the layers:

- Named patterns (MVC, MVP, MVVM) are mappings onto these layers: 
  Model → core; View → an adapter over the display
  (rendering is I/O); Controller / Presenter / ViewModel → coordination.
  Whatever names the pattern uses, the invariant it protects is the same:
  strong model/view separation — models never know views exist. Follow the pattern the
  codebase has chosen; map its names to these layers to know which rules bind.
- Dependencies point inward, only inward. Shell imports core; core never
  imports shell. A lower layer never knows a higher layer exists.
- Favor one-way data flow. One loop: event → state transition (pure, in the
  core) → render from the new state. State flows down, events flow up; the
  view never writes state directly and nothing renders from anything but
  state. Each piece of state has exactly one owner; everything else receives
  it read-only or derives from it (computed, not stored). Two-way binding and
  scattered mutation make "who changed this?" unanswerable.
- Boundaries are types owned by the inner layer — core defines the interface
  (`Clock`, `UserStore`), adapters implement it. That's dependency inversion;
  it's what keeps the core testable without mocks of the outside world.
- Logic drifts toward the core over time: if a coordinator or adapter grows
  an `if` about the domain, extract it into a pure function and call it (see
  `write-function`).
- No cycles, at any level — two modules that import each other are one module
  that hasn't admitted it, or a missing third concept both should depend on.
- Test probe: the core's tests run with no test doubles for I/O. If a "pure"
  test needs a mock, the code under test is in the wrong layer.

## Files & folders

- Organize by feature/domain first, by layer second — folder names should say
  what the app *does* (`checkout/`, `profile/`), not what framework it uses.
- Things that change together live together — a unit's test, types, and
  styles colocate with it (or follow the project's stated convention).
- One concept per file; file named for its primary export (see `naming`).
- A folder is a module with a public API: what it exports deliberately is the
  surface; reaching deep into another folder's internals is a boundary
  violation.
- Dependency direction between folders follows the layer rules — shared code
  sits below the features that use it (`shared/` never imports from a
  feature).
- Start flat; add structure when forced. A new folder needs a nameable
  concept and ~3+ files. Empty scaffolding "for later" is speculative
  generality.
- Deletability test: a feature done right disappears by deleting its folder
  plus its wiring line(s) in the composition root. The more places a feature
  leaks into, the worse its boundaries.

## Project-specific rules

- Adhere to WCAG guidelines when architecting UI features.
- If you find two conflicting patterns in the codebase, ask the user whether
  one should be refactored away in favor of the canonical pattern.
- Unit test individual core functions; integration test the shell to ensure
  business and customer value is intact.
- Code is for *what and how*; comments are for *why and when*.
