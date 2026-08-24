import type { FastifyPluginCallback } from 'fastify'
import { adminConsoleRoutes } from './console.ts'
import { findAdminByEmail } from '../../actions/auth/find-admin-by-email.ts'
import { csrfProtection } from '../../plugins/csrf.ts'
import { addNotFoundPage } from '../../plugins/error-pages.ts'
import { addSiteRender } from '../../plugins/site-render.ts'
import { countUnreadMessages } from '../../plugins/unread-messages.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'

export const adminSite: FastifyPluginCallback = (admin, _options, done) => {
  const renderPage = addSiteRender(admin, {
    pages: 'sites/admin/views',
    layout: 'sites/admin/views/layout',
  })
  admin.register(csrfProtection)
  admin.addHook('preHandler', countUnreadMessages('admin'))

  addNotFoundPage(admin, renderPage)

  admin.register(
    signInRoutes({
      // Admin rows are seeded, so an address nobody seeded gets no link at
      // all — and, per `admits`'s own contract, no response that says so.
      actorType: 'admin',
      admits: async (db, email) => (await findAdminByEmail({ db }, email)) !== null,
    }),
  )
  admin.register(adminConsoleRoutes)

  done()
}
