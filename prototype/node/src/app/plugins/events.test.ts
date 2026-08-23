import { test, type TestContext } from 'node:test'
import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import { openConversation } from '../actions/messaging/open-conversation.ts'
import {
  buildTestApp,
  signInAsAdmin,
  signInAsCustomer,
  signInAsSeller,
  type SignedInActor,
  type TestApp,
} from '../test/build-test-app.ts'
import { unreadEventStream, type AppEvents, type UnreadStreamSource } from './events.ts'

/** A stream fed by hand: one count per read, the last one repeating. */
function fakeSource(counts: readonly number[]): {
  source: UnreadStreamSource
  events: EventEmitter<AppEvents>
  disconnect: () => void
} {
  const events = new EventEmitter<AppEvents>()
  const disconnected = new AbortController()
  let reads = 0

  return {
    events,
    disconnect: () => disconnected.abort(),
    source: {
      countUnread: async () => {
        const count = counts[Math.min(reads, counts.length - 1)] ?? 0
        reads += 1
        return count
      },
      events,
      disconnected: disconnected.signal,
    },
  }
}

const decoder = new TextDecoder()

/** The next `count` frames the stream sends, one per enqueued chunk. */
async function readFrames(
  reader: ReadableStreamDefaultReader<Uint8Array>,
  count: number,
): Promise<string[]> {
  const frames: string[] = []

  while (frames.length < count) {
    const chunk = await reader.read()
    if (chunk.done) break
    frames.push(decoder.decode(chunk.value))
  }

  return frames
}

/** Reads to the end, so a test can assert the stream closed rather than hung. */
async function readToEnd(reader: ReadableStreamDefaultReader<Uint8Array>): Promise<void> {
  for (;;) {
    const chunk = await reader.read()
    if (chunk.done) return
  }
}

test('a stream opens with the reconnect delay and the count now', async () => {
  const { source } = fakeSource([2])

  const frames = await readFrames(unreadEventStream(source).getReader(), 2)

  assert.deepEqual(frames, ['retry: 3000\n\n', 'event: unread\ndata: 2\n\n'])
})

test('a change sends a frame only when the count moved', async () => {
  const { source, events } = fakeSource([0, 0, 3])
  const reader = unreadEventStream(source).getReader()
  await readFrames(reader, 2)

  // The first change re-reads the same total and says nothing; the second
  // finds a message waiting.
  events.emit('changed')
  events.emit('changed')

  assert.deepEqual(await readFrames(reader, 1), ['event: unread\ndata: 3\n\n'])
})

test('a browser that goes away ends the stream and leaves no listener behind', async () => {
  const { source, events, disconnect } = fakeSource([0])
  const reader = unreadEventStream(source).getReader()
  await readFrames(reader, 2)

  disconnect()
  await readToEnd(reader)

  assert.equal(events.listenerCount('changed'), 0)
  assert.equal(events.listenerCount('closing'), 0)
})

test('the app closing ends every open stream', async () => {
  const { source, events } = fakeSource([0])
  const reader = unreadEventStream(source).getReader()
  await readFrames(reader, 2)

  events.emit('closing')
  await readToEnd(reader)

  assert.equal(events.listenerCount('changed'), 0)
})

test('a count that cannot be read ends the stream rather than stalling it', async () => {
  const events = new EventEmitter<AppEvents>()
  let reads = 0
  const reader = unreadEventStream({
    countUnread: async () => {
      reads += 1
      if (reads > 1) throw new Error('the database went away')
      return 0
    },
    events,
    disconnected: new AbortController().signal,
  }).getReader()
  await readFrames(reader, 2)

  events.emit('changed')
  await readToEnd(reader)

  assert.equal(events.listenerCount('changed'), 0)
})

/** A test app on a real socket: `app.inject` buffers, and a stream never ends. */
async function listeningTestApp(t: TestContext): Promise<{ testApp: TestApp; baseUrl: string }> {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const baseUrl = await testApp.app.listen({ host: '127.0.0.1', port: 0 })

  return { testApp, baseUrl }
}

function cookieHeader({ cookies }: SignedInActor): string {
  return Object.entries(cookies)
    .map(([name, value]) => `${name}=${value}`)
    .join('; ')
}

type LiveStream = {
  contentType: string | null
  received: () => string
  waitFor: (needle: string) => Promise<void>
  close: () => void
}

/** Subscribes as one actor and collects what the server pushes. */
async function openLiveStream(
  baseUrl: string,
  path: string,
  actor: SignedInActor,
): Promise<LiveStream> {
  const aborted = new AbortController()
  const response = await fetch(`${baseUrl}${path}`, {
    headers: { cookie: cookieHeader(actor) },
    signal: aborted.signal,
  })
  assert.equal(response.status, 200)
  assert.ok(response.body !== null)

  const reader: ReadableStreamDefaultReader<Uint8Array> = response.body.getReader()
  let received = ''
  void (async () => {
    try {
      for (;;) {
        const chunk = await reader.read()
        if (chunk.done) return
        received += decoder.decode(chunk.value, { stream: true })
      }
    } catch {
      // The test aborted the request, or the app closed under it.
    }
  })()

  return {
    contentType: response.headers.get('content-type'),
    received: () => received,
    waitFor: async (needle: string) => {
      for (let attempt = 0; attempt < 200; attempt += 1) {
        if (received.includes(needle)) return
        await new Promise((resolve) => setTimeout(resolve, 10))
      }
      throw new Error(`the stream never carried ${JSON.stringify(needle)}, only ${received}`)
    },
    close: () => aborted.abort(),
  }
}

test('the seller stream answers as an event stream carrying the count now', async (t) => {
  const { testApp, baseUrl } = await listeningTestApp(t)
  const seller = await signInAsSeller(testApp)

  const stream = await openLiveStream(baseUrl, '/seller/events', seller)
  t.after(stream.close)

  assert.match(stream.contentType ?? '', /^text\/event-stream/)
  await stream.waitFor('event: unread\ndata: 0\n\n')
})

test('a posted message reaches the recipient and nobody else', async (t) => {
  const { testApp, baseUrl } = await listeningTestApp(t)
  const customer = await signInAsCustomer(testApp)
  const operator = await signInAsAdmin(testApp)
  const seller = await signInAsSeller(testApp)
  const conversation = await openConversation(
    { db: testApp.db, clock: testApp.clock },
    { kind: 'admin_customer', adminId: operator.id, customerId: customer.id },
  )

  const operatorStream = await openLiveStream(baseUrl, '/admin/events', operator)
  const sellerStream = await openLiveStream(baseUrl, '/seller/events', seller)
  t.after(operatorStream.close)
  t.after(sellerStream.close)
  await operatorStream.waitFor('data: 0')
  await sellerStream.waitFor('data: 0')

  const posted = await fetch(`${baseUrl}/messages/${conversation.id}`, {
    method: 'POST',
    headers: {
      cookie: cookieHeader(customer),
      'content-type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({ body: 'Any news on my order?' }),
    redirect: 'manual',
  })

  assert.equal(posted.status, 302)
  await operatorStream.waitFor('event: unread\ndata: 1\n\n')
  assert.equal(sellerStream.received(), 'retry: 3000\n\nevent: unread\ndata: 0\n\n')
})

test('every layout loads the badge script and the policy still allows only this origin', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)
  const pages: ReadonlyArray<[string, Record<string, string>]> = [
    ['/', {}],
    ['/seller', seller.cookies],
    ['/admin', operator.cookies],
  ]

  for (const [url, cookies] of pages) {
    const response = await testApp.app.inject({ url, cookies })

    assert.match(response.body, /<script defer src="\/app\.js"><\/script>/)
    assert.match(String(response.headers['content-security-policy']), /script-src 'self'/)
  }

  assert.equal((await testApp.app.inject({ url: '/app.js' })).statusCode, 200)
})
