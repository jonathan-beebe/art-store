export const MESSAGE_BODY_MAX_LENGTH = 2_000

/** What is wrong with a message body, or null when it may be sent. */
export function messageBodyError(body: string | undefined): string | null {
  const trimmed = (body ?? '').trim()
  if (trimmed.length === 0) {
    return 'Write a message before sending.'
  }
  if (trimmed.length > MESSAGE_BODY_MAX_LENGTH) {
    return `A message is at most ${MESSAGE_BODY_MAX_LENGTH} characters.`
  }
  return null
}

/** The body as it is stored: trimmed, never with the surrounding whitespace. */
export function parseMessageBody(body: string | undefined): string {
  return (body ?? '').trim()
}
