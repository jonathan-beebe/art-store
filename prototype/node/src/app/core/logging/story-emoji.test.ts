import { test } from 'node:test'
import assert from 'node:assert/strict'
import { prefixedMsg, storyEmoji } from './story-emoji.ts'
import type { LogLineLevel, LogPhase } from './log-event.ts'

test('a root will line is 🎬, at info', () => {
  assert.equal(storyEmoji('will', 'info', true), '🎬')
})

test('a root will line is 🎬, at debug too', () => {
  assert.equal(storyEmoji('will', 'debug', true), '🎬')
})

test('a root failed line is ❌', () => {
  assert.equal(storyEmoji('failed', 'error', true), '❌')
})

test('a nested failed line is 🛑, not ❌', () => {
  assert.equal(storyEmoji('failed', 'error', false), '🛑')
})

test('a did line is 🟢 at info', () => {
  assert.equal(storyEmoji('did', 'info', false), '🟢')
})

test('a did line is 🟢 at debug', () => {
  assert.equal(storyEmoji('did', 'debug', false), '🟢')
})

test('a root did line is 🟢, the same as a nested one', () => {
  assert.equal(storyEmoji('did', 'info', true), '🟢')
})

test('a refused line is ⚠️ at info', () => {
  assert.equal(storyEmoji('refused', 'info', false), '⚠️')
})

test('a refused line is ⚠️ at debug', () => {
  assert.equal(storyEmoji('refused', 'debug', false), '⚠️')
})

test('a did line written at warn is ⚠️, warn outranking did', () => {
  assert.equal(storyEmoji('did', 'warn', false), '⚠️')
})

test('a doing line written at warn is ⚠️', () => {
  assert.equal(storyEmoji('doing', 'warn', false), '⚠️')
})

test('a nested will line carries no prefix', () => {
  assert.equal(storyEmoji('will', 'info', false), null)
})

test('a doing line at info carries no prefix', () => {
  assert.equal(storyEmoji('doing', 'info', false), null)
})

test('a doing line at debug carries no prefix', () => {
  assert.equal(storyEmoji('doing', 'debug', false), null)
})

test('every phase and level combination resolves to the documented emoji', () => {
  const table: ReadonlyArray<[LogPhase, LogLineLevel, boolean, string | null]> = [
    ['will', 'info', true, '🎬'],
    ['will', 'debug', true, '🎬'],
    ['failed', 'error', true, '❌'],
    ['failed', 'error', false, '🛑'],
    ['did', 'info', false, '🟢'],
    ['did', 'debug', false, '🟢'],
    ['did', 'warn', false, '⚠️'],
    ['refused', 'info', false, '⚠️'],
    ['refused', 'debug', false, '⚠️'],
    ['doing', 'warn', false, '⚠️'],
    ['will', 'info', false, null],
    ['doing', 'info', false, null],
    ['doing', 'debug', false, null],
  ]

  for (const [phase, level, root, expected] of table) {
    assert.equal(storyEmoji(phase, level, root), expected, `${phase}/${level}/root=${String(root)}`)
  }
})

test('prefixedMsg joins the emoji and the message with one space', () => {
  assert.equal(prefixedMsg('placing an order from the cart', 'will', 'info', true), '🎬 placing an order from the cart')
})

test('prefixedMsg returns the message unchanged when storyEmoji finds no prefix', () => {
  assert.equal(prefixedMsg('a step inside the work', 'doing', 'info', false), 'a step inside the work')
})
