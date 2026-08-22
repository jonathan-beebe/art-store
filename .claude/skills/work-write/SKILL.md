---
name: work-write
description:
  Write a ticket file from an already-scoped problem. Takes a type and a scope
  packet (produced by `/work-scope`), allocates an id, and writes the ticket to
  `work/1-inbox/`. This skill is the formatter — it does no discovery and no
  dialogue. If your scope is rough, run `/work-scope` first.
argument-hint: <type> <scope packet>
---

Write a ticket from a scope packet:

$ARGUMENTS

## Expected arguments

`<type> <scope packet>`

- **type** — one of: research, design, architecture, feature, improvement,
  maintenance, a11y, refactor, bug.
- **scope packet** — the labeled markdown blob produced by `/work-scope` (see
  "Scope packet shape" below).

If `$ARGUMENTS` is empty, or the type is unrecognized, or the packet is missing
required fields, **stop and redirect to `/work-scope`**. This skill does not
guess, dialogue, or fill in blanks — the scoping work belongs in `/work-scope`
so the human is in the loop while it happens.

## Type registry

| type         | prefix |
| ------------ | ------ |
| research     | RSRCH  |
| design       | DSGN   |
| architecture | ARCH   |
| feature      | FEAT   |
| improvement  | IMPRV  |
| maintenance  | MAINT  |
| a11y         | A11Y   |
| refactor     | RFCTR  |
| bug          | BUG    |

## Scope packet shape

The packet `/work-scope` produces (and that this skill accepts as input):

```
PROBLEM: <factual statement — what is broken / missing / unclear, and where>
GOAL: <one-line statement of what the finish line looks like — what this work is aiming at>
OUTCOME: <observable, verifiable end state — the user or system reaches X>
WHY IT MATTERS: <user impact, constraint violated, downstream effect>
RELATED WORK: <list of TICKET-### and/or commit SHAs, or "none">
DISCOVERY NOTES (optional): <advisory notes from the reporter — diagnostics, constraints, and suggestions that may help address the problem>
ONE-LINE SUMMARY: <short phrase — used for filename slug and journal entry>
```

`PROBLEM`, `GOAL`, `OUTCOME`, `WHY IT MATTERS`, `RELATED WORK`, and
`ONE-LINE SUMMARY` are required. `DISCOVERY NOTES` is optional for every type.

## Hard solutions are rejected

Suggestions are welcome on every type; solutions are not. The gate is content:

- A **suggestion** is advisory input the maker can evaluate and discard — a
  direction, an option, a starting point, an ideal end state, a sketch or
  pseudo-code fragment illustrating an idea. It lives in `DISCOVERY NOTES`.
- A **hard solution** is a worked-out implementation the maker would merely
  transcribe — full diffs or drop-in code, file-by-file edit lists, an
  exhaustive step sequence covering the whole change.

A packet containing a hard solution — in any field — is rejected: the scoper
and writer study the problem and assemble context and suggestions; the maker
does the solving. Per-type guidance on what good suggestions look like lives in
`/work-scope`'s "Suggestion guidance by type" section.

## Validation contract — what this skill enforces on the packet

A packet that violates any of the rules below is rejected with a one-line reason
and a pointer back to `/work-scope`:

- `PROBLEM` is factual and grounded (file paths / line numbers when applicable).
  Not vague ("fix the menu thing").
- `GOAL` is a single, clear, one-line statement of what the finish line looks
  like — the why behind the work, not a restatement of `OUTCOME` and not a
  mechanism.
- `OUTCOME` is phrased as an observable state ("the user can dismiss the dialog
  with Escape", "the table has an accessible name"), not as a code change ("add
  an `aria-label`", "wrap in `<dialog>`"). Mechanism — even when obvious —
  belongs in `DISCOVERY NOTES` (as advisory) or in `/work-start`, not in
  `OUTCOME`.
- No field contains a hard solution — a worked-out implementation the maker
  would merely transcribe (full diffs, drop-in code, file-by-file edit lists,
  exhaustive step sequences). Reject (see Hard solutions are rejected).
- The only place implementation detail (suggested fix, code sketches, library
  or API choices) may appear is `DISCOVERY NOTES`, and it reads as advisory —
  suggestions the maker may follow or discard, not directives. It may not bleed
  into `PROBLEM`, `GOAL`, `OUTCOME`, or `WHY IT MATTERS`.

These rules exist because solutioning at definition time freezes assumptions
that may be stale by the time work begins, and crowds out the problem statement
so the implementer skims past it. `DISCOVERY NOTES` is the controlled
exception: it preserves diagnostic and directional signal while leaving the
solving to the maker.

## Workflow

1. **Parse args** into `<type>` and `<packet>`. Reject unknown types.
2. **Validate the packet** against the contract above. On any failure, stop and
   tell the human exactly which rule failed and to re-run `/work-scope`.
3. **Allocate id.** Read `work/journal.md` → `Next ticket numbers > <PREFIX>:`
   for the next number. Allocated id is `<PREFIX>-<NNN>`.
4. **Write the ticket** to `work/1-inbox/<PREFIX>-<NNN>-<slug>.md`, where
   `<slug>` is derived from `ONE-LINE SUMMARY` (lowercase, kebab-case,
   alphanumerics + hyphens, ≤ 60 chars).

   Frontmatter at minimum:

   ```
   ---
   id: <PREFIX>-<NNN>
   type: <type>
   status: open
   created: <YYYY-MM-DD>
   ---
   ```

   Body in this exact section order:

   ```
   # <PREFIX>-<NNN>: <ONE-LINE SUMMARY>

   ## Problem
   <PROBLEM>

   ## Goal
   <GOAL>

   ## Outcome
   <OUTCOME>

   ## Why it matters
   <WHY IT MATTERS>

   ## Discovery notes
   <DISCOVERY NOTES — advisory; /work-start may use or discard>
   (omit this section entirely if the packet had no DISCOVERY NOTES)

   ## Related work
   <RELATED WORK — bullet list of links>
   (omit this section entirely if RELATED WORK is "none")
   ```

   Do not add sections beyond these. Do not synthesize content the packet didn't
   provide.

5. **Log it.** Invoke `/work-log` with
   `<PREFIX>-<NNN> — defined: <ONE-LINE SUMMARY>`. The `/work-log` skill bumps
   the per-type counter.
