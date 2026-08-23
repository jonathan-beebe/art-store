/** What an order does to the stock a listing holds. */
export const STOCK_CHANGES = ['take', 'restore', 'keep'] as const

export type StockChange = (typeof STOCK_CHANGES)[number]
