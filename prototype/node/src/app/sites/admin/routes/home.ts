import type { FastifyPluginCallback } from 'fastify'

export const homeRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/', (_request, reply) => reply.render('home', { title: 'Overview' }))

  done()
}
