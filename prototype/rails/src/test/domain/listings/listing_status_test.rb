require "test_helper"

module Domain
  module Listings
    class ListingStatusTest < ActiveSupport::TestCase
      test "all names every status" do
        assert_equal %w[draft for_sale sold archived], ListingStatus::ALL
      end

      test "a draft goes on sale" do
        assert ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::FOR_SALE)
      end

      test "a listing on sale sells" do
        assert ListingStatus.can_transition?(ListingStatus::FOR_SALE, ListingStatus::SOLD)
      end

      test "a sold listing returns to the storefront" do
        assert ListingStatus.can_transition?(ListingStatus::SOLD, ListingStatus::FOR_SALE)
      end

      test "a draft and a listing on sale both archive" do
        assert ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::ARCHIVED)
        assert ListingStatus.can_transition?(ListingStatus::FOR_SALE, ListingStatus::ARCHIVED)
      end

      test "an archived listing goes nowhere" do
        assert_empty ListingStatus::TRANSITIONS.fetch(ListingStatus::ARCHIVED)
      end

      test "a sold listing does not archive" do
        refute ListingStatus.can_transition?(ListingStatus::SOLD, ListingStatus::ARCHIVED)
      end

      test "a draft does not sell" do
        refute ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::SOLD)
      end

      test "transition returns the next status" do
        assert_equal ListingStatus::SOLD, ListingStatus.transition(ListingStatus::FOR_SALE, ListingStatus::SOLD)
      end

      test "transition refuses a move the table does not allow" do
        error = assert_raises(TransitionError) { ListingStatus.transition(ListingStatus::DRAFT, ListingStatus::SOLD) }
        assert_equal "A listing cannot move from draft to sold.", error.message
      end

      test "transition refuses a status it does not know" do
        assert_raises(TransitionError) { ListingStatus.transition("wishlisted", ListingStatus::SOLD) }
      end

      test "every status has a transition list" do
        assert_equal ListingStatus::ALL.sort, ListingStatus::TRANSITIONS.keys.sort
      end
    end
  end
end
