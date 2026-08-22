module Customers
  class MergeAnonymousCustomer
    attr_reader :owned_tables

    def initialize(owned_tables: Domain::Customers::OwnedTables::ALL)
      @owned_tables = owned_tables
    end

    # Returns the verified customer, now holding both histories.
    def call(anonymous:, verified:)
      ActiveRecord::Base.transaction do
        owned_tables.each { |table, column| re_point(table, column, from: anonymous.id, to: verified.id) }

        # The anonymous row survives the merge so a cookie still holding its id
        # resolves forward instead of starting the visitor over.
        CustomerMerge.create!(anonymous_customer: anonymous, customer: verified)
      end

      verified
    end

    private

    def re_point(table, column, from:, to:)
      # The commerce tables arrive on their own schedule; a merge run before
      # they exist still has to write its trail.
      return unless connection.table_exists?(table) && connection.column_exists?(table, column)

      connection.update(
        ActiveRecord::Base.sanitize_sql_array(["UPDATE #{table} SET #{column} = ? WHERE #{column} = ?", to, from])
      )
    end

    def connection
      ActiveRecord::Base.connection
    end
  end
end
