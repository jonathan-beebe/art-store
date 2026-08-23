/**
 * Integer cents — the only representation of money in the system. Nothing
 * divides until `percentOfCents`, and nothing renders until `formatCents`.
 */
export type Cents = number

function assertIntegerAmount(value: number, label: string): void {
  if (!Number.isInteger(value)) {
    throw new Error(`${label} must be an integer number of cents, got ${value}`)
  }
}

export function addCents(a: Cents, b: Cents): Cents {
  assertIntegerAmount(a, 'addCents: a')
  assertIntegerAmount(b, 'addCents: b')
  return a + b
}

export function multiplyCents(amount: Cents, factor: number): Cents {
  assertIntegerAmount(amount, 'multiplyCents: amount')
  // factor is a quantity (order line items, not a percentage or share), so a
  // fractional value is always a caller mistake.
  assertIntegerAmount(factor, 'multiplyCents: factor')
  return amount * factor
}

export function percentOfCents(amount: Cents, percent: number): Cents {
  assertIntegerAmount(amount, 'percentOfCents: amount')
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
  return signed === 0 ? 0 : signed
}

/** Groups a non-negative whole-dollar string with commas every three digits. */
function withThousandsSeparators(wholeDollars: string): string {
  return wholeDollars.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

export function formatCents(amount: Cents): string {
  assertIntegerAmount(amount, 'formatCents: amount')

  const sign = amount < 0 ? '-' : ''
  const absAmount = Math.abs(amount)
  const dollars = Math.floor(absAmount / 100)
  const cents = absAmount % 100
  return `${sign}$${withThousandsSeparators(String(dollars))}.${String(cents).padStart(2, '0')}`
}

const DOLLAR_AMOUNT_PATTERN = /^(-)?\$?(\d{1,3}(?:,\d{3})*|\d+)(?:\.(\d{2}))?$/

export function parseDollars(input: string): Cents {
  const trimmed = input.trim()
  const match = DOLLAR_AMOUNT_PATTERN.exec(trimmed)
  if (match === null) {
    throw new Error(`parseDollars: not a dollar amount: ${JSON.stringify(input)}`)
  }

  const [, minus, wholeDollars = '0', centsDigits] = match
  const amount = Number(wholeDollars.replace(/,/g, '')) * 100 + Number(centsDigits ?? 0)
  return minus === undefined ? amount : -amount
}
