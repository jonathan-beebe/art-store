import { DatabaseSync } from 'node:sqlite'
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

type SqlInputValue = null | number | bigint | string | NodeJS.ArrayBufferView

/**
 * Kysely dialect over the SQLite that ships with Node, holding one connection.
 * `SqliteAdapter` reports that the dialect cannot open a second one, which is
 * what makes Kysely serialize every query and transaction through it.
 */
export class NodeSqliteDialect implements Dialect {
  readonly #file: string

  constructor(file: string) {
    this.#file = file
  }

  createDriver(): Driver {
    return new NodeSqliteDriver(this.#file)
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
  readonly #file: string
  #database: DatabaseSync | undefined
  #connection: DatabaseConnection | undefined

  constructor(file: string) {
    this.#file = file
  }

  async init(): Promise<void> {
    const database = new DatabaseSync(this.#file)

    // Both pragmas are per-connection and neither SQLite default suits this app:
    // foreign keys are off, and a zero busy timeout fails a contended write
    // immediately instead of waiting for the other writer to finish.
    database.exec('PRAGMA foreign_keys = ON')
    database.exec(`PRAGMA busy_timeout = ${BUSY_TIMEOUT_MS}`)

    this.#database = database
    this.#connection = new NodeSqliteConnection(database)
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
    this.#database?.close()
    this.#database = undefined
    this.#connection = undefined
  }
}

class NodeSqliteConnection implements DatabaseConnection {
  readonly #database: DatabaseSync

  constructor(database: DatabaseSync) {
    this.#database = database
  }

  async executeQuery<R>(compiledQuery: CompiledQuery): Promise<QueryResult<R>> {
    const statement = this.#database.prepare(compiledQuery.sql)
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
    const statement = this.#database.prepare(compiledQuery.sql)

    for (const row of statement.iterate(...bindableParameters(compiledQuery.parameters))) {
      yield { rows: [asRow<R>(row)] }
    }
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
