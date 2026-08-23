export const FAQ_QUESTION_MAX_LENGTH = 500
export const FAQ_ANSWER_MAX_LENGTH = 2_000

export type FaqDraftFields = { question?: string; answer?: string }
export type FaqDraftErrors = Partial<Record<'question' | 'answer', string>>

/** A published question and answer. Both parts are written — the `ok` arm of
 * `parseFaqDraft` is the only way to hold one. */
export type FaqDraft = { question: string; answer: string }

export type FaqDraftResult = { ok: true; value: FaqDraft } | { ok: false; errors: FaqDraftErrors }

function questionError(value: string | undefined): string | undefined {
  const question = (value ?? '').trim()
  if (question.length === 0) {
    return 'Enter the question.'
  }
  return question.length > FAQ_QUESTION_MAX_LENGTH
    ? `Keep the question under ${FAQ_QUESTION_MAX_LENGTH} characters.`
    : undefined
}

function answerError(value: string | undefined): string | undefined {
  const answer = (value ?? '').trim()
  if (answer.length === 0) {
    return 'Enter the answer.'
  }
  return answer.length > FAQ_ANSWER_MAX_LENGTH
    ? `Keep the answer under ${FAQ_ANSWER_MAX_LENGTH} characters.`
    : undefined
}

export function parseFaqDraft(fields: FaqDraftFields): FaqDraftResult {
  const checked: readonly [keyof FaqDraftErrors, string | undefined][] = [
    ['question', questionError(fields.question)],
    ['answer', answerError(fields.answer)],
  ]

  const errors: FaqDraftErrors = {}
  for (const [field, message] of checked) {
    if (message !== undefined) errors[field] = message
  }

  if (Object.keys(errors).length > 0) {
    return { ok: false, errors }
  }

  return {
    ok: true,
    value: { question: (fields.question ?? '').trim(), answer: (fields.answer ?? '').trim() },
  }
}
