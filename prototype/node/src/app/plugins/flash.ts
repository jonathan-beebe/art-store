import type { FastifyInstance, FastifyReply } from 'fastify'
import { z } from 'zod'

/**
 * What one request leaves for the page that follows it: a message to show, and
 * the magic link the debug alert prints in place of sending an email.
 */
export type Flash = {
  notice?: string
  alert?: string
  debugMagicLink?: string
}

declare module 'fastify' {
  interface FastifyReply {
    setFlash(flash: Flash): void
    takeFlash(): Flash
  }
}

const FLASH_COOKIE = 'flash'

export const flashSchema = z.object({
  notice: z.string().optional(),
  alert: z.string().optional(),
  debugMagicLink: z.string().optional(),
})

/**
 * The flash is a signed cookie the next render consumes: `setFlash` before a
 * redirect, and the page the browser lands on clears it as it shows it. A
 * tampered or stale cookie yields an empty flash rather than an error.
 */
export function addFlash(app: FastifyInstance): void {
  app.decorateReply('setFlash', function (this: FastifyReply, flash: Flash): void {
    this.setCookie(FLASH_COOKIE, JSON.stringify(flash), {
      path: '/',
      httpOnly: true,
      sameSite: 'lax',
      signed: true,
      secure: this.server.config.secureCookies,
    })
  })

  app.decorateReply('takeFlash', function (this: FastifyReply): Flash {
    const cookie = this.request.cookies[FLASH_COOKIE]
    if (cookie === undefined) return {}

    this.clearCookie(FLASH_COOKIE, { path: '/' })

    const unsigned = this.request.unsignCookie(cookie)
    if (!unsigned.valid || unsigned.value === null) return {}

    const parsed = flashSchema.safeParse(readJson(unsigned.value))
    return parsed.success ? parsed.data : {}
  })
}

function readJson(value: string): unknown {
  try {
    return JSON.parse(value)
  } catch {
    return null
  }
}
