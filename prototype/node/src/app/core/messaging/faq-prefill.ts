export type FaqPrefill = { question: string; answer: string; sourceMessageId: number | null }

/**
 * What a "Publish as FAQ" form starts from: the thread's opening message reads
 * as the question, the seller's most recent reply as the answer it publishes
 * alongside it.
 */
export function faqPrefill(
  messages: readonly { id: number; body: string; isMine: boolean }[],
): FaqPrefill {
  const first = messages[0]
  const lastFromSeller = messages.findLast((message) => message.isMine)

  return {
    question: first?.body ?? '',
    answer: lastFromSeller?.body ?? '',
    sourceMessageId: lastFromSeller?.id ?? null,
  }
}
