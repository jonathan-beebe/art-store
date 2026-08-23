export type DatabaseCheck = 'ok' | 'failed'
export type MigrationsCheck = 'current' | 'pending'
export type HealthStatus = 'ok' | 'draining' | 'unavailable'

export type HealthChecks = {
  database: DatabaseCheck
  migrations: MigrationsCheck
}

/**
 * Decides what an instance reports for `/health`. Draining wins over the
 * checks — an instance being taken out of rotation answers 503 even if the
 * database is fine, since new requests should stop arriving either way.
 */
export function evaluateHealth(checks: HealthChecks, draining: boolean): HealthStatus {
  if (draining) return 'draining'
  if (checks.database === 'failed' || checks.migrations === 'pending') return 'unavailable'

  return 'ok'
}

/** `/health`'s status code: 200 only when the instance can serve traffic. */
export function healthStatusCode(status: HealthStatus): 200 | 503 {
  return status === 'ok' ? 200 : 503
}
