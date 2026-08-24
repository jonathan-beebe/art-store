import { EventEmitter } from 'node:events'
import type { FastifyPluginCallback, FastifyRequest } from 'fastify'
import type { MessagingActor } from '../actions/messaging/conversation-actor.ts'
import { unreadMessageCount } from '../actions/messaging/conversation-inbox.ts'
import type { ActorType } from '../core/auth/actor-type.ts'
import { rootPlugin } from './root-plugin.ts'

/**
 * `changed` fires once after any request that wrote something, `closing` when
 * the app is shutting down. A stream re-reads its own actor's count on
 * `changed` and sends a frame only when the number moved, so nothing that
 * writes has to know whose count it moved.
 *
 * The bus is in-process. A second instance of this app would carry its own,
 * and a browser streaming from one would not hear a write that landed on the
 * other — two instances need a shared broker instead.
 */
export type AppEvents = {
  changed: []
  closing: []
}

declare module 'fastify' {
  interface FastifyInstance {
    events: EventEmitter<AppEvents>
  }
}

// One listener per open stream, so the ceiling is the number of connected
// browsers rather than the default ten.
const UNLIMITED_LISTENERS = 0

/** How long a browser waits before reconnecting a stream that dropped. */
const RECONNECT_DELAY_MS = 3000

const READ_METHODS: ReadonlySet<string> = new Set(['GET', 'HEAD', 'OPTIONS'])

/**
 * The event bus every live stream listens on, and the two hooks that feed it:
 * one after each write, one when the app starts closing.
 */
export const eventBus = rootPlugin({ name: 'eventBus' }, (app) => {
  const events = new EventEmitter<AppEvents>()
  events.setMaxListeners(UNLIMITED_LISTENERS)

  app.decorate('events', events)

  app.addHook('onResponse', async (request, reply) => {
    if (wroteSomething(request.method, reply.statusCode)) events.emit('changed')
  })

  // An open stream is an in-flight request, so `close()` waits for it. This is
  // `preClose` rather than `onClose` because `preClose` runs before the server
  // stops accepting: an `onClose` listener would be waiting for the streams it
  // is about to end.
  app.addHook('preClose', async () => {
    events.emit('closing')
  })
})

/** Only a request that changed something can have moved anyone's count. */
function wroteSomething(method: string, statusCode: number): boolean {
  return !READ_METHODS.has(method) && statusCode < 400
}

/** What one live stream reads from and ends with. */
export type UnreadStreamSource = {
  /** This actor's unread total, re-read whenever something changed. */
  countUnread: () => Promise<number>
  events: EventEmitter<AppEvents>
  /** Ends the stream: the browser went away. */
  disconnected: AbortSignal
}

const encoder = new TextEncoder()

function unreadFrame(count: number): string {
  return `event: unread\ndata: ${count}\n\n`
}

/**
 * One actor's count as `text/event-stream` bytes: the total now, then the
 * total each time it changes. Ends on client disconnect and on app shutdown,
 * dropping both listeners with it.
 */
export function unreadEventStream(source: UnreadStreamSource): ReadableStream<Uint8Array> {
  let open = true
  let sent: number | null = null
  let pending = Promise.resolve()
  let stop = (): void => {}

  return new ReadableStream({
    start(controller) {
      const send = (frame: string): void => {
        if (open) controller.enqueue(encoder.encode(frame))
      }

      const refresh = async (): Promise<void> => {
        if (!open) return

        const count = await source.countUnread()
        if (count === sent) return

        sent = count
        send(unreadFrame(count))
      }

      // Serialized, so two events arriving together cannot send the same count
      // twice. A count that cannot be read ends the stream; the browser
      // reconnects on its own.
      const push = (): void => {
        pending = pending.then(refresh).catch(() => stop())
      }

      stop = (): void => {
        if (!open) return

        open = false
        source.events.off('changed', push)
        source.events.off('closing', stop)
        source.disconnected.removeEventListener('abort', stop)
        controller.close()
      }

      send(`retry: ${RECONNECT_DELAY_MS}\n\n`)
      push()

      source.events.on('changed', push)
      source.events.on('closing', stop)
      source.disconnected.addEventListener('abort', stop, { once: true })
    },

    cancel() {
      stop()
    },
  })
}

const SITE_ACTORS: Readonly<
  Record<ActorType, (request: FastifyRequest) => MessagingActor | null>
> = {
  seller: ({ currentSeller }) =>
    currentSeller === null ? null : { type: 'seller', id: currentSeller.id },
  customer: ({ currentCustomer }) =>
    currentCustomer === null ? null : { type: 'customer', id: currentCustomer.id },
  admin: ({ currentAdmin }) =>
    currentAdmin === null ? null : { type: 'admin', id: currentAdmin.id },
}

/**
 * `GET <prefix>/events` for one site. Registered inside that site's identity
 * guard, so a stream only ever carries the count of the actor asking for it.
 */
export function unreadEventsRoute(actorType: ActorType): FastifyPluginCallback {
  const unreadEvents: FastifyPluginCallback = (site, _options, done) => {
    site.get('/events', async (request, reply) => {
      const actor = SITE_ACTORS[actorType](request)
      if (actor === null) return reply.code(204).send()

      const disconnected = new AbortController()
      request.raw.on('close', () => disconnected.abort())

      const stream = unreadEventStream({
        countUnread: () => unreadMessageCount({ db: site.db }, actor),
        events: site.events,
        disconnected: disconnected.signal,
      })

      return reply
        .header('Cache-Control', 'no-cache')
        .header('Connection', 'keep-alive')
        .type('text/event-stream')
        .send(stream)
    })

    done()
  }

  return unreadEvents
}
