import { test } from 'node:test'
import assert from 'node:assert/strict'
import { MAIL_FROM, rfc5322Date, renderMailMessage, type MailMessage } from './mail-message.ts'

const CRLF = '\r\n'

const SOLD: MailMessage = {
  to: 'artist@example.com',
  subject: 'Item sold',
  body: 'Order #7 is paid. $405.00 is held until the customer confirms delivery.',
  url: 'http://localhost:4000/seller/orders/7',
  messageId: 'outbox-1@art-store.example',
  date: new Date('2026-08-24T12:00:00.000Z'),
}

test('it renders the whole message, headers then a blank line then the body', () => {
  assert.equal(
    renderMailMessage(SOLD),
    [
      'From: Art Store <no-reply@art-store.example>',
      'To: artist@example.com',
      'Subject: Item sold',
      'Date: Mon, 24 Aug 2026 12:00:00 +0000',
      'Message-ID: <outbox-1@art-store.example>',
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset="utf-8"',
      'Content-Transfer-Encoding: 8bit',
      '',
      'Order #7 is paid. $405.00 is held until the customer confirms delivery.',
      '',
      'http://localhost:4000/seller/orders/7',
      '',
    ].join(CRLF),
  )
})

test('every line ends CRLF and no bare newline survives', () => {
  const rendered = renderMailMessage({ ...SOLD, body: 'First line.\nSecond line.' })

  assert.equal(rendered.includes('First line.\r\nSecond line.'), true)
  assert.equal(/[^\r]\n/.test(rendered), false)
})

test('a message with no url ends after the body', () => {
  const rendered = renderMailMessage({ ...SOLD, url: null })

  assert.equal(
    rendered.endsWith(
      `${CRLF}${CRLF}Order #7 is paid. $405.00 is held until the customer confirms delivery.${CRLF}`,
    ),
    true,
  )
})

test('the sign-in url is the last line, so the reader can click it', () => {
  const lines = renderMailMessage(SOLD).split(CRLF)

  assert.equal(lines.at(-2), 'http://localhost:4000/seller/orders/7')
})

test('a newline smuggled into a header value cannot open a header of its own', () => {
  const rendered = renderMailMessage({
    ...SOLD,
    subject: 'Item sold\r\nBcc: thief@example.com',
    to: 'artist@example.com\nX-Evil: yes',
  })

  assert.equal(rendered.includes('Subject: Item sold Bcc: thief@example.com'), true)
  assert.equal(rendered.includes('To: artist@example.com X-Evil: yes'), true)
  assert.equal(rendered.split(CRLF).filter((line) => line.startsWith('Bcc:')).length, 0)
})

test('the From header names the platform', () => {
  assert.equal(MAIL_FROM, 'Art Store <no-reply@art-store.example>')
})

test('rfc5322Date writes the instant in UTC with a numeric zone', () => {
  assert.equal(rfc5322Date(new Date('2026-01-02T03:04:05.000Z')), 'Fri, 02 Jan 2026 03:04:05 +0000')
})

test('rfc5322Date carries no fractional seconds', () => {
  assert.equal(rfc5322Date(new Date('2026-12-31T23:59:59.999Z')), 'Thu, 31 Dec 2026 23:59:59 +0000')
})
