/**
 * Integer cents — the only representation of money in the system. Nothing
 * divides until `percentOfCents`, and nothing renders until `formatCents`.
 *
 * The brand keeps the other numbers in the domain — a quantity, a percentage,
 * a row id — from standing in for an amount. `cents`, `parseDollars`, and
 * `centsFromColumn` are the only ways to make one, and each of them checks
 * what a bare `number` could not.
 */
export type Cents = number & { readonly __brand: 'Cents' }

function assertIntegerAmount(value: number, label: string): void {
  if (!Number.isInteger(value)) {
    throw new Error(`${label} must be an integer number of cents, got ${value}`)
  }
}

/** An amount written as a plain number of cents — a literal, a zero balance,
 * a seeded figure. The one place a `number` becomes `Cents`. */
export function cents(amount: number): Cents {
  assertIntegerAmount(amount, 'cents: amount')

  return amount as Cents
}

export const ZERO_CENTS = cents(0)

/** An amount as a money column hands it back: a stored column arrives as a
 * number, and a `sum` over one arrives as a number, a string, or a bigint
 * depending on the driver and the width of the total. */
export function centsFromColumn(value: number | string | bigint): Cents {
  return cents(Number(value))
}

export function addCents(a: Cents, b: Cents): Cents {
  return cents(a + b)
}

export function subtractCents(a: Cents, b: Cents): Cents {
  return cents(a - b)
}

/** The same amount owed the other way — what turns a payout into the negative
 * ledger entry that lets a balance fold the whole ledger by adding. */
export function negateCents(amount: Cents): Cents {
  return cents(amount === 0 ? 0 : -amount)
}

export function multiplyCents(amount: Cents, factor: number): Cents {
  // factor is a quantity (order line items, not a percentage or share), so a
  // fractional value is always a caller mistake.
  assertIntegerAmount(factor, 'multiplyCents: factor')

  return cents(amount * factor)
}

export function percentOfCents(amount: Cents, percent: number): Cents {
  if (!Number.isFinite(percent)) {
    throw new Error(`percentOfCents: percent must be a finite number, got ${percent}`)
  }

  const scaled = Math.abs(amount * percent)
  const whole = Math.floor(scaled / 100)
  const remainder = scaled - whole * 100
  // Half a cent rounds away from zero so a platform fee and its reversal
  // land on the same amount.
  const rounded = remainder * 2 >= 100 ? whole + 1 : whole
  const signed = amount * percent < 0 ? -rounded : rounded

  // Negative zero would render as "-$0.00".
  return cents(signed === 0 ? 0 : signed)
}

const CURRENCY_FORMAT = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

export function formatCents(amount: Cents): string {
  return CURRENCY_FORMAT.format(amount / 100)
}

const DOLLAR_AMOUNT_PATTERN = /^(-)?\$?(\d{1,3}(?:,\d{3})*|\d+)(?:\.(\d{2}))?$/

/** The grammar of an amount typed into a form: an optional sign, an optional
 * `$`, whole dollars with or without thousands separators, and either two
 * decimal places or none. Every price field checks against this one rule, so
 * what a form refuses and what `parseDollars` refuses cannot drift apart. */
export function isDollarAmount(input: string): boolean {
  return DOLLAR_AMOUNT_PATTERN.test(input.trim())
}

export function parseDollars(input: string): Cents {
  const trimmed = input.trim()
  const match = DOLLAR_AMOUNT_PATTERN.exec(trimmed)
  if (match === null) {
    throw new Error(`parseDollars: not a dollar amount: ${JSON.stringify(input)}`)
  }

  const [, minus, wholeDollars = '0', centsDigits] = match
  const amount = Number(wholeDollars.replace(/,/g, '')) * 100 + Number(centsDigits ?? 0)

  return cents(minus === undefined ? amount : -amount)
}

/** Cents as the plain decimal string a price input wants — `formatCents` is for
 * reading, this is for editing, so no `$` and no thousands separators. */
export function dollarsInputValue(amount: Cents): string {
  const absAmount = Math.abs(amount)

  return `${Math.floor(absAmount / 100)}.${String(absAmount % 100).padStart(2, '0')}`
}
