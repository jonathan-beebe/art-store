---
name: work-start
description:
  Work a ticket of any type. With no argument (or `all`), drains every ticket in
  `work/1-inbox/` in filename order. With a `TICKET-###` argument, works that
  single ticket. With a type name (e.g. `bug`, `feat`, `a11y`), drains only
  tickets of that type. The canonical workflow lives here and the type-specific
  working steps are loaded from `types/<type>.md`.
argument-hint: '[TICKET-### | <type> | all]'
---

Start a work session:

$ARGUMENTS

## Expected arguments

Zero or one argument:

- _(empty)_ or `all` — drain `work/1-inbox/` in alphabetical order, one ticket
  at a time, until empty.
- `TICKET-###` — work that single ticket (look it up in `1-inbox/` first, then
  `2-doing/`).
- `<type>` — drain only tickets of the given type from `work/1-inbox/` in
  alphabetical order. Accepts either the type name (`bug`, `feature`, `a11y`, …)
  or the prefix (`BUG`, `FEAT`, `A11Y`, …), case-insensitive. See the type
  registry below for the full list.

## Type registry

| prefix | type         | how-to file             |
| ------ | ------------ | ----------------------- |
| RSRCH  | research     | `types/research.md`     |
| DSGN   | design       | `types/design.md`       |
| ARCH   | architecture | `types/architecture.md` |
| FEAT   | feature      | `types/feature.md`      |
| IMPRV  | improvement  | `types/improvement.md`  |
| MAINT  | maintenance  | `types/maintenance.md`  |
| A11Y   | a11y         | `types/a11y.md`         |
| RFCTR  | refactor     | `types/refactor.md`     |
| BUG    | bug          | `types/bug.md`          |

## Workflow (canonical for all types)

- You are only orchestrating these changes, you are not writing code. 
- Use separate agents running the `Sonnet` model for writing tests, code, and comments. 
  Be as efficient as you can in offloading work to haiku and sonnet models. You are concerned 
  with managing the work to the goal/outcome, and are responsible for the quality of the 
  committed work. Iterate with the agents as needed to ensure quality.
- Instruct these agents:
  - Use `/write-comment` for any code comments.
  - Use `/write-class` for any classes.
  - Use `/write-class-method` for any class methods.
  - Use `/write-function` for any standalone functions.
  - Use `/write-function` for any ruby functions.
  - Use the various tdd skills when testing and follow a TDD flow, writing tests first, then making them pass.
  - Have a final validation agent check the work and recommend any refactors or improvements necessary to hit the goal.

1. **Resolve target ticket(s).** Pick based on the argument:
   - `TICKET-###` — that single ticket.
   - _(empty)_ or `all` — every file in `work/1-inbox/`, alphabetical order.
   - `<type>` — only files in `work/1-inbox/` whose id starts with the matching
     prefix from the type registry, alphabetical order. Resolve the argument to
     a prefix case-insensitively against both the `type` and `prefix` columns.
     If it matches neither, stop and surface the unknown type rather than
     falling back to draining everything.
2. For each ticket, in order:
   1. **Identify the type** by extracting the prefix from the id.
   2. **Read** `types/<type>.md` (this directory). That file owns the
      type-specific test approach, definition of done, and any extra steps.
   3. **Locate** the ticket file in `work/1-inbox/` then `work/2-doing/`. If it
      lives only in `3-done/`, surface that and ask whether to reopen.
   4. **Promote to doing.** Move the file to `work/2-doing/` if not already
      there. Invoke `work-log`: `<PREFIX>-<NNN> — started`.
   5. **Understand the goal.** Re-read the ticket and the affected code.
   6. **Re-validate** that the issue / need still applies. Capture notes in a
      `## Working` section at the bottom of the ticket as you go.
   7. **Tests first** — write the test(s) the matching `types/<type>.md` calls
      for (failing/demonstrative, characterization, etc.).
   8. **Make the change** — pursue the simplest solution. Favor existing
      patterns over new ones.
   9. **Run all tests** — green before committing.
   10. **Commit** with the ticket id in the message.
   11. **Mark accepted.** Update the ticket status to resolved, move the file to
       `work/3-done/`, and invoke the skill `/work-log`:
       `<PREFIX>-<NNN> — done: <one-line summary>`.
3. If a worker step fails or asks for human input, stop the drain loop and
   surface the question — do not silently skip ahead.

## A note on comments

When documents need to ground their notes in a code reference, they use the file name, 
module/class/function name, line numbers, date, and/or commit hash, as necessary. 
The work tickets reference the code.

- The code never references the tickets.
- Code comments never reference the past. Comments describe the current code.
- The history is carried in the commit history, and in the working documents. Code and 
  code comments do not need to be concerned with *what was*, only with what is.


