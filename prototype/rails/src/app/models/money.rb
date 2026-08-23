# Every amount in the system — prices, subtotals, fees, ledger entries — is
# integer cents. Nothing divides until `percent`, and nothing renders until
# `format`.
Money = Data.define(:cents) do
  def self.from_cents(cents)
    raise ArgumentError, "cents must be a whole number: #{cents.inspect}" unless cents.is_a?(Integer)

    new(cents: cents)
  end

  def self.zero
    from_cents(0)
  end

  def self.from_dollars(text)
    pattern = /\A(?<sign>-)?\$?(?<dollars>\d{1,3}(?:,\d{3})*|\d+)(?:\.(?<cents>\d{2}))?\z/
    match = pattern.match(text.to_s.strip)
    raise ArgumentError, "not a dollar amount: #{text.inspect}" if match.nil?

    amount = match[:dollars].delete(",").to_i * 100 + match[:cents].to_i
    from_cents(match[:sign] ? -amount : amount)
  end

  def +(other)
    Money.from_cents(cents + other.cents)
  end

  def *(count)
    Money.from_cents(cents * count)
  end

  # Half a cent rounds away from zero, so the platform fee on a sale and the
  # fee on its reversal are the same amount.
  def percent(rate)
    scaled = cents * rate
    whole, remainder = scaled.abs.divmod(100)
    whole += 1 if remainder * 2 >= 100
    Money.from_cents(scaled.negative? ? -whole : whole)
  end

  def format
    ActiveSupport::NumberHelper.number_to_currency(cents / 100.0)
  end
end
