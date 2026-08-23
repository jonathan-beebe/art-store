import type { FastifyPluginCallback } from 'fastify'

export const homeRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/', (_request, reply) => reply.render('home', { title: 'Overview' }))

  done()
}
