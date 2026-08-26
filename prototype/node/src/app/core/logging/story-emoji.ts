import type { LogLineLevel, LogPhase } from './log-event.ts'

/**
 * `docs/alignment.md` §2.4's table, in row order: the `root` block owns the
 * boundary emoji (`will`, `did`, `failed`); a nested `did` falls through
 * unprefixed, while `warn` still claims ⚠️.
 */
export function storyEmoji(phase: LogPhase, level: LogLineLevel, root: boolean): string | null {
  if (root) {
    if (phase === 'will') return '🎬'
    if (phase === 'did') return '🟢'
    if (phase === 'failed') return '❌'
  }
  if (level === 'warn') return '⚠️'
  if (phase === 'refused') return '⚠️'
  if (phase === 'failed') return '🛑'

  return null
}

/** `msg` with its story emoji in front, or `msg` unchanged where none applies. */
export function prefixedMsg(msg: string, phase: LogPhase, level: LogLineLevel, root: boolean): string {
  const emoji = storyEmoji(phase, level, root)

  return emoji === null ? msg : `${emoji} ${msg}`
}
