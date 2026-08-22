class CustomerMerge < ApplicationRecord
  belongs_to :anonymous_customer, class_name: "Customer"
  belongs_to :customer
end
