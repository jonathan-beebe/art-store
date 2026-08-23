# The prototype's stand-in for a card processor: the number decides the answer,
# and nothing but its last four digits is ever kept.
class FakeCard
  APPROVED_NUMBER = "4242424242424242"

  DECLINED_NUMBERS = {
    "4000000000000002" => "generic_decline",
    "4000000000009995" => "insufficient_funds"
  }.freeze

  def initialize(number)
    @digits = number.to_s.gsub(/\D/, "")
  end

  def approved?
    @digits == APPROVED_NUMBER
  end

  def last_four
    @digits[-4..] || @digits
  end

  def decline_reason
    return nil if approved?

    DECLINED_NUMBERS.fetch(@digits, "invalid_card_number")
  end
end
