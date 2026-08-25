---
name: write-comment
description: How to decide whether code needs a comment, whether better code would remove the need, and what a value-adding comment says. Use whenever you write a comment, or review one.
---

# Writing a comment

Code is for *what* and *how*. Comments are for *why* and *when*. A comment that
explains what the code does has no job — the code already did that, and the
comment now has to be maintained alongside it, silently rotting when it isn't.

Most of the pull to comment is a signal about the code, not a need for prose.
Work the decision in order: **default to none → try to fix the code → only then
write one.**

Related skills: `naming` (most comments that explain a name should be a rename),
`write-function` (most comments that explain a block should be an extraction).

## 1. Default: no comment

Write code that reads without one. This is the outcome to aim for, and the bar
every comment below has to clear to earn its place.

## 2. Feel the pull? Try to remove the need

Before writing the comment, ask what it is compensating for. Most of the time
there is a code change that makes it unnecessary — make that change instead:

| The comment would explain…                | Do this instead                  |
| ----------------------------------------- | -------------------------------- |
| What a name means                         | Rename it (`naming` skill)       |
| What a block of code does                 | Extract it into a named function |
| What a bare literal is                    | Name the constant                |
| What a tangled condition tests            | Name the predicate               |
| What each section of a long function does | Split the function               |

Only when you've done this and the reader would *still* be missing something
does a comment earn its place.

## 3. Some things code genuinely cannot say

Two families. Both carry information a reader cannot recover by reading harder.

**Decision records — why the code is shaped this way.**

- Why this shape and not the obvious alternative — `h-full`, not `h-svh`;
  `useLayoutEffect`, not `useEffect`
- Why a constant holds this value — measurements, thresholds, what breaks on
  either side
- An external constraint the reader can't see from here — platform behavior,
  spec requirement, library ordering guarantee
- A boundary or ownership rule — who owns this state, and why not here
- A lesson learned the hard way — the trap that was fallen into, stated as the
  rule that now prevents it

**Decodes — what a literal doesn't say on its face.**

- A magnitude an expression hides — `const UPDATE_CHECK_INTERVAL_MS = 60 * 60 * 1000 // 1 hour`
- Units where the name doesn't already carry them
- If the same decode is needed in more than one place, name a constant instead

The threshold for a decode is *the literal doesn't read as the quantity it is*.
`3600000` needs one. `60 * 60 * 1000` saves the reader a beat, so it earns one.
`1200` and `2000` read as themselves — nothing needed.

**Three conventional allowances**, each narrow:

- **Interface prop purpose** — one brief line, only when the property name
  can't carry it alone. If it needs a paragraph, the prop or its name is wrong.
- **Section banners** — organizational structure in a long file. A label, not
  an explanation.
- **Test step explanations** — the assertion says what is checked; the comment
  says what behavior is being protected, and keeps a long arrange/act sequence
  readable.

## 4. Never write these

- **Restatements of the line below.** The largest category of dead comment by
  far, and the easiest to spot: delete it and nothing is lost.
- **Change descriptions that read like a PR comment.** A comment describes the
  code as it is. Git holds what it was. "This used to be X, now it's Y" is
  review commentary that got committed.
- **Ticket numbers.** `(BUG-017)` tells the reader a ticket exists — it doesn't
  tell them anything they can act on, and it sends them out of the file to find
  out. If the ticket taught a lesson, extract the lesson and state it. That is
  the durable part; the number is the disposable part.
- **Commit SHAs and `file.ts:NN` line references.** They go stale silently and
  the reader can't tell. Refer to a symbol by name, or say the thing directly.
- **Explanations of a name.** Rename it.
- **Commented-out code.** Delete it.

## 5. Write it well

- **Present tense, describing the code as it stands.** Not how it got here.
- **Self-contained.** A reader with only this file should get the full point.
- **Brief.** A decision usually needs one or two sentences. When a comment runs
  long, most of the length is usually narrative that section 4 would delete.
- **Placed on the decision**, not floating above the whole function.
- **State the rule, not the war story.** "Re-measured every render, never held
  in state" beats three sentences about the bug that proved it.

## The test

Delete the comment and reread the code. Put it back only if a competent reader
of this codebase would now make a wrong change, or would redo an experiment
someone already ran.

If the answer is instead "they'd be mildly confused for a moment" — fix the
code. If it's "nothing would be lost" — leave it deleted.

## Other

- Design docs own the design comments. The code files focus only on the implementation of that design.
- Comments that map vocabulary can be valuable. For example, mapping a vendor's name to our own internal name.
- Reference a symbol, don't describe it.
    ```
    - # +SomeVariableName+ is our id (the `customerId` path segment)
    + # +SomeVariableName+ maps to our Customer#loyalty_reference
    ```
- Do not comment on past/deleted code, past/reversed decisions, mistakes, etc. Comments represent the code as it is today. They do not care about what was.
- Assume framework fluency. Comments are not for educating the reader on the language, frameworks, tools, etc. They can look up those details if needed.
- Comment on root constraints, not observed symptoms. So, for example "Doesn't roll back" is what was observed, but "We can't delete a customer" is the root why.
- Useful in comments
  - runnable commands, like the command to run a specific test file manually.
  - links directly to a vendor function in their docs. (not a link to a parent page, but a link directly to the docs for a given api call, for example.)