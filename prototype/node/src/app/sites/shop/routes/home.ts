import type { FastifyPluginCallback } from 'fastify'

export const homeRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.get('/', (_request, reply) => reply.render('home', { title: 'Original art' }))

  done()
}
