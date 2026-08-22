---
name: work-retro
description:
  Sweep recently-resolved tickets and journal entries since the last retro,
  surface patterns and lessons across a fixed set of categories, walk through
  them with the human one at a time, and prepend a dated entry to
  `work/retro.md`. The truth source for "when was the last retro" is the `Last
  Retro:` line at the top of `work/retro.md` itself; if the file does not exist,
  the window opens at the beginning of time.
argument-hint: '[YYYY-MM-DD:HH:MM:SS]'
---

Run a retrospective:

$ARGUMENTS

## Expected arguments

Zero or one argument:

- _(empty)_ — read the window start from `work/retro.md`'s `Last Retro:` line.
  If the file doesn't exist, treat the start as "never" (include everything).
- `YYYY-MM-DD:HH:MM:SS` — override the window start. Use when re-running a
  retro, or when you want to retro a specific historical slice.

The window end is always "now" (the current local time when the skill runs).

## Inputs

Read the following for the initial context.

- `work/retro.md` — top of file, the `Last Retro: <YYYY-MM-DD:HH:MM:SS>` line.
  This is the authoritative source for the window start.
- `work/journal.md` — every bullet under `## Log` whose timestamp falls within
  the window.
- `work/3-done/*.md` — every ticket whose `resolved:` frontmatter date falls
  within the window (or `created:` if `resolved:` is absent). Pay particular
  attention to the `## Working` section of each ticket — that's where working
  notes live, they're the richest signal for retro analysis.

## Categories to surface

Sweep the work items completed within the work window. Mine the `## Working` sections looking
for hard observations about what happened. Don't invent findings to fill a category — 
say "nothing notable" if there isn't a signal.

### Questions to ask

- What went well?
- What didn't go well? 
- What surprised us?
- What patterns did we see?
- What should we do more of?
- What should we do less of?
- Where did the system fail us, and how can we harden the system to prevent similar failures in the future?
- Where did the system serve us well, and how can we lean into that?
- What tools can we build or harden to facilitate our work?
- What improvements did we make that can be turned into a tool or script to encode the lesson for future reuse?
- Where did we struggle? Bugs that took multiple passes, reverts, pattern sprawl, accumulated tech debt, places the workflow itself slowed us down?
- Where did we spend most of our time? This reveals what is truly valuable to us, and if our focus truly aligns with our intent?
- Where can we tighten our workflow feedback loops?
- Where can we improve our monitoring of system performance and bottlenecks?

## Workflow

1. **Determine the window.**
   - If `$ARGUMENTS` is non-empty, parse it as `YYYY-MM-DD:HH:MM:SS` and use
     that as the start. Reject malformed input.
   - Otherwise, read the first ~10 lines of `work/retro.md`. Look for a
     `Last Retro: <YYYY-MM-DD:HH:MM:SS>` line and use that timestamp.
   - If `work/retro.md` doesn't exist, the start is "never" — include
     everything.
   - The end is the current local time (`YYYY-MM-DD:HH:MM:SS`).
   - Surface the window to the human in one line before continuing:
     `Retro window: <start> → <end> (N journal entries, M done tickets)`.

2. **Gather inputs.**
   - Filter `work/journal.md` `## Log` bullets by timestamp into the window.
   - Filter `work/3-done/*.md` by `resolved:` (fall back to `created:`) into the
     window. Read each in full — both the structured sections and the
     `## Working` notes.
   - If the window contains nothing in either source, stop and tell the human;
     don't write an empty retro.

3. **Sweep & categorize.** Build observations for each of the questions
   above. We are looking for hard observations about what happened and concrete actions
   we can take to improve our workflow and systems. Goals include:
   
   - Eliminating wasted effort
   - Reducing code churn
   - Increasing the focus and impact of our work
   - Improve the guarantee of quality at each step of the workflow
   
   Observations must be grounded but written at the **thematic / outcome
   altitude** — not the code-snippet altitude. See "Altitude" below.

4. **Collaborate with your human operator.** For each of the quesetions above:
   - Present the observations you found, with citations.
   - Collaborate with your operator to dig deep and mine for the hard lessons learned
     and how to encode those into systems and workflow improvements. You are looking
     for:
     - Concrete actions, such as building a tool or hardening a skill.
     - Creating a hypothesis and constructing a workflow experiment 
       that can be evaluated during the next retro.
   - For each opportunity, work towards a concrete lesson learned and actionable result
     before moving on. 
     - If a concrete action is found, document any decisions, hypothesis, and desired outcomes and goals in the action-items section of the retro doc.
     - If there is no concrete action, but there is a lesson learned, document it as an observation in the retro entry so we can keep watching for it in future retros.
     - Otherwise it is ok to skip it.

5. **Draft the entry.** Assemble the final retro entry from the agreed-upon
   observations and decisions. Present it once for final approval before
   writing.

6. **Write the entry.** Prepend the new dated entry to `work/retro.md` (newest
   first). If the file doesn't exist, create it with the header block from
   "Output format" below. Update the `Last Retro:` line at the top of
   `work/retro.md` to the current timestamp.

7. **Log the retro in `work/journal.md`.** Append a single bullet to the top of
   `## Log` recording that the retro ran, with a one-line summary of its shape —
   e.g.
   `- <YYYY-MM-DD:HH:MM:SS> — retro — covered <N> tickets / <M> journal entries; <one-line headline of the entry>`.
   The retro itself is a workflow event worth a log entry, even though it isn't
   tied to a single ticket.

## Output format

`work/retro.md` structure (this skill owns it):

```
# Retros

Last Retro: <YYYY-MM-DD:HH:MM:SS>

Newest entries first. Each entry is one retro session.

---

## <YYYY-MM-DD>

**Window:** <start> → <end>
**Scope:** <N journal entries, M done tickets>

### Themes
(A brief list of themes that emerged from the retro conversation. 
  Only note concrete observations, skip any that were deemed irrelevant 
  or not actionable.)

### Observations
(a brief list of observations that came from the retro questions above)

### Action items
1. [ ] <very brief description>
(numbered checklist; one line each; absorb any "follow-ups filed" tickets here too)

---

## <previous YYYY-MM-DD>
…
```

## Altitude

Retro observations live at the system, workflow, and project level — themes, focus, quality, 
scope, outcomes, and effectiveness. This is not about the code, but about the workflows that
generated the code and predictable quality.

**What good looks like:**

- Noting workflow gaps that resulted in bugs.
- Noting opportunities to refine our workflow to eliminate waste, reduce churn, 
  reduce or eliminate categories of bugs.
- Noting areas where previous workflow changes and experiments are working and paying off.
- Noting areas where previous workflow changes and experiments are not working and need to be adjusted or abandoned.

**What bad looks like:**

(reserved for future notes)

**How to apply:**

- Lead with shape: counts, ratios, the slice of time it covered.
- Describe what was delivered as user / product / workflow impact.
- Assess effectiveness of workflows and skills.
- Mine for opportunities to improve the workflow and increase its effectiveness at predictable quality output.

## What this skill does NOT do

- Does not edit code, run tests, or open tickets. If the retro surfaces work
  that should become a ticket, surface it as a recommendation in the action-items section of the entry and
  let the human run `/work-scope` later.
- Does not summarize tickets. The retro is about the
  meta-signal at the system and workflow level: patterns across tickets.
- Does not run autonomously. The walk-through with the human is the point — skip
  it and you've just generated a wordy log.
