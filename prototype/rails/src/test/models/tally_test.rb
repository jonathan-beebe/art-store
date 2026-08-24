require "test_helper"

class TallyTest < ActiveSupport::TestCase
  STATUSES = %w[draft for_sale sold].freeze

  test "a key nothing counted still appears, at zero" do
    tallies = Tally.over(STATUSES, { "sold" => 2 })

    assert_equal({ "draft" => 0, "for_sale" => 0, "sold" => 2 }, tallies)
  end

  test "the keys keep the domain order, not the measured order" do
    tallies = Tally.over(STATUSES, { "sold" => 2, "draft" => 5 })

    assert_equal %w[draft for_sale sold], tallies.keys
  end

  test "every key is zero when nothing was counted" do
    assert_equal({ "draft" => 0, "for_sale" => 0, "sold" => 0 }, Tally.over(STATUSES, {}))
  end
end
