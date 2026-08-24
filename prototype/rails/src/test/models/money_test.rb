require "test_helper"

class MoneyTest < ActiveSupport::TestCase
  test "from cents keeps the integer amount" do
    assert_equal 1234, Money.from_cents(1234).cents
  end

  test "from cents rejects a value that is not a whole number of cents" do
    assert_raises(ArgumentError) { Money.from_cents("12.34") }
  end

  test "zero is nothing, and the seed a sum of amounts starts from" do
    assert_equal 0, Money.zero.cents
    assert_equal 1500, [ Money.from_cents(500), Money.from_cents(1000) ].sum(Money.zero).cents
  end

  test "amounts with the same cents are equal" do
    assert_equal Money.from_cents(500), Money.from_cents(500)
  end

  test "from dollars parses dollars and cents" do
    assert_equal 1234, Money.from_dollars("12.34").cents
  end

  test "from dollars parses a whole dollar amount" do
    assert_equal 1200, Money.from_dollars("12").cents
  end

  test "from dollars parses thousands separators" do
    assert_equal 123456, Money.from_dollars("1,234.56").cents
  end

  test "from dollars parses a leading currency symbol" do
    assert_equal 1234, Money.from_dollars("$12.34").cents
  end

  test "from dollars parses a negative amount" do
    assert_equal(-1234, Money.from_dollars("-12.34").cents)
  end

  test "from dollars ignores surrounding whitespace" do
    assert_equal 1234, Money.from_dollars("  12.34  ").cents
  end

  test "from dollars rejects text that is not an amount" do
    assert_raises(ArgumentError) { Money.from_dollars("twelve dollars") }
  end

  test "from dollars rejects a fraction of a cent" do
    assert_raises(ArgumentError) { Money.from_dollars("12.345") }
  end

  test "addition sums the cents" do
    assert_equal Money.from_cents(1500), Money.from_cents(1000) + Money.from_cents(500)
  end

  test "multiplication scales by a count" do
    assert_equal Money.from_cents(3000), Money.from_cents(1000) * 3
  end

  test "multiplication rejects a fractional count" do
    assert_raises(ArgumentError) { Money.from_cents(1000) * 1.5 }
  end

  test "percent takes a whole share" do
    assert_equal Money.from_cents(100), Money.from_cents(1000).percent(10)
  end

  test "percent rounds a half cent away from zero" do
    assert_equal Money.from_cents(101), Money.from_cents(1005).percent(10)
  end

  test "percent rounds a negative half cent away from zero" do
    assert_equal Money.from_cents(-101), Money.from_cents(-1005).percent(10)
  end

  test "percent rounds down below a half cent" do
    assert_equal Money.from_cents(33), Money.from_cents(333).percent(10)
  end

  test "format writes dollars and cents" do
    assert_equal "$12.34", Money.from_cents(1234).format
  end

  test "format pads cents under a dime" do
    assert_equal "$0.05", Money.from_cents(5).format
  end

  test "format separates thousands" do
    assert_equal "$1,234.56", Money.from_cents(123456).format
  end

  test "format separates millions" do
    assert_equal "$1,000,000.00", Money.from_cents(100_000_000).format
  end

  test "format puts the sign before the currency symbol" do
    assert_equal "-$12.34", Money.from_cents(-1234).format
  end
end
