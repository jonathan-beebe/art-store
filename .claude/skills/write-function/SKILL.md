---
name: write-function
description: How to write a pure or standalone function — responsibility, inputs, outputs, errors, determinism, side effects, testability. Use whenever you create or substantially rework a function. For methods on a class, use write-class-method instead.
---

# Writing a function

These rules are for pure/standalone functions. Methods are covered by the
`write-class-method` skill. Where a function fits in the system — core,
adapter, coordination — is the `architecture` skill's domain; most functions
you write should be core functions.

Related skills: name the function with the `naming` skill (verb phrase, full
context, effect verbs). If you can't name it honestly without "and", split it
before writing it.

## Rules

- **Named first** — use the `naming` skill. The name describes the function's
  purpose in the system.
- **Single responsibility** — one purpose, one role, one job. Small size ===
  doing one thing; large size === doing too much.
- **Small** — fits on screen, understood at a glance, not intimidating.
- **Inputs are clear**
  - Everything the function needs is passed in as parameters, not pulled in
    from outside the system (no hidden dependencies). E.g. if it uses the
    current time, `time` is a parameter — it never calls `getCurrentTime()`
    itself.
  - Arguments are clear, ordered, sensible, and local — necessary for the
    problem, not imported from outside.
  - Prefer input params to nesting function calls; avoid deeply nested
    dependencies.
  - Keep the public interface simple — most functions take very few
    parameters. An orchestrator may take an object or many params (its job is
    to manage a lot); that's the exception.
  - Protect against bad values at runtime.
- **Outputs are clear**
  - If the return value is optional, it's clear what a missing value means.
  - The relationship between inputs and outputs is clear.
- **Errors are clear and expected**
  - Clearly manages its error conditions, invalid inputs, and exceptions.
  - If it throws, it's clear why it throws and what each error means.
  - Error paths are sensible, predictable, and anticipated — don't surprise
    the caller.
- **Deterministic** — same input always produces the same output. Not all
  functions can be, but most should be; only a narrow set of work should
  ever be non-deterministic.
- **Always terminates** — it won't loop forever.
- **No unnecessary side effects** (assignment, logging, printing, I/O)
  - It doesn't change state elsewhere in the code, and doesn't mutate
    variables or parts of the system unrelated to the work at hand.
  - State-changing calls (setting a cookie, changing a global) belong near
    the top of the call stack, *not* embedded deep in other functions. Higher
    layers coordinate side effects and pass down the functions that perform
    them (implemented in adapters); low-level code never makes a
    side-effecting call the coordinators above it are unaware of (see
    `architecture` — layers).
  - Most functions have no side effects. Those with side effects are
    properly organized so it is obvious.
- **Isolated & composable** — no hidden dependencies, clear inputs, clear
  outputs; composes with other functions (functional core).
- **Testable** — testing itself defers to the TDD/test skills, but plan for
  testability: a pure function with clear inputs and outputs needs no test
  doubles.
- **Communicates to peers**
  - Not overly clever; it's obvious how it works.
  - Quality without a name: does not hurt to look at, easy to use.
  - Minimize required context — the knowledge of the system or language a
    reader needs to understand it.
  - Code is for *what and how*; comments are for *why and when*.
