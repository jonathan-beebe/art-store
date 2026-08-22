require "test_helper"

class PageTest < ActiveSupport::TestCase
  Page = Domain::Shop::Page

  def test_the_first_page_starts_at_the_beginning
    page = Page.of(requested: 1, size: 12, total_count: 30)

    assert_equal 0, page.offset
    assert_equal 12, page.limit
  end

  def test_a_later_page_skips_the_pages_before_it
    assert_equal 24, Page.of(requested: 3, size: 12, total_count: 30).offset
  end

  def test_it_counts_the_pages_the_collection_fills
    assert_equal 3, Page.of(requested: 1, size: 12, total_count: 25).count
    assert_equal 2, Page.of(requested: 1, size: 12, total_count: 24).count
  end

  def test_an_empty_collection_still_has_one_page
    page = Page.of(requested: 1, size: 12, total_count: 0)

    assert_equal 1, page.count
    assert page.first?
    assert page.last?
  end

  def test_a_page_past_the_end_lands_on_the_last_one
    assert_equal 3, Page.of(requested: 99, size: 12, total_count: 30).number
  end

  def test_a_page_before_the_start_lands_on_the_first_one
    assert_equal 1, Page.of(requested: 0, size: 12, total_count: 30).number
  end

  def test_input_that_is_not_a_number_lands_on_the_first_page
    assert_equal 1, Page.of(requested: nil, size: 12, total_count: 30).number
    assert_equal 1, Page.of(requested: "second", size: 12, total_count: 30).number
  end

  def test_a_middle_page_has_a_page_on_each_side
    page = Page.of(requested: 2, size: 12, total_count: 30)

    refute page.first?
    refute page.last?
    assert_equal 1, page.previous_number
    assert_equal 3, page.next_number
  end

  def test_it_refuses_a_size_that_holds_nothing
    assert_raises(ArgumentError) { Page.of(requested: 1, size: 0, total_count: 30) }
  end

  def test_it_refuses_a_negative_count
    assert_raises(ArgumentError) { Page.of(requested: 1, size: 12, total_count: -1) }
  end
end
