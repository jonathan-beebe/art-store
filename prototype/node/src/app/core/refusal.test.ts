import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mustSucceed, refused, type IllegalTransition, type Refusal } from './refusal.ts'
import { BrokenContractError } from './defect.ts'

test('a refusal carries the reason and the data behind it', () => {
  assert.deepEqual(refused('card_declined', { order_id: 'ord_1' }), {
    outcome: 'refused',
    reason: 'card_declined',
    data: { order_id: 'ord_1' },
  })
})

test('a refusal built without data carries no data key at all', () => {
  const refusal = refused('expired')

  assert.deepEqual(refusal, { outcome: 'refused', reason: 'expired' })
  assert.ok(!('data' in refusal))
})

test('a transition refusal promises its two statuses in the type', () => {
  const refusal: IllegalTransition<'draft' | 'sold'> = refused('illegal_transition', {
    status_from: 'draft',
    status_to: 'sold',
  } as const)

  assert.deepEqual(refusal.data, { status_from: 'draft', status_to: 'sold' })
})

test('a refusal without the promised facts does not compile as a transition refusal', () => {
  // Never run — the assertion is the compile step's.
  const rejectsMissingFacts = () => {
    // @ts-expect-error -- an illegal_transition refusal requires status_from and status_to
    const refusal: IllegalTransition<'draft' | 'sold'> = refused('illegal_transition')

    return refusal
  }

  assert.equal(typeof rejectsMissingFacts, 'function')
})

test('mustSucceed passes a success result through unchanged', () => {
  const posted = { outcome: 'posted' as const, message: { id: 'msg_1' } }

  const result = mustSucceed(posted)

  assert.equal(result, posted)
  assert.equal(result.message.id, 'msg_1')
})

test('mustSucceed throws a broken contract naming the refusal reason and data', () => {
  const refusal = refused('card_declined', { order_id: 'ord_1' })

  assert.throws(
    () => mustSucceed(refusal),
    (error: unknown) => {
      assert.ok(error instanceof BrokenContractError)
      assert.equal(error.reason, 'card_declined')
      assert.deepEqual(error.data, { order_id: 'ord_1' })
      assert.equal(error.message, 'the action was refused: card_declined')
      return true
    },
  )
})

test('mustSucceed takes a caller-supplied sentence as the error message', () => {
  const refusal = refused('card_declined', { order_id: 'ord_1' })

  assert.throws(
    () => mustSucceed(refusal, 'the sentence'),
    (error: unknown) => {
      assert.ok(error instanceof BrokenContractError)
      assert.equal(error.message, 'the sentence')
      return true
    },
  )
})

test('mustSucceed throws with no data when the refusal carries none', () => {
  const refusal = refused('expired')

  assert.throws(
    () => mustSucceed(refusal),
    (error: unknown) => {
      assert.ok(error instanceof BrokenContractError)
      assert.equal(error.data, undefined)
      return true
    },
  )
})

test('mustSucceed narrows a union to its success arm at compile time', () => {
  const result: { outcome: 'ok'; value: number } | Refusal<'nope'> = { outcome: 'ok', value: 1 }

  const succeeded = mustSucceed(result)

  assert.equal(succeeded.value, 1)

  const rejectsReasonAccess = (): string => {
    // @ts-expect-error -- mustSucceed's return type is the success arm alone; it carries no reason
    return succeeded.reason as string
  }

  assert.equal(typeof rejectsReasonAccess, 'function')
})
