require "test_helper"

module Domain
  class MoneyTest < ActiveSupport::TestCase
    def test_from_cents_keeps_the_integer_amount
      assert_equal 1234, Money.from_cents(1234).cents
    end

    def test_from_cents_rejects_a_value_that_is_not_a_whole_number_of_cents
      assert_raises(ArgumentError) { Money.from_cents("12.34") }
    end

    def test_amounts_with_the_same_cents_are_equal
      assert_equal Money.from_cents(500), Money.from_cents(500)
    end

    def test_from_dollars_parses_dollars_and_cents
      assert_equal 1234, Money.from_dollars("12.34").cents
    end

    def test_from_dollars_parses_a_whole_dollar_amount
      assert_equal 1200, Money.from_dollars("12").cents
    end

    def test_from_dollars_parses_thousands_separators
      assert_equal 123456, Money.from_dollars("1,234.56").cents
    end

    def test_from_dollars_parses_a_leading_currency_symbol
      assert_equal 1234, Money.from_dollars("$12.34").cents
    end

    def test_from_dollars_parses_a_negative_amount
      assert_equal(-1234, Money.from_dollars("-12.34").cents)
    end

    def test_from_dollars_ignores_surrounding_whitespace
      assert_equal 1234, Money.from_dollars("  12.34  ").cents
    end

    def test_from_dollars_rejects_text_that_is_not_an_amount
      assert_raises(ArgumentError) { Money.from_dollars("twelve dollars") }
    end

    def test_from_dollars_rejects_a_fraction_of_a_cent
      assert_raises(ArgumentError) { Money.from_dollars("12.345") }
    end

    def test_addition_sums_the_cents
      assert_equal Money.from_cents(1500), Money.from_cents(1000) + Money.from_cents(500)
    end

    def test_multiplication_scales_by_a_count
      assert_equal Money.from_cents(3000), Money.from_cents(1000) * 3
    end

    def test_multiplication_rejects_a_fractional_count
      assert_raises(ArgumentError) { Money.from_cents(1000) * 1.5 }
    end

    def test_percent_takes_a_whole_share
      assert_equal Money.from_cents(100), Money.from_cents(1000).percent(10)
    end

    def test_percent_rounds_a_half_cent_away_from_zero
      assert_equal Money.from_cents(101), Money.from_cents(1005).percent(10)
    end

    def test_percent_rounds_a_negative_half_cent_away_from_zero
      assert_equal Money.from_cents(-101), Money.from_cents(-1005).percent(10)
    end

    def test_percent_rounds_down_below_a_half_cent
      assert_equal Money.from_cents(33), Money.from_cents(333).percent(10)
    end

    def test_format_writes_dollars_and_cents
      assert_equal "$12.34", Money.from_cents(1234).format
    end

    def test_format_pads_cents_under_a_dime
      assert_equal "$0.05", Money.from_cents(5).format
    end

    def test_format_separates_thousands
      assert_equal "$1,234.56", Money.from_cents(123456).format
    end

    def test_format_separates_millions
      assert_equal "$1,000,000.00", Money.from_cents(100_000_000).format
    end

    def test_format_puts_the_sign_before_the_currency_symbol
      assert_equal "-$12.34", Money.from_cents(-1234).format
    end
  end
end
