require "test_helper"

module Customers
  class MergeAnonymousCustomerTest < ActiveSupport::TestCase
    # The commerce tables carry columns this ticket knows nothing about, so the
    # table-driven re-pointing is proven against a table the test owns outright.
    PROBE_TABLE = "identity_merge_probes".freeze
    PROBE_TABLES = { PROBE_TABLE => "customer_id" }.freeze

    setup do
      @anonymous = create_anonymous_customer
      @verified = create_verified_customer
    end

    teardown do
      connection.drop_table(PROBE_TABLE, if_exists: true)
    end

    test "it re-points the rows of a table the customer owns" do
      bystander = create_anonymous_customer
      create_probe_table
      insert_probe_rows(@anonymous.id, bystander.id)

      merge(owned_tables: PROBE_TABLES)

      assert_equal [@verified.id, bystander.id], probe_customer_ids
    end

    test "it skips a table the schema does not have yet" do
      merge(owned_tables: PROBE_TABLES)

      assert_equal 1, CustomerMerge.count
    end

    test "it skips a table that lacks the customer column" do
      connection.create_table(PROBE_TABLE) { |table| table.string :note }

      merge(owned_tables: PROBE_TABLES)

      assert_equal 1, CustomerMerge.count
    end

    test "it records the merge so a stale cookie still resolves" do
      merge

      assert_equal @verified, CustomerMerge.sole.customer
      assert_equal @anonymous, CustomerMerge.sole.anonymous_customer
    end

    test "it returns the customer the history moved to" do
      assert_equal @verified, merge
    end

    test "it leaves the anonymous row in place for the merge trail" do
      merge

      assert Customer.exists?(@anonymous.id)
    end

    test "it re-points every commerce table the domain lists" do
      assert_equal Domain::Customers::OwnedTables::ALL, MergeAnonymousCustomer.new.owned_tables
    end

    private

    def merge(owned_tables: PROBE_TABLES)
      MergeAnonymousCustomer.new(owned_tables: owned_tables).call(anonymous: @anonymous, verified: @verified)
    end

    def connection
      ActiveRecord::Base.connection
    end

    def create_probe_table
      connection.create_table(PROBE_TABLE) { |table| table.integer :customer_id }
    end

    def insert_probe_rows(*customer_ids)
      customer_ids.each do |customer_id|
        connection.insert("INSERT INTO #{PROBE_TABLE} (customer_id) VALUES (#{customer_id})")
      end
    end

    def probe_customer_ids
      connection.select_values("SELECT customer_id FROM #{PROBE_TABLE} ORDER BY id")
    end
  end
end
