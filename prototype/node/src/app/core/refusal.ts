/** The facts behind a refusal: prefixed ids and the values the rule read. */
export type RefusalData = Record<string, unknown>

/**
 * The shape an action returns when the domain says no — see
 * `actions/auth/sign-in-with-magic-link.ts`. `reason` is the sub-category
 * `data.reason` names on the log line and the UX branches on; `data` is the
 * facts behind it, typed per reason so a consumer wording the refusal reads
 * them straight off the type. A reason that promises facts makes `data`
 * required. A refusal is `refused` at `info`, never `failed`.
 */
export type Refusal<
  Reason extends string = string,
  Data extends RefusalData | undefined = RefusalData | undefined,
> = undefined extends Data
  ? { outcome: 'refused'; reason: Reason; data?: Data }
  : { outcome: 'refused'; reason: Reason; data: Data }

export function refused<Reason extends string>(reason: Reason): Refusal<Reason, undefined>
export function refused<Reason extends string, Data extends RefusalData>(
  reason: Reason,
  data: Data,
): Refusal<Reason, Data>
export function refused(reason: string, data?: RefusalData): Refusal<string> {
  return { outcome: 'refused', reason, ...(data === undefined ? {} : { data }) }
}

/** The facts every refused transition carries: the status the row held and
 * the one asked for. */
export type TransitionFacts<Status extends string> = {
  status_from: Status
  status_to: Status
}

/** The refusal a lifecycle table hands back for a move its rows forbid. */
export type IllegalTransition<Status extends string> = Refusal<'illegal_transition', TransitionFacts<Status>>
