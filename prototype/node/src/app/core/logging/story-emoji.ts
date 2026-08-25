import type { LogLineLevel, LogPhase } from './log-event.ts'

/**
 * `docs/alignment.md` §2.4's table, in priority order: `warn` sits above `did`
 * so `rate_limit.exceed` — a `did` written at `warn` — reads ⚠️ rather than 🟢.
 */
export function storyEmoji(phase: LogPhase, level: LogLineLevel, root: boolean): string | null {
  if (root) {
    if (phase === 'will') return '🎬'
    if (phase === 'failed') return '❌'
  }
  if (level === 'warn') return '⚠️'
  if (phase === 'refused') return '⚠️'
  if (phase === 'did') return '🟢'
  if (phase === 'failed') return '🛑'

  return null
}

/** `msg` with its story emoji in front, or `msg` unchanged where none applies. */
export function prefixedMsg(msg: string, phase: LogPhase, level: LogLineLevel, root: boolean): string {
  const emoji = storyEmoji(phase, level, root)

  return emoji === null ? msg : `${emoji} ${msg}`
}
