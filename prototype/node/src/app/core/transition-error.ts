/**
 * Raised when a status is asked to move somewhere its transition table does not
 * allow. Actions let it out; routes catch it and render the refusal.
 *
 * This is the line between the two kinds of failure in the domain:
 * `TransitionError` is for a user-triggerable, expected refusal — a stale form,
 * a status change that is no longer possible — and reaches the person who asked
 * for it as a message on the page. `RangeError` and `TypeError` are for
 * programmer error only, and reaching one is a bug rather than an answer.
 *
 * `reason` names the refusal within the event's category and `data` carries
 * the facts behind it; both land on the `refused` log line.
 */
export class TransitionError extends Error {
  readonly reason?: string
  readonly data?: Record<string, unknown>

  constructor(message: string, facts: { reason?: string; data?: Record<string, unknown> } = {}) {
    super(message)
    this.name = 'TransitionError'
    if (facts.reason !== undefined) this.reason = facts.reason
    if (facts.data !== undefined) this.data = facts.data
  }
}
