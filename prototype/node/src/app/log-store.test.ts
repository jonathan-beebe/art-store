import { test, type TestContext } from 'node:test'
import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { DatabaseSync } from 'node:sqlite'
import { removeDatabaseFile } from './db/database.ts'
import type { NewLogLine } from './db/logs-schema.ts'
import { logStoreStream, openLogStore, pruneLogLines, type LogStore } from './log-store.ts'

/** Matches the constant in `log-store.ts`; past it new lines are dropped. */
const BUFFER_CAP = 10_000

/** A full §2.1 line: all eleven payload fields plus the framework extras
 * `raw` alone keeps. */
const FULL_LINE = JSON.stringify({
  ts: '2026-08-23T18:00:00.019Z',
  level: 'error',
  event: 'order.place',
  phase: 'failed',
  msg: '❌ placing the order failed',
  pid: 7,
  hostname: 'app',
  request_id: 'req_1',
  session_id: 'ses_01J',
  actor_type: 'customer',
  actor_id: 'cus_01J',
  txn_id: 'txn_01J',
  duration_ms: 15,
  data: { order_id: 'ord_01J', total_cents: 12000 },
  error: { type: 'Error', message: 'no stock left' },
})

function database(store: LogStore): DatabaseSync {
  if (store.database === null) throw new Error('the store is disabled')

  return store.database
}

function storedRows(store: LogStore): Array<Record<string, unknown>> {
  return database(store).prepare('SELECT * FROM log_lines ORDER BY id').all()
}

function rowCount(store: LogStore): number {
  const row = database(store).prepare('SELECT count(*) AS n FROM log_lines').get() as { n: number }

  return row.n
}

/** A line for direct `append` calls, where the stream's parsing is not the point. */
function bufferedLine(msg: string): NewLogLine {
  return {
    ts: '2026-08-24T12:00:00.000Z',
    level: 'info',
    event: 'app.log',
    phase: null,
    msg,
    requestId: null,
    sessionId: null,
    actorType: null,
    actorId: null,
    txnId: null,
    durationMs: null,
    data: null,
    error: null,
    raw: msg,
  }
}

/** A stdout stand-in, so stream tests watch the passthrough instead of
 * printing JSON into the runner's own output. */
function captureSink(): { chunks: string[]; write(chunk: string): boolean } {
  const chunks: string[] = []

  return {
    chunks,
    write(chunk: string): boolean {
      chunks.push(chunk)
      return true
    },
  }
}

function captureStderr(t: TestContext): string[] {
  const chunks: string[] = []
  t.mock.method(process.stderr, 'write', ((chunk: string) => {
    chunks.push(chunk)
    return true
  }) as never)

  return chunks
}

function temporaryStoreFile(): string {
  return path.join(tmpdir(), `art-store-log-store-${randomUUID()}.sqlite3`)
}

const nextMacrotask = (): Promise<void> => new Promise((resolve) => setImmediate(resolve))

test('a full §2.1 line lands in every column with raw byte-identical', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  logStoreStream(store, { stdout: captureSink() }).write(`${FULL_LINE}\n`)
  store.flushSync()

  const [row] = storedRows(store)
  assert.ok(row !== undefined)
  assert.equal(row.ts, '2026-08-23T18:00:00.019Z')
  assert.equal(row.level, 'error')
  assert.equal(row.event, 'order.place')
  assert.equal(row.phase, 'failed')
  assert.equal(row.msg, '❌ placing the order failed')
  assert.equal(row.request_id, 'req_1')
  assert.equal(row.session_id, 'ses_01J')
  assert.equal(row.actor_type, 'customer')
  assert.equal(row.actor_id, 'cus_01J')
  assert.equal(row.txn_id, 'txn_01J')
  assert.equal(row.duration_ms, 15)
  assert.equal(row.data, JSON.stringify({ order_id: 'ord_01J', total_cents: 12000 }))
  assert.equal(row.error, JSON.stringify({ type: 'Error', message: 'no stock left' }))
  assert.equal(row.raw, FULL_LINE)
})

test('a line that fails JSON.parse stores raw plus a receive-time ts and nulls elsewhere', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  logStoreStream(store, { stdout: captureSink() }).write('not json at all\n')
  store.flushSync()

  const [row] = storedRows(store)
  assert.ok(row !== undefined)
  assert.equal(row.raw, 'not json at all')
  assert.match(String(row.ts), /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/)
  assert.equal(row.level, null)
  assert.equal(row.event, null)
  assert.equal(row.msg, null)
  assert.equal(row.data, null)
})

test('a chunk ending mid-line carries the partial to the next write', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const stream = logStoreStream(store, { stdout: captureSink() })

  const half = Math.floor(FULL_LINE.length / 2)
  stream.write(FULL_LINE.slice(0, half))
  store.flushSync()
  assert.equal(rowCount(store), 0)

  stream.write(`${FULL_LINE.slice(half)}\n{"ts":"2026-08-23T18:00:01.000Z","msg":"next"}\n`)
  store.flushSync()

  const rows = storedRows(store)
  assert.equal(rows.length, 2)
  assert.equal(rows[0]?.raw, FULL_LINE)
  assert.equal(rows[1]?.msg, 'next')
})

test('the mirrored columns survive the 64 KiB cap on raw', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const line = JSON.stringify({
    ts: '2026-08-23T18:00:00.000Z',
    msg: 'big payload',
    data: { blob: 'x'.repeat(70_000) },
  })

  logStoreStream(store, { stdout: captureSink() }).write(`${line}\n`)
  store.flushSync()

  const [row] = storedRows(store)
  assert.ok(row !== undefined)
  assert.equal(row.msg, 'big payload')
  assert.equal(String(row.data).length > 64 * 1024, true)
  assert.equal(Buffer.byteLength(String(row.raw)), 64 * 1024)
})

test('the stream writes the chunk to stdout verbatim before anything else', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const order: string[] = []
  const sink = {
    write(chunk: string): boolean {
      order.push(`stdout:${chunk}`)
      return true
    },
  }
  const watched: LogStore = {
    ...store,
    append: (line) => {
      order.push('append')
      store.append(line)
    },
  }

  logStoreStream(watched, { stdout: sink }).write('{"msg":"a"}\n')

  assert.deepEqual(order, ['stdout:{"msg":"a"}\n', 'append'])
})

test('the passthrough survives an append that throws, with the failure on stderr', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const stderr = captureStderr(t)
  const sink = captureSink()
  const broken: LogStore = {
    ...store,
    append: () => {
      throw new Error('the buffer is on fire')
    },
  }

  logStoreStream(broken, { stdout: sink }).write('{"msg":"a"}\n')

  assert.deepEqual(sink.chunks, ['{"msg":"a"}\n'])
  assert.equal(stderr.some((chunk) => chunk.includes('the buffer is on fire')), true)
})

test('the passthrough survives a stdout that throws', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const sink = {
    write(): boolean {
      throw new Error('EPIPE')
    },
  }

  logStoreStream(store, { stdout: sink }).write('{"msg":"a"}\n')
  store.flushSync()

  assert.equal(rowCount(store), 1)
})

test('appends flush on the next macrotask, not inside write', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  logStoreStream(store, { stdout: captureSink() }).write('{"msg":"a"}\n')

  assert.equal(rowCount(store), 0)
  await nextMacrotask()
  assert.equal(rowCount(store), 1)
})

test('flushSync makes every buffered line queryable immediately', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  store.append(bufferedLine('one'))
  store.append(bufferedLine('two'))
  store.flushSync()

  assert.deepEqual(storedRows(store).map((row) => row.msg), ['one', 'two'])
})

test('a failed flush re-buffers the batch and a later flush lands it once', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const stderr = captureStderr(t)

  store.append(bufferedLine('kept'))
  database(store).exec('ALTER TABLE log_lines RENAME TO log_lines_hidden')
  store.flushSync()
  assert.equal(stderr.filter((chunk) => chunk.startsWith('log store:')).length, 1)

  database(store).exec('ALTER TABLE log_lines_hidden RENAME TO log_lines')
  store.flushSync()

  assert.deepEqual(storedRows(store).map((row) => row.msg), ['kept'])
})

test('past the buffer cap new lines are dropped with one stderr notice', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)
  const stderr = captureStderr(t)

  database(store).exec('ALTER TABLE log_lines RENAME TO log_lines_hidden')
  for (let i = 0; i < BUFFER_CAP + 5; i += 1) {
    store.append(bufferedLine(`line ${i}`))
  }

  const dropNotices = stderr.filter((chunk) => chunk.includes('buffer full'))
  assert.equal(dropNotices.length, 1)

  database(store).exec('ALTER TABLE log_lines_hidden RENAME TO log_lines')
  store.flushSync()
  assert.equal(rowCount(store), BUFFER_CAP)
})

test('opening the same file twice bootstraps once and keeps writing', async (t) => {
  const file = temporaryStoreFile()
  t.after(() => removeDatabaseFile(file))

  const first = openLogStore(file)
  first.append(bufferedLine('from the first open'))
  first.flushSync()
  first.close()

  const second = openLogStore(file)
  t.after(second.close)
  second.append(bufferedLine('from the second open'))
  second.flushSync()

  const journalMode = database(second).prepare('PRAGMA journal_mode').get() as { journal_mode: string }
  const version = database(second).prepare('PRAGMA user_version').get() as { user_version: number }
  assert.equal(journalMode.journal_mode, 'wal')
  assert.equal(version.user_version, 1)
  assert.deepEqual(storedRows(second).map((row) => row.msg), [
    'from the first open',
    'from the second open',
  ])
})

test('a file whose user_version is ahead of the code disables the store', async (t) => {
  const file = temporaryStoreFile()
  t.after(() => removeDatabaseFile(file))
  const seeded = new DatabaseSync(file)
  seeded.exec('PRAGMA user_version = 99')
  seeded.close()
  const stdout = captureSink()

  const store = openLogStore(file, { stdout })

  assert.equal(store.database, null)
  const warn = stdout.chunks.find((chunk) => chunk.includes('log store disabled'))
  assert.ok(warn !== undefined)
  const line = JSON.parse(warn) as Record<string, unknown>
  assert.equal(line.level, 'warn')
  assert.equal(line.event, 'app.log')
  assert.deepEqual(line.data, { log_database_file: file })
})

test('a file that cannot be opened disables the store and the stream stays a passthrough', async () => {
  const stdout = captureSink()
  const file = path.join(tmpdir(), randomUUID(), 'missing', 'logs.sqlite3')

  const store = openLogStore(file, { stdout })
  const sink = captureSink()
  logStoreStream(store, { stdout: sink }).write('{"msg":"still on stdout"}\n')
  store.append(bufferedLine('goes nowhere'))
  store.flushSync()
  store.close()

  assert.equal(store.database, null)
  assert.deepEqual(sink.chunks, ['{"msg":"still on stdout"}\n'])
  assert.equal(stdout.chunks.some((chunk) => chunk.includes('log store disabled')), true)
})

test('a bootstrap that fails midway disables the store rather than throwing', async (t) => {
  const file = temporaryStoreFile()
  t.after(() => removeDatabaseFile(file))
  // A table squatting on an index name: the DDL's CREATE INDEX refuses it, so
  // the bootstrap transaction rolls back.
  const seeded = new DatabaseSync(file)
  seeded.exec('CREATE TABLE log_lines_ts (x)')
  seeded.close()
  const stdout = captureSink()

  const store = openLogStore(file, { stdout })

  assert.equal(store.database, null)
  assert.equal(stdout.chunks.some((chunk) => chunk.includes('log store disabled')), true)
})

const PRUNE_CUTOFF = new Date('2026-08-10T00:00:00.000Z')

/** A line at an exact `ts`, for planting rows around the retention cutoff. */
function lineAt(ts: string): NewLogLine {
  return { ...bufferedLine(`line at ${ts}`), ts }
}

test('the prune deletes a line just under the cutoff and keeps one exactly at it', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  store.append(lineAt('2026-08-09T23:59:59.999Z'))
  store.append(lineAt('2026-08-10T00:00:00.000Z'))
  store.append(lineAt('2026-08-10T12:00:00.000Z'))
  store.flushSync()

  const deleted = pruneLogLines(store, PRUNE_CUTOFF)

  assert.equal(deleted, 1)
  assert.deepEqual(storedRows(store).map((row) => row.ts), [
    '2026-08-10T00:00:00.000Z',
    '2026-08-10T12:00:00.000Z',
  ])
})

test('the prune loops over batches until no stale row is left', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  for (let i = 0; i < 7; i += 1) {
    store.append(lineAt(`2026-08-09T00:00:0${i}.000Z`))
  }
  store.append(lineAt('2026-08-11T00:00:00.000Z'))
  store.flushSync()

  const deleted = pruneLogLines(store, PRUNE_CUTOFF, { batchSize: 3 })

  assert.equal(deleted, 7)
  assert.deepEqual(storedRows(store).map((row) => row.ts), ['2026-08-11T00:00:00.000Z'])
})

test('a prune with nothing stale deletes nothing and says so', async (t) => {
  const store = openLogStore(':memory:')
  t.after(store.close)

  store.append(lineAt('2026-08-10T00:00:01.000Z'))
  store.flushSync()

  assert.equal(pruneLogLines(store, PRUNE_CUTOFF), 0)
  assert.equal(rowCount(store), 1)
})

test('a disabled store prunes nothing', async () => {
  const file = path.join(tmpdir(), randomUUID(), 'missing', 'logs.sqlite3')
  const store = openLogStore(file, { stdout: captureSink() })

  assert.equal(store.database, null)
  assert.equal(pruneLogLines(store, PRUNE_CUTOFF), 0)
})

test('open registers a final flush on process exit and close unregisters it', async () => {
  const before = process.listeners('exit').length

  const store = openLogStore(':memory:')
  assert.equal(process.listeners('exit').length, before + 1)

  store.close()
  assert.equal(process.listeners('exit').length, before)

  // Closing again, or appending into a closed store, is a quiet no-op.
  store.close()
  store.append(bufferedLine('after close'))
  store.flushSync()
})
