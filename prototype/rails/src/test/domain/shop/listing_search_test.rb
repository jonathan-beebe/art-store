require "test_helper"

class ListingSearchTest < ActiveSupport::TestCase
  ListingSearch = Domain::Shop::ListingSearch

  test "it reads a term and a medium" do
    search = ListingSearch.from_input(term: "harbour", medium: "Oil on canvas")

    assert search.term?
    assert search.medium?
    assert_equal "harbour", search.term
    assert_equal "Oil on canvas", search.medium
  end

  test "it treats blank input as no filter" do
    search = ListingSearch.from_input(term: "   ", medium: nil)

    refute search.term?
    refute search.medium?
    assert_nil search.term
    assert_nil search.medium
  end

  test "it trims what the visitor typed" do
    assert_equal "dusk", ListingSearch.from_input(term: "  dusk  ", medium: nil).term
  end

  test "it wraps the term in wildcards" do
    assert_equal "%harbour%", ListingSearch.from_input(term: "harbour", medium: nil).like_pattern
  end

  test "it drops wildcards the visitor typed" do
    assert_equal "%a b%", ListingSearch.from_input(term: "a%_b", medium: nil).like_pattern
  end

  test "it refuses a pattern without a term" do
    search = ListingSearch.from_input(term: nil, medium: "Oil")

    assert_raises(ArgumentError) { search.like_pattern }
  end
end
