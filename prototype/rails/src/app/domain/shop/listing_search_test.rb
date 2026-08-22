require "minitest/autorun"
require_relative "listing_search"

class ListingSearchTest < Minitest::Test
  ListingSearch = Domain::Shop::ListingSearch

  def test_it_reads_a_term_and_a_medium
    search = ListingSearch.from_input(term: "harbour", medium: "Oil on canvas")

    assert search.term?
    assert search.medium?
    assert_equal "harbour", search.term
    assert_equal "Oil on canvas", search.medium
  end

  def test_it_treats_blank_input_as_no_filter
    search = ListingSearch.from_input(term: "   ", medium: nil)

    refute search.term?
    refute search.medium?
    assert_nil search.term
    assert_nil search.medium
  end

  def test_it_trims_what_the_visitor_typed
    assert_equal "dusk", ListingSearch.from_input(term: "  dusk  ", medium: nil).term
  end

  def test_it_wraps_the_term_in_wildcards
    assert_equal "%harbour%", ListingSearch.from_input(term: "harbour", medium: nil).like_pattern
  end

  def test_it_drops_wildcards_the_visitor_typed
    assert_equal "%a b%", ListingSearch.from_input(term: "a%_b", medium: nil).like_pattern
  end

  def test_it_refuses_a_pattern_without_a_term
    search = ListingSearch.from_input(term: nil, medium: "Oil")

    assert_raises(ArgumentError) { search.like_pattern }
  end
end
