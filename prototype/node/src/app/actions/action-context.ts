import type { Clock } from '../clock.ts'
import type { AppDatabase } from '../db/database.ts'
import type { NotificationDelivery } from '../delivery/notification-delivery.ts'
import type { AppLogger } from '../log-story.ts'

/**
 * Everything a commerce action needs from the world. `db` is a Kysely handle
 * that may already be a transaction, which is what lets one action call
 * another and land in the same unit of work.
 *
 * `log` is where the action tells its story. A caller with nowhere to write —
 * a seed, a fixture, a unit test about the write itself — leaves it out and the
 * action stays silent.
 *
 * `notificationDelivery` overrides where a notification goes beyond the in-app
 * inbox; left out, `notify` queues it for the outbox.
 *
 * `rootStory` is set by a CLI entrypoint so the story the action tells for it
 * opens the process; consumed by `actionStory`.
 */
export type ActionContext = {
  db: AppDatabase
  clock: Clock
  log?: AppLogger
  notificationDelivery?: NotificationDelivery
  rootStory?: boolean
}
