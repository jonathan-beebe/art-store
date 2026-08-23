import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { adminPage } from '../page.ts'
import { outboxRow, outboxRows } from '../queries/outbox-rows.ts'
import { drainOutbox, type DrainedMessage } from '../../../actions/outbox/drain-outbox.ts'
import { renderOutboxMessage } from '../../../delivery/outbox-message.ts'
import { parseIdParam } from '../../../plugins/id-param.ts'

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db, config } = request.server

  return reply.render(
    'outbox',
    adminPage('Outbox', { rows: await outboxRows({ db }), outboxDir: config.outboxDir }),
  )
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return reply.code(404).type('text/plain').send('Not found')

  const message = await outboxRow({ db: request.server.db }, id)
  if (message === null) return reply.code(404).type('text/plain').send('Not found')

  return reply.render(
    'outbox-message',
    adminPage(`Outbox message #${message.id}`, {
      message,
      rendered: renderOutboxMessage(message),
    }),
  )
}

async function drain(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db, clock, config } = request.server
  const drained = await drainOutbox({ db, clock }, { outboxDir: config.outboxDir })

  reply.setFlash({ notice: drainedMessage(drained, config.outboxDir) })

  return reply.redirect('/admin/outbox')
}

export const outboxRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/outbox', index)
  admin.post('/outbox/drain', drain)
  admin.get('/outbox/:id', show)

  done()
}

function drainedMessage(drained: readonly DrainedMessage[], outboxDir: string): string {
  if (drained.length === 0) return 'The outbox had nothing waiting to send.'

  return `Wrote ${drained.length} message(s) to ${outboxDir}.`
}
