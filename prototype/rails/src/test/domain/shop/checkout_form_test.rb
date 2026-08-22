require "test_helper"

class CheckoutFormTest < ActiveSupport::TestCase
  CheckoutForm = Domain::Shop::CheckoutForm

  test "a filled form is complete" do
    form = CheckoutForm.from_input(email: " Ada@Example.Test ", shipping: shipping)

    assert form.complete?
    assert_empty form.missing_parts
    assert_equal "Ada@Example.Test", form.email
    assert_equal "London", form.shipping.city
  end

  test "the second address line is optional" do
    form = CheckoutForm.from_input(email: "ada@example.test", shipping: shipping(line2: ""))

    assert form.complete?
    assert_nil form.shipping.line2
  end

  test "a blank shipping part is missing" do
    form = CheckoutForm.from_input(email: "ada@example.test", shipping: shipping(city: "   ", country: nil))

    refute form.complete?
    assert_equal %i[city country], form.missing_parts
  end

  test "an address that is not an email is incomplete" do
    refute CheckoutForm.from_input(email: "ada", shipping: shipping).complete?
  end

  test "an absent shipping part is missing" do
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
