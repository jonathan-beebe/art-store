import type { FastifyPluginCallback } from 'fastify'
import { addSiteRender } from '../../plugins/site-render.ts'
import { homeRoutes } from './routes/home.ts'

export const adminSite: FastifyPluginCallback = (admin, _options, done) => {
  addSiteRender(admin, { pages: 'sites/admin/views', layout: 'sites/admin/views/layout' })

  admin.register(homeRoutes)

  done()
}
