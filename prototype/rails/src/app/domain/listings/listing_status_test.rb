# Runs without Rails: ruby -Iapp app/domain/listings/listing_status_test.rb
require "minitest/autorun"
require_relative "listing_status"

module Domain
  module Listings
    class ListingStatusTest < Minitest::Test
      def test_all_names_every_status
        assert_equal %w[draft for_sale sold archived], ListingStatus::ALL
      end

      def test_a_draft_goes_on_sale
        assert ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::FOR_SALE)
      end

      def test_a_listing_on_sale_sells
        assert ListingStatus.can_transition?(ListingStatus::FOR_SALE, ListingStatus::SOLD)
      end

      def test_a_sold_listing_returns_to_the_storefront
        assert ListingStatus.can_transition?(ListingStatus::SOLD, ListingStatus::FOR_SALE)
      end

      def test_a_draft_and_a_listing_on_sale_both_archive
        assert ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::ARCHIVED)
        assert ListingStatus.can_transition?(ListingStatus::FOR_SALE, ListingStatus::ARCHIVED)
      end

      def test_an_archived_listing_goes_nowhere
        assert_empty ListingStatus::TRANSITIONS.fetch(ListingStatus::ARCHIVED)
      end

      def test_a_sold_listing_does_not_archive
        refute ListingStatus.can_transition?(ListingStatus::SOLD, ListingStatus::ARCHIVED)
      end

      def test_a_draft_does_not_sell
        refute ListingStatus.can_transition?(ListingStatus::DRAFT, ListingStatus::SOLD)
      end

      def test_transition_returns_the_next_status
        assert_equal ListingStatus::SOLD, ListingStatus.transition(ListingStatus::FOR_SALE, ListingStatus::SOLD)
      end

      def test_transition_refuses_a_move_the_table_does_not_allow
        error = assert_raises(TransitionError) { ListingStatus.transition(ListingStatus::DRAFT, ListingStatus::SOLD) }
        assert_equal "A listing cannot move from draft to sold.", error.message
      end

      def test_transition_refuses_a_status_it_does_not_know
        assert_raises(TransitionError) { ListingStatus.transition("wishlisted", ListingStatus::SOLD) }
      end

      def test_every_status_has_a_transition_list
        assert_equal ListingStatus::ALL.sort, ListingStatus::TRANSITIONS.keys.sort
      end
    end
  end
end
