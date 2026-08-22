require "test_helper"

class PageTest < ActiveSupport::TestCase
  Page = Domain::Shop::Page

  test "the first page starts at the beginning" do
    page = Page.of(requested: 1, size: 12, total_count: 30)

    assert_equal 0, page.offset
    assert_equal 12, page.limit
  end

  test "a later page skips the pages before it" do
    assert_equal 24, Page.of(requested: 3, size: 12, total_count: 30).offset
  end

  test "it counts the pages the collection fills" do
    assert_equal 3, Page.of(requested: 1, size: 12, total_count: 25).count
    assert_equal 2, Page.of(requested: 1, size: 12, total_count: 24).count
  end

  test "an empty collection still has one page" do
    page = Page.of(requested: 1, size: 12, total_count: 0)

    assert_equal 1, page.count
    assert page.first?
    assert page.last?
  end

  test "a page past the end lands on the last one" do
    assert_equal 3, Page.of(requested: 99, size: 12, total_count: 30).number
  end

  test "a page before the start lands on the first one" do
    assert_equal 1, Page.of(requested: 0, size: 12, total_count: 30).number
  end

  test "input that is not a number lands on the first page" do
    assert_equal 1, Page.of(requested: nil, size: 12, total_count: 30).number
    assert_equal 1, Page.of(requested: "second", size: 12, total_count: 30).number
  end

  test "a middle page has a page on each side" do
    page = Page.of(requested: 2, size: 12, total_count: 30)

    refute page.first?
    refute page.last?
    assert_equal 1, page.previous_number
    assert_equal 3, page.next_number
  end

  test "it refuses a size that holds nothing" do
    assert_raises(ArgumentError) { Page.of(requested: 1, size: 0, total_count: 30) }
  end

  test "it refuses a negative count" do
    assert_raises(ArgumentError) { Page.of(requested: 1, size: 12, total_count: -1) }
  end
end
