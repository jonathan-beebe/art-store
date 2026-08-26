import { BrokenContractError } from './defect.ts'

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

/** The reason and data of a returned refusal, or `null` for any other result. */
export function refusalOf(result: unknown): { reason: string; data?: RefusalData } | null {
  if (typeof result !== 'object' || result === null) return null

  const reason = refusedReasonOf(result)
  if (reason === null) return null

  const data = 'data' in result ? result.data : undefined
  return isRefusalData(data) ? { reason, data } : { reason }
}

/** The `reason` an object carries when it is a returned refusal, or `null` otherwise. */
function refusedReasonOf(result: object): string | null {
  if (!('outcome' in result) || result.outcome !== 'refused') return null

  const reason = 'reason' in result ? result.reason : undefined
  return typeof reason === 'string' ? reason : null
}

function isRefusalData(value: unknown): value is RefusalData {
  return typeof value === 'object' && value !== null
}

/** True when `result` is not a returned refusal — the shape `mustSucceed` returns unchanged. */
function told<Result>(result: Result): result is Exclude<Result, Refusal> {
  return refusalOf(result) === null
}

/**
 * Unwraps a result for a caller inside the application that only ever asks
 * for a legal move — a refusal reaching it is a broken contract, not a domain
 * outcome to handle. Returns `result` unchanged on success; a returned
 * refusal throws `BrokenContractError` carrying the refusal's reason and
 * data, with `message` as the error's message, or a default sentence naming
 * the reason.
 */
export function mustSucceed<Result>(result: Result, message?: string): Exclude<Result, Refusal> {
  if (told(result)) return result

  const refusal = refusalOf(result)
  if (refusal === null) {
    // `told` and `refusalOf` read the same shape independently; TypeScript
    // cannot carry the narrowing from one call to the other, so this branch
    // satisfies strict mode without ever running.
    throw new BrokenContractError('broken_contract', 'mustSucceed received an inconsistent result')
  }

  throw new BrokenContractError(refusal.reason, message ?? `the action was refused: ${refusal.reason}`, refusal.data)
}
