# The identifier every domain table's primary key carries: three lowercase
# letters saying which table it belongs to, an underscore, and a 26-character
# Crockford base32 ULID.
#
#   ord_01J5X3M9A2K8YB7Q4R6T1V0WZE
#
# The ULID's leading 48 bits are the millisecond it was minted and the
# trailing 80 are random, so ids sort in the order they were made. Ids minted
# within one millisecond count up from that millisecond's random draw, which
# holds the order under a clock that stands still.
module PrefixedUlid
  DIGITS = "0123456789ABCDEFGHJKMNPQRSTVWXYZ".freeze
  LENGTH = 26
  RANDOM_BYTES = 10

  # 26 base32 digits hold 130 bits and a ULID is 128, so the leading digit
  # never passes 7.
  BODY = /[0-7][#{DIGITS}]{#{LENGTH - 1}}/

  @clock = nil
  @value = 0
  @mint = Mutex.new

  def self.generate(prefix, at: Time.current)
    "#{prefix}_#{encode(next_value(at.to_i * 1_000 + at.usec / 1_000))}"
  end

  # The id when the text carries this prefix and a well-formed ULID, nil when
  # it carries another prefix, no prefix, or a malformed ULID.
  def self.parse(text, prefix)
    text.to_s[/\A#{pattern(prefix)}\z/]
  end

  # The same rule as a route's segment constraints, so a request naming
  # another table's id never reaches a controller.
  def self.constraints(**prefixes)
    prefixes.transform_values { |prefix| pattern(prefix) }
  end

  private_class_method def self.pattern(prefix)
    /#{prefix}_#{BODY}/
  end

  private_class_method def self.next_value(milliseconds)
    @mint.synchronize do
      @value = milliseconds == @clock ? @value + 1 : (milliseconds << (RANDOM_BYTES * 8)) + random_bits
      @clock = milliseconds
      @value
    end
  end

  private_class_method def self.random_bits
    SecureRandom.random_bytes(RANDOM_BYTES).unpack1("H*").to_i(16)
  end

  private_class_method def self.encode(value)
    (LENGTH - 1).downto(0).map { |place| DIGITS[(value >> (place * 5)) & 31] }.join
  end
end
