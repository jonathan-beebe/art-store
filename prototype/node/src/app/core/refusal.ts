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
