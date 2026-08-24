Rails.application.routes.draw do
  # Reveal health status on /up that returns 200 if the app boots with no exceptions, otherwise 500.
  # Can be used by load balancers and uptime monitors to verify that the app is live.
  get "up" => "rails/health#show", as: :rails_health_check

  scope module: :auth do
    get "seller/login", to: "seller_sessions#new", as: :seller_login
    post "seller/login", to: "seller_sessions#create", as: :seller_send_magic_link
    post "seller/logout", to: "seller_sessions#destroy", as: :seller_logout

    get "login", to: "customer_sessions#new", as: :customer_login
    post "login", to: "customer_sessions#create", as: :customer_send_magic_link
    post "logout", to: "customer_sessions#destroy", as: :customer_logout

    get "admin/login", to: "admin_sessions#new", as: :admin_login
    post "admin/login", to: "admin_sessions#create", as: :admin_send_magic_link
    post "admin/logout", to: "admin_sessions#destroy", as: :admin_logout

    get "auth/magic/:token", to: "magic_links#show", as: :verify_magic_link
  end

  # Every id in a path names the table it came from, so a path carrying
  # another table's id matches no route and the site answers the 404 it
  # answers for an id nothing holds.
  namespace :seller do
    root "dashboard#show"

    resources :listings, only: %i[index show new create edit update],
      constraints: PrefixedUlid.constraints(id: :lst) do
      resource :status, only: :create, controller: "listing_statuses",
        constraints: PrefixedUlid.constraints(listing_id: :lst)
      resources :faqs, only: %i[index create update destroy],
        constraints: PrefixedUlid.constraints(listing_id: :lst, id: :faq)
    end

    # The portal's order page is one seller's slice of an order, so the id in
    # the path is the fulfillment's.
    resources :orders, only: %i[index show], constraints: PrefixedUlid.constraints(id: :ful) do
      resource :shipment, only: :create, controller: "shipments",
        constraints: PrefixedUlid.constraints(order_id: :ful)
      resource :conversation, only: :create, controller: "order_conversations",
        constraints: PrefixedUlid.constraints(order_id: :ful)
    end

    get "earnings", to: "earnings#show", as: :earnings
    post "earnings/payout", to: "payouts#create", as: :earnings_payout

    resources :notifications, only: :index do
      resource :read, only: :create, controller: "notification_reads",
        constraints: PrefixedUlid.constraints(notification_id: :ntf)
    end

    resources :conversations, path: "messages", only: %i[index show],
      constraints: PrefixedUlid.constraints(id: :cnv) do
      resources :messages, only: :create, constraints: PrefixedUlid.constraints(conversation_id: :cnv)
    end

    resource :support, only: :create
  end

  namespace :admin do
    root "dashboard#show"

    resources :sellers, only: :show, constraints: PrefixedUlid.constraints(id: :sel) do
      resource :conversation, only: :create, controller: "seller_conversations",
        constraints: PrefixedUlid.constraints(seller_id: :sel)
    end

    resources :customers, only: :show, constraints: PrefixedUlid.constraints(id: :cus) do
      resource :conversation, only: :create, controller: "customer_conversations",
        constraints: PrefixedUlid.constraints(customer_id: :cus)
    end

    resources :conversations, path: "messages", only: %i[index show],
      constraints: PrefixedUlid.constraints(id: :cnv) do
      resources :messages, only: :create, constraints: PrefixedUlid.constraints(conversation_id: :cnv)
    end
  end

  namespace :shop, path: "" do
    get "art/:slug", to: "listings#show", as: :listing
    post "art/:slug/questions", to: "listing_questions#create", as: :listing_questions

    get "favorites", to: "favorites#index", as: :favorites
    post "art/:slug/favorite", to: "favorites#toggle", as: :toggle_favorite

    get "cart", to: "carts#show", as: :cart
    post "cart/:slug", to: "cart_items#create", as: :add_to_cart
    delete "cart/:slug", to: "cart_items#destroy", as: :remove_from_cart

    get "checkout", to: "checkouts#show", as: :checkout
    post "checkout", to: "checkouts#create", as: :place_order

    get "orders", to: "orders#index", as: :orders
    get "orders/:id", to: "orders#show", as: :order,
      constraints: PrefixedUlid.constraints(id: :ord)
    get "orders/:id/pay", to: "order_payments#show", as: :order_payment,
      constraints: PrefixedUlid.constraints(id: :ord)
    post "orders/:id/pay", to: "order_payments#create", as: :pay_order,
      constraints: PrefixedUlid.constraints(id: :ord)
    post "orders/:order_id/fulfillments/:id/delivered",
      to: "delivery_confirmations#create", as: :confirm_delivery,
      constraints: PrefixedUlid.constraints(order_id: :ord, id: :ful)
    post "orders/:order_id/fulfillments/:id/conversation",
      to: "fulfillment_conversations#create", as: :fulfillment_conversation,
      constraints: PrefixedUlid.constraints(order_id: :ord, id: :ful)

    get "account", to: "account#show", as: :account
    post "account/notifications/:id/read", to: "notification_reads#create", as: :read_notification,
      constraints: PrefixedUlid.constraints(id: :ntf)

    resources :conversations, path: "messages", only: %i[index show],
      constraints: PrefixedUlid.constraints(id: :cnv) do
      resources :messages, only: :create, constraints: PrefixedUlid.constraints(conversation_id: :cnv)
    end

    resource :support, only: :create
  end

  root "shop/storefront#show"
end
