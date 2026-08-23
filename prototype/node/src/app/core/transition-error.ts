/**
 * Raised when a status is asked to move somewhere its transition table does not
 * allow. Actions let it out; routes catch it and render the refusal.
 *
 * This is the line between the two kinds of failure in the domain:
 * `TransitionError` is for a user-triggerable, expected refusal — a stale form,
 * a status change that is no longer possible — and reaches the person who asked
 * for it as a message on the page. `RangeError` and `TypeError` are for
 * programmer error only, and reaching one is a bug rather than an answer.
 */
export class TransitionError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'TransitionError'
  }
}
