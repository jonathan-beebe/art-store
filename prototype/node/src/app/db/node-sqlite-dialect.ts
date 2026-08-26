import { DatabaseSync } from 'node:sqlite'
import type { StatementSync } from 'node:sqlite'
import {
  CompiledQuery,
  SqliteAdapter,
  SqliteIntrospector,
  SqliteQueryCompiler,
} from 'kysely'
import type {
  DatabaseConnection,
  DatabaseIntrospector,
  Dialect,
  Driver,
  QueryResult,
} from 'kysely'

/** Milliseconds SQLite waits for another writer's lock before giving up. */
const BUSY_TIMEOUT_MS = 5000

/**
 * Upper bound on distinct prepared SQL texts a connection keeps cached. The
 * app's own queries occupy a small, fixed set of shapes well under this; the
 * bound exists only to stop unbounded ad-hoc SQL from growing the cache
 * forever.
 */
export const STATEMENT_CACHE_LIMIT = 100

type SqlInputValue = null | number | bigint | string | NodeJS.ArrayBufferView

/**
 * Kysely dialect over the SQLite that ships with Node, holding one connection.
 * `SqliteAdapter` reports that the dialect cannot open a second one, which is
 * what makes Kysely serialize every query and transaction through it.
 *
 * Given a file path, the driver opens the handle, sets its pragmas, and closes
 * it on destroy. Given an existing `DatabaseSync` — the log store's, whose
 * reads must serialize with its batch writer — the handle is borrowed as it
 * stands: its owner already set its pragmas and keeps the right to close it.
 */
export class NodeSqliteDialect implements Dialect {
  readonly #source: string | DatabaseSync

  constructor(source: string | DatabaseSync) {
    this.#source = source
  }

  createDriver(): Driver {
    return new NodeSqliteDriver(this.#source)
  }

  createQueryCompiler(): SqliteQueryCompiler {
    return new SqliteQueryCompiler()
  }

  createAdapter(): SqliteAdapter {
    return new SqliteAdapter()
  }

  // Kysely types the argument as `Kysely<any>`; naming its own parameter type
  // keeps that `any` out of this file.
  createIntrospector(db: Parameters<Dialect['createIntrospector']>[0]): DatabaseIntrospector {
    return new SqliteIntrospector(db)
  }
}

class NodeSqliteDriver implements Driver {
  readonly #source: string | DatabaseSync
  #database: DatabaseSync | undefined
  #connection: NodeSqliteConnection | undefined

  constructor(source: string | DatabaseSync) {
    this.#source = source
  }

  async init(): Promise<void> {
    // A borrowed handle keeps its owner's pragmas.
    const database = typeof this.#source === 'string' ? this.#opened(this.#source) : this.#source

    this.#database = database
    this.#connection = new NodeSqliteConnection(database)
  }

  #opened(file: string): DatabaseSync {
    const database = new DatabaseSync(file)

    // All three pragmas are per-connection and none of the SQLite defaults suit
    // this app: foreign keys are off, a zero busy timeout fails a contended
    // write immediately instead of waiting for the other writer to finish, and
    // synchronous defaults to FULL, which fsyncs on every autocommit write.
    // journal_mode is WAL (set persistently by migration, not here); under WAL,
    // NORMAL never risks corruption — a power loss can at most lose the most
    // recently committed transaction(s), not corrupt the database.
    database.exec('PRAGMA foreign_keys = ON')
    database.exec(`PRAGMA busy_timeout = ${BUSY_TIMEOUT_MS}`)
    database.exec('PRAGMA synchronous = NORMAL')

    return database
  }

  async acquireConnection(): Promise<DatabaseConnection> {
    if (!this.#connection) throw new Error('The SQLite driver is not initialized.')

    return this.#connection
  }

  async beginTransaction(connection: DatabaseConnection): Promise<void> {
    // IMMEDIATE takes the write lock up front. A deferred transaction that reads
    // before it writes loses its snapshot under WAL when another process commits
    // in between, and SQLITE_BUSY_SNAPSHOT is not a case the busy timeout retries.
    await connection.executeQuery(CompiledQuery.raw('begin immediate'))
  }

  async commitTransaction(connection: DatabaseConnection): Promise<void> {
    await connection.executeQuery(CompiledQuery.raw('commit'))
  }

  async rollbackTransaction(connection: DatabaseConnection): Promise<void> {
    await connection.executeQuery(CompiledQuery.raw('rollback'))
  }

  async releaseConnection(): Promise<void> {
    // The single connection outlives every query; only destroy closes it.
  }

  async destroy(): Promise<void> {
    // Cached statements are tied to this database handle; drop them before it closes.
    this.#connection?.clearStatements()
    // A borrowed handle is its owner's to close.
    if (typeof this.#source === 'string') this.#database?.close()
    this.#database = undefined
    this.#connection = undefined
  }
}

class NodeSqliteConnection implements DatabaseConnection {
  readonly #database: DatabaseSync
  readonly #statements = new Map<string, StatementSync>()

  constructor(database: DatabaseSync) {
    this.#database = database
  }

  async executeQuery<R>(compiledQuery: CompiledQuery): Promise<QueryResult<R>> {
    const statement = this.#preparedStatement(compiledQuery.sql)
    const parameters = bindableParameters(compiledQuery.parameters)

    // A statement that describes result columns returns rows; anything else writes.
    if (statement.columns().length > 0) {
      return { rows: statement.all(...parameters).map(asRow<R>) }
    }

    const { changes, lastInsertRowid } = statement.run(...parameters)

    return {
      insertId: BigInt(lastInsertRowid),
      numAffectedRows: BigInt(changes),
      rows: [],
    }
  }

  async *streamQuery<R>(compiledQuery: CompiledQuery): AsyncIterableIterator<QueryResult<R>> {
    // Prepared fresh, not cached: a streamed statement is iterated lazily, and a
    // second execution of the same SQL text against a cached statement would
    // reset it mid-stream.
    const statement = this.#database.prepare(compiledQuery.sql)

    for (const row of statement.iterate(...bindableParameters(compiledQuery.parameters))) {
      yield { rows: [asRow<R>(row)] }
    }
  }

  clearStatements(): void {
    this.#statements.clear()
  }

  #preparedStatement(sql: string): StatementSync {
    const cached = this.#statements.get(sql)
    if (cached) return cached

    const statement = this.#database.prepare(sql)
    this.#statements.set(sql, statement)

    if (this.#statements.size > STATEMENT_CACHE_LIMIT) {
      const oldestKey = this.#statements.keys().next().value as string
      this.#statements.delete(oldestKey)
    }

    return statement
  }
}

/**
 * Copies a row into an ordinary object. `node:sqlite` hands back rows with a null
 * prototype, which every other Kysely dialect does not; callers reading a row
 * should not have to know which dialect produced it. The row shape is the query's,
 * which only the caller's type argument knows.
 */
function asRow<R>(row: Record<string, unknown>): R {
  const copied: Record<string, unknown> = { ...row }

  return copied as R
}

/**
 * Narrows the values Kysely compiled out of a query to the four types SQLite can
 * bind, so an unbindable value names itself instead of surfacing as a driver error.
 */
function bindableParameters(parameters: readonly unknown[]): SqlInputValue[] {
  return parameters.map((parameter) => {
    if (
      parameter === null
      || typeof parameter === 'number'
      || typeof parameter === 'bigint'
      || typeof parameter === 'string'
      || parameter instanceof Uint8Array
    ) {
      return parameter
    }

    throw new TypeError(`SQLite cannot bind a ${typeof parameter} parameter.`)
  })
}
