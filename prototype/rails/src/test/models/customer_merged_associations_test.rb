require "test_helper"

# The exhaustiveness check a customer merge lives or dies by: every table the
# schema gives a `customer_id` column is named in `Customer::MERGED_ASSOCIATIONS`
# (folded or re-pointed by `Customer#fold`) or in
# `Customer::LEFT_BEHIND_ASSOCIATIONS` (deliberately untouched, with the
# reason next to it). It reads `ActiveRecord::Base.connection`, not a
# hand-copied table list, so a migration that adds a new `customer_id` column
# fails this test until someone puts it in one list or the other.
class CustomerMergedAssociationsTest < ActiveSupport::TestCase
  test "every customer_id column in the schema is folded, re-pointed, or explicitly left behind" do
    unclassified = tables_with_a_customer_id_column - merged_tables - Customer::LEFT_BEHIND_ASSOCIATIONS.keys

    assert_empty unclassified,
      "#{unclassified.to_a} carry a customer_id column that Customer::MERGED_ASSOCIATIONS and " \
      "Customer::LEFT_BEHIND_ASSOCIATIONS both leave unclassified"
  end

  test "left-behind is reserved for tables that actually carry a customer_id column" do
    stale = Customer::LEFT_BEHIND_ASSOCIATIONS.keys - tables_with_a_customer_id_column.to_a

    assert_empty stale, "#{stale} are in Customer::LEFT_BEHIND_ASSOCIATIONS but have no customer_id column"
  end

  test "no table is both merged and explicitly left behind" do
    assert_empty merged_tables & Customer::LEFT_BEHIND_ASSOCIATIONS.keys
  end

  test "every reason left-behind is named against is present" do
    assert Customer::LEFT_BEHIND_ASSOCIATIONS.values.all?(&:present?)
  end

  private

  def tables_with_a_customer_id_column
    ActiveRecord::Base.connection.tables.select do |table|
      ActiveRecord::Base.connection.columns(table).any? { |column| column.name == "customer_id" }
    end.to_set
  end

  def merged_tables
    Customer::MERGED_ASSOCIATIONS.map { |association| Customer.reflect_on_association(association).table_name }.to_set
  end
end
