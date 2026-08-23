/**
 * A port the prototype declares and deliberately leaves unwired. Reaching one
 * is a configuration mistake, not a runtime condition to recover from.
 */
export class NotImplementedError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'NotImplementedError'
  }
}
