/**
 * One outbox message as the RFC-5322 text an `.eml` file holds. `messageId`
 * and `date` arrive as inputs because a message identifier and a send time are
 * facts about the world, and this file decides nothing about the world.
 */
export type MailMessage = {
  to: string
  subject: string
  body: string
  url: string | null
  /** The `Message-ID` without its angle brackets. */
  messageId: string
  date: Date
}

/** Every message this prototype writes comes from the platform itself. */
export const MAIL_FROM = 'Art Store <no-reply@art-store.example>'

// RFC 5322 §2.1: every line of a message ends CRLF, header lines included.
const CRLF = '\r\n'

export function renderMailMessage(message: MailMessage): string {
  const headers = [
    `From: ${MAIL_FROM}`,
    `To: ${headerValue(message.to)}`,
    `Subject: ${headerValue(message.subject)}`,
    `Date: ${rfc5322Date(message.date)}`,
    `Message-ID: <${headerValue(message.messageId)}>`,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset="utf-8"',
    'Content-Transfer-Encoding: 8bit',
  ]

  return [...headers, '', ...bodyLines(message), ''].join(CRLF)
}

/**
 * `Date` in the form RFC 5322 §3.3 asks for. `toUTCString` already writes
 * `Mon, 24 Aug 2026 12:00:00 GMT`, which is that form with the obsolete zone
 * name in place of the numeric offset.
 */
export function rfc5322Date(date: Date): string {
  return date.toUTCString().replace(/ GMT$/, ' +0000')
}

/** The body, then a blank line and the link when the message carries one. */
function bodyLines(message: MailMessage): readonly string[] {
  const body = message.body.split(/\r?\n/)

  return message.url === null ? body : [...body, '', message.url]
}

/**
 * A header value on one line. A CR or LF reaching a header would end it and
 * start whatever followed as a header of its own.
 */
function headerValue(value: string): string {
  return value.replace(/[\r\n]+/g, ' ')
}
