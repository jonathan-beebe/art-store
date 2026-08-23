export const FAQ_QUESTION_MAX_LENGTH = 500
export const FAQ_ANSWER_MAX_LENGTH = 2_000

export type FaqDraftFields = { question?: string; answer?: string }
export type FaqDraftErrors = Partial<Record<'question' | 'answer', string>>
export type FaqDraft = { question: string; answer: string }

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

export function faqDraftErrors(fields: FaqDraftFields): FaqDraftErrors {
  const entries: [keyof FaqDraftErrors, string | undefined][] = [
    ['question', questionError(fields.question)],
    ['answer', answerError(fields.answer)],
  ]

  return Object.fromEntries(entries.filter(([, message]) => message !== undefined)) as FaqDraftErrors
}

export function parseFaqDraft(fields: FaqDraftFields): FaqDraft {
  return {
    question: (fields.question ?? '').trim(),
    answer: (fields.answer ?? '').trim(),
  }
}
