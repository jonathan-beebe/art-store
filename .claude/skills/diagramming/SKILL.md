---
name: diagramming
description: How to diagram the shape and design of systems, features, modules, and files — Mermaid diagrams written into markdown docs that live next to the thing they capture. Use when designing or documenting structure, boundaries, data flow, or interactions, or when the architecture skill needs to draw what it is designing.
---

# Diagramming

Diagrams capture the shape of a design: boundaries, dependencies, data flow,
interactions. They are written as Mermaid blocks inside markdown docs, and
each doc lives next to the thing it diagrams — so the diagram and the code
change together.

Related skills: the `architecture` skill decides the shape; this skill draws
it. Name the doc and the elements in it with the `naming` skill.

## Where a doc lives

A doc is a sibling of its subject, at the subject's altitude:

- **System** — a large system's doc lives at the top of the file hierarchy
  it captures (e.g. the repo root, or the root of that system's tree).
- **Feature / module** — the doc lives inside the folder it captures, next
  to the folder's files.
- **File** — the doc is a sibling of the file it captures.

Name the doc for its subject (see `naming` — a module names a concept): a
doc capturing `checkout/` lives at `checkout/checkout.md`; a doc capturing
`cart.ts` lives beside it as `cart.md`. A system-level doc may be
`architecture.md` at the root of that system.

## Choosing a diagram type

Pick the diagram that answers the question being asked; use several small
diagrams over one that answers everything.

- **C4-style diagrams** — the system altitude: deployables, external systems,
  and the contracts between them. Context for "what talks to this system";
  Container for "what deployables compose it"; Component for the major parts
  inside one deployable. Draw these as flowcharts (subgraphs for boundaries,
  labeled edges for contracts) — Mermaid's `C4Context`/`C4Container` syntax
  renders poorly, so we don't use it (decided 2026-07-05).
- **Flowcharts** (`flowchart TD` / `LR`) — structure and dependency: layers,
  module boundaries, folder shape, which piece imports which. Dependency
  arrows point inward (see `architecture` — layers); a diagram that shows
  them pointing outward or in a cycle is showing a design defect, not a
  drawing mistake.
- **Data flow diagrams** (`flowchart` with labeled edges) — how data moves
  through the pipeline: data → server API → client fetch → view. Label each
  edge with the data or contract that crosses it (DTO, event, schema).
- **Sequence diagrams** (`sequenceDiagram`) — interactions over time: a
  feature's end-to-end flow, a protocol between deployables, the
  coordination layer sequencing core and adapters.
- **State diagrams** (`stateDiagram-v2`) — the core's state transitions:
  states, events, and the pure transitions between them.
- **Class diagrams** (`classDiagram`) — only when relationships between
  classes are the point (composition, interfaces, the rare hierarchy). Not
  for restating what the code already says plainly.
- **ER diagrams** (`erDiagram`) — the data store's shape and relationships.

## Rules

- **One diagram, one question.** Every diagram exists to answer a specific
  question ("what are the deployables?", "how does an order flow?"). State
  the question as prose above the diagram. If a diagram answers two
  questions, split it.
- **Few boxes.** A diagram a reader can't hold in their head has the same
  problem as a system they can't (see `architecture` — system shape). Aim
  for roughly 5–9 elements; past that, move up an altitude and let a lower
  doc hold the detail.
- **Boundaries and contracts are the content.** The valuable information is
  what crosses each boundary and in which direction — label edges with the
  contract, event, or data that flows, not just lines between boxes.
- **Names match the code.** Boxes carry the real names of the modules,
  classes, and deployables they represent (see `naming`), so the diagram
  stays greppable against the source.
- **Diagrams follow the design, at the design's altitude.** Draw what the
  `architecture` skill decided — layers, deployables, one-way data flow —
  and omit detail from lower altitudes; that's the code's job, or a lower
  doc's.
- **A stale diagram is worse than none.** Because the doc is a sibling of
  its subject, changing the subject's shape means updating the doc in the
  same change. If a diagram no longer earns its upkeep, delete it.
- **Prose frames, diagram shows.** Each doc is a markdown file: a sentence
  or two of context and the question, then the Mermaid block, then any
  caveats. The doc is not an essay — the diagram carries the weight.
