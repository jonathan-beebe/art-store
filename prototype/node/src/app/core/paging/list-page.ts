/** Which slice of a list or table a viewer is looking at, and the two numbers a
 * query needs to fetch it. */
export type ListPage = {
  number: number
  size: number
  totalCount: number
  /** How many pages the collection fills; an empty one still fills a first page. */
  count: number
  offset: number
  limit: number
  isFirst: boolean
  isLast: boolean
  previousNumber: number
  nextNumber: number
}

function pageCount(size: number, totalCount: number): number {
  return Math.max(Math.ceil(totalCount / size), 1)
}

function clamp(value: number, low: number, high: number): number {
  return Math.min(Math.max(value, low), high)
}

/**
 * `requested` arrives from a query string, so anything that is not a page of
 * this collection lands on the nearest one that is.
 */
export function listPage(input: {
  requested: string | number | null | undefined
  size: number
  totalCount: number
}): ListPage {
  const { requested, size, totalCount } = input

  if (!Number.isInteger(size) || size < 1) {
    throw new RangeError(`a page holds at least one item, got ${size}`)
  }
  if (totalCount < 0) {
    throw new RangeError(`a count cannot be negative, got ${totalCount}`)
  }

  const count = pageCount(size, totalCount)
  const asked = Number.parseInt(String(requested ?? ''), 10)
  const number = clamp(Number.isNaN(asked) ? 1 : asked, 1, count)

  return {
    number,
    size,
    totalCount,
    count,
    offset: (number - 1) * size,
    limit: size,
    isFirst: number === 1,
    isLast: number === count,
    previousNumber: number - 1,
    nextNumber: number + 1,
  }
}
