import type { FastifyPluginCallback } from 'fastify'
import { adminConsoleRoutes } from './console.ts'
import { findAdminByEmail } from '../../actions/auth/find-admin-by-email.ts'
import { addSiteRender } from '../../plugins/site-render.ts'
import { countUnreadMessages } from '../../plugins/unread-messages.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'

export const adminSite: FastifyPluginCallback = (admin, _options, done) => {
  addSiteRender(admin, { pages: 'sites/admin/views', layout: 'sites/admin/views/layout' })
  admin.addHook('preHandler', countUnreadMessages('admin'))

  admin.register(
    signInRoutes({
      // Admin rows are seeded, so an address nobody seeded gets no link at all.
      actorType: 'admin',
      admits: async (db, email) => (await findAdminByEmail({ db }, email)) !== null,
      refusal: 'That address cannot sign in to the admin site.',
    }),
  )
  admin.register(adminConsoleRoutes)

  done()
}
