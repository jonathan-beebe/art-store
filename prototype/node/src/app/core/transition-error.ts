/**
 * Raised when a status is asked to move somewhere its transition table does not
 * allow. Actions let it out; routes catch it and render the refusal.
 */
export class TransitionError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'TransitionError'
  }
}
