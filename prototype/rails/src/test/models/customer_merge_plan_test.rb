require "test_helper"

class CustomerMergePlanTest < ActiveSupport::TestCase
  test "quantities of the same listing on both sides are summed" do
    lines = CustomerMergePlan.fold_cart_lines({ 1 => 2 }, { 1 => 3 }, {})

    assert_equal [ CustomerMergePlan::CartLine.new(listing_id: 1, quantity: 5) ], lines
  end

  test "a summed quantity over stock is clamped to what is left" do
    lines = CustomerMergePlan.fold_cart_lines({ 1 => 2 }, { 1 => 3 }, { 1 => 4 })

    assert_equal [ CustomerMergePlan::CartLine.new(listing_id: 1, quantity: 4) ], lines
  end

  test "a line clamped to zero stock is dropped" do
    lines = CustomerMergePlan.fold_cart_lines({ 1 => 2 }, { 1 => 3 }, { 1 => 0 })

    assert_empty lines
  end

  test "a line the stock table has no cap for is kept in full" do
    lines = CustomerMergePlan.fold_cart_lines({}, { 1 => 3 }, {})

    assert_equal [ CustomerMergePlan::CartLine.new(listing_id: 1, quantity: 3) ], lines
  end

  test "lines on only one side are both kept, unclamped by each other" do
    lines = CustomerMergePlan.fold_cart_lines({ 1 => 1 }, { 2 => 1 }, { 1 => 10, 2 => 10 })

    assert_equal [ 1, 2 ], lines.map(&:listing_id).sort
  end

  test "nothing on either side folds to nothing" do
    assert_empty CustomerMergePlan.fold_cart_lines({}, {}, {})
  end

  test "an anonymous favorite the verified customer does not hold moves" do
    plan = CustomerMergePlan.partition_favorites([ 1 ], [ 2 ])

    assert_equal [ 2 ], plan[:move]
    assert_empty plan[:drop]
  end

  test "an anonymous favorite duplicating the verified customer's own drops instead of moving" do
    plan = CustomerMergePlan.partition_favorites([ 1 ], [ 1 ])

    assert_empty plan[:move]
    assert_equal [ 1 ], plan[:drop]
  end

  test "the anonymous side's own duplicate favorites collapse to one decision" do
    plan = CustomerMergePlan.partition_favorites([], [ 1, 1 ])

    assert_equal [ 1 ], plan[:move]
  end

  test "favorites the verified customer already holds and ones only the anonymous customer holds partition together" do
    plan = CustomerMergePlan.partition_favorites([ 1 ], [ 1, 2 ])

    assert_equal [ 2 ], plan[:move]
    assert_equal [ 1 ], plan[:drop]
  end
end
