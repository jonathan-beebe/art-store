module Domain
  module Orders
    ShippingAddress = Data.define(:name, :line1, :line2, :city, :region, :postal_code, :country)
  end
end
