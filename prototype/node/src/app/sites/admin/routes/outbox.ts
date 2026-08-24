import { drainOutbox, type DrainedMessage } from '../../../actions/outbox/drain-outbox.ts'
import { renderOutboxMessage } from '../../../delivery/outbox-message.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { outboxRow, outboxRows } from '../queries/outbox-rows.ts'

export const outboxRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/outbox', async (_request, reply) => {
    const { db, config } = admin

    return reply.render(
      'outbox',
      adminPage('Outbox', { rows: await outboxRows({ db }), outboxDir: config.outboxDir }),
    )
  })

  admin.post('/outbox/drain', async (_request, reply) => {
    const { db, clock, config } = admin
    const drained = await drainOutbox({ db, clock }, { outboxDir: config.outboxDir })

    reply.setFlash({ notice: drainedMessage(drained, config.outboxDir) })

    return reply.redirect('/admin/outbox')
  })

  admin.get('/outbox/:id', { schema: { params: idParams('obx') } }, async (request, reply) => {
    const message = await outboxRow({ db: admin.db }, request.params.id)
    if (message === null) return reply.code(404).type('text/plain').send('Not found')

    return reply.render(
      'outbox-message',
      adminPage(`Outbox message ${message.id}`, {
        message,
        rendered: renderOutboxMessage(message),
      }),
    )
  })

  done()
}

function drainedMessage(drained: readonly DrainedMessage[], outboxDir: string): string {
  if (drained.length === 0) return 'The outbox had nothing waiting to send.'

  return `Wrote ${drained.length} message(s) to ${outboxDir}.`
}
