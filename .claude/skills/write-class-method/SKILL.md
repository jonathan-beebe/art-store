---
name: write-class-method
description: How to write a method on a class — receiver as implicit input, command/query separation, side effects limited to the receiver, determinism relative to state. Use whenever you write or rework a method body. For standalone functions, use write-function instead.
---

# Writing a class method

A method is a function defined on a class, implicitly passed a reference to
the object it acts on. Most `write-function` rules apply; the differences
below come from having a receiver and owned state.

Related skills: name the method with the `naming` skill (method rules: the
receiver is the subject, don't repeat the class name, read the call site
aloud). When a method needs real computation, extract it into a pure
function via the `write-function` skill and call it. The class the method
lives on is governed by the `write-class` skill.

## Rules

- **Named first** — use the `naming` skill. Queries named for the value
  returned, commands named for the effect.
- **Single responsibility** — one job, same as a function. Also: the job must
  belong to *this* class — a method that mostly operates on another object's
  data lives on the wrong class (see `write-class` — boundaries).
- **Small** — fits on screen, understood at a glance. A large method is doing
  too much; extract pieces into private methods or pure functions
  (`write-function`).
- **Inputs are clear**
  - The receiver (`this`) is an implicit input — the method may read its own
    object's state, and that's not a hidden dependency. Everything *else* is
    passed as a parameter, not pulled from outside (no globals, no
    `getCurrentTime()` — inject via constructor or pass as an argument).
  - Arguments are clear, ordered, sensible, and few — the receiver already
    carries context, so methods should need even fewer parameters than
    standalone functions.
  - Protect against bad values at runtime — both bad arguments and calls made
    when the object is in an invalid state for the operation.
- **Outputs are clear**
  - Separate commands from queries: a query returns a value and changes
    nothing; a command changes the object's state. Avoid methods that do
    both.
  - If the return value is optional, it's clear what a missing value means.
- **Errors are clear and expected**
  - Clearly manages its error conditions, invalid inputs, and invalid-state
    calls. If it throws, it's clear why and what each error means.
  - Error paths are sensible, predictable, and anticipated — don't surprise
    the caller.
- **Deterministic — relative to receiver state.** Same object state + same
  arguments → same result. Nondeterminism from anywhere but explicit inputs
  and the object's own state is still forbidden.
- **Always terminates.**
- **Side effects — permitted on the receiver, forbidden elsewhere**
  - Methods may change the state of their own object; that's what commands
    are for. The mutation must be visible in the name (effect verb — see
    `naming`).
  - It doesn't change state outside its object — no mutating parameters,
    globals, or unrelated parts of the system.
  - External side effects (I/O, logging, cookies) still belong near the top
    of the call stack — coordinated from above, performed by adapters passed
    in — not buried deep in methods where the coordinators are unaware of
    them (see `architecture` — layers).
- **Isolated & composable** — no hidden dependencies beyond the receiver;
  collaborators arrive via the constructor, not reached for.
- **Testable** — a method is tested through its object: construct the object
  in a known state, call the method, assert on the result or the new state.
  Plan for that; if setup is painful, the class holds too much.
- **Communicates to peers**
  - Not overly clever; obvious how it works. Does not hurt to look at, easy
    to use.
  - The call site reads as a sentence — the receiver supplies the context
    the name omits.
  - Minimize required context — the knowledge of the class's internals a
    caller needs to use it correctly.
  - Code is for *what and how*; comments are for *why and when*.
