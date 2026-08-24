require "test_helper"

class PrefixedUlidTest < ActiveSupport::TestCase
  SHAPE = /\Aord_[0-7][0-9A-HJKMNP-TV-Z]{25}\z/
  RANDOM_HALF = (1 << 80) - 1

  test "an id is a prefix, an underscore and 26 base32 digits" do
    id = PrefixedUlid.generate(:ord)

    assert_match SHAPE, id
    assert_equal 30, id.length
  end

  test "the leading digits are the millisecond the caller's clock reads" do
    at = Time.utc(2026, 8, 23, 18, 30, 15, 250_000)

    minted = ulid_of(PrefixedUlid.generate(:ord, at: at))

    assert_equal at.to_i * 1_000 + 250, decode(minted) >> 80
  end

  test "ids minted on one clock reading sort in the order they were minted" do
    at = Time.utc(2026, 8, 23, 18, 30, 15)

    ids = Array.new(5) { PrefixedUlid.generate(:ord, at: at) }

    assert_equal ids, ids.sort
    assert_equal 5, ids.uniq.length
  end

  test "an id minted later sorts after one minted earlier" do
    earlier = PrefixedUlid.generate(:ord, at: Time.utc(2026, 8, 23, 18, 30, 15))
    later = PrefixedUlid.generate(:ord, at: Time.utc(2026, 8, 23, 18, 30, 16))

    assert later > earlier
  end

  test "each millisecond draws its own random bits" do
    at = Time.utc(2026, 8, 23, 18, 30, 15)

    first = decode(ulid_of(PrefixedUlid.generate(:ord, at: at)))
    second = decode(ulid_of(PrefixedUlid.generate(:ord, at: at + 0.001)))

    assert_not_equal first & RANDOM_HALF, second & RANDOM_HALF
  end

  test "parsing returns the id it was given" do
    id = PrefixedUlid.generate(:cus)

    assert_equal id, PrefixedUlid.parse(id, :cus)
  end

  test "parsing refuses another table's prefix" do
    assert_nil PrefixedUlid.parse(PrefixedUlid.generate(:ord), :cus)
  end

  test "parsing refuses an id with no prefix" do
    assert_nil PrefixedUlid.parse("01J5X3M9A2K8YB7Q4R6T1V0WZE", :ord)
  end

  test "parsing refuses a malformed ulid" do
    assert_nil PrefixedUlid.parse("ord_01J5X3M9A2K8YB7Q4R6T1V0WZ", :ord)
    assert_nil PrefixedUlid.parse("ord_01j5x3m9a2k8yb7q4r6t1v0wze", :ord)
    assert_nil PrefixedUlid.parse("ord_01J5X3M9A2K8YB7Q4R6T1V0WZU", :ord)
    assert_nil PrefixedUlid.parse("ord_81J5X3M9A2K8YB7Q4R6T1V0WZE", :ord)
  end

  test "parsing refuses text that only contains an id" do
    assert_nil PrefixedUlid.parse(" #{PrefixedUlid.generate(:ord)} ", :ord)
  end

  test "parsing refuses nothing at all" do
    assert_nil PrefixedUlid.parse(nil, :ord)
  end

  test "route constraints match their own table's ids only" do
    constraints = PrefixedUlid.constraints(id: :ord, customer_id: :cus)

    assert_match constraints[:id], PrefixedUlid.generate(:ord)
    assert_no_match constraints[:id], PrefixedUlid.generate(:cus)
    assert_match constraints[:customer_id], PrefixedUlid.generate(:cus)
  end

  private

  def ulid_of(id)
    id.split("_").last
  end

  def decode(ulid)
    ulid.each_char.reduce(0) { |value, digit| (value * 32) + PrefixedUlid::DIGITS.index(digit) }
  end
end
