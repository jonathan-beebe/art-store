require "minitest/autorun"
require_relative "checkout_form"

class CheckoutFormTest < Minitest::Test
  CheckoutForm = Domain::Shop::CheckoutForm

  def test_a_filled_form_is_complete
    form = CheckoutForm.from_input(email: " Ada@Example.Test ", shipping: shipping)

    assert form.complete?
    assert_empty form.missing_parts
    assert_equal "Ada@Example.Test", form.email
    assert_equal "London", form.shipping.city
  end

  def test_the_second_address_line_is_optional
    form = CheckoutForm.from_input(email: "ada@example.test", shipping: shipping(line2: ""))

    assert form.complete?
    assert_nil form.shipping.line2
  end

  def test_a_blank_shipping_part_is_missing
    form = CheckoutForm.from_input(email: "ada@example.test", shipping: shipping(city: "   ", country: nil))

    refute form.complete?
    assert_equal %i[city country], form.missing_parts
  end

  def test_an_address_that_is_not_an_email_is_incomplete
    refute CheckoutForm.from_input(email: "ada", shipping: shipping).complete?
  end

  def test_an_absent_shipping_part_is_missing
    form = CheckoutForm.from_input(email: "ada@example.test", shipping: {})

    refute form.complete?
    assert_equal CheckoutForm::REQUIRED_PARTS, form.missing_parts
  end

  private

  def shipping(**overrides)
    {
      name: "Ada Lovelace", line1: "12 Analytical Way", line2: "Flat 3",
      city: "London", region: "Greater London", postal_code: "EC1A 1BB", country: "GB"
    }.merge(overrides)
  end
end
