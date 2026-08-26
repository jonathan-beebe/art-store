import { BrokenContractError } from './defect.ts'

/**
 * The shape an action returns when the domain says no — see
 * `actions/auth/sign-in-with-magic-link.ts`. `reason` is the sub-category
 * `data.reason` names on the log line and the UX branches on; `data` is the
 * facts behind it (prefixed ids). A refusal is `refused` at `info`, never
 * `failed`.
 */
export type Refusal<Reason extends string = string> = {
  outcome: 'refused'
  reason: Reason
  data?: Record<string, unknown>
}

export function refused<Reason extends string>(
  reason: Reason,
  data?: Record<string, unknown>,
): Refusal<Reason> {
  return { outcome: 'refused', reason, ...(data === undefined ? {} : { data }) }
}

/**
 * Reads the two statuses a transition refusal carries, for a copy mapper to
 * word without a pre-action row read of its own. A refusal reaching here
 * without both is a broken contract, same vocabulary as the unwrappers.
 */
export function transitionFacts(refusal: Refusal): { status_from: string; status_to: string } {
  const { data } = refusal
  const statusFrom = data?.status_from
  const statusTo = data?.status_to
  if (typeof statusFrom !== 'string' || typeof statusTo !== 'string') {
    throw new BrokenContractError(
      'missing_transition_statuses',
      'A transition refusal is missing status_from or status_to.',
      data,
    )
  }

  return { status_from: statusFrom, status_to: statusTo }
}
