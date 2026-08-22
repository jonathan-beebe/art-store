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

    get "auth/magic/:token", to: "magic_links#show", as: :verify_magic_link
  end

  namespace :seller do
    root "dashboard#show"

    resources :listings, only: %i[index show new create edit update] do
      resource :status, only: :create, controller: "listing_statuses"
    end

    resources :orders, only: %i[index show] do
      resource :shipment, only: :create, controller: "shipments"
    end

    get "earnings", to: "earnings#show", as: :earnings
    post "earnings/payout", to: "payouts#create", as: :earnings_payout

    resources :notifications, only: :index do
      resource :read, only: :create, controller: "notification_reads"
    end
  end

  namespace :shop, path: "" do
    get "art/:slug", to: "listings#show", as: :listing

    get "favorites", to: "favorites#index", as: :favorites
    post "art/:slug/favorite", to: "favorites#toggle", as: :toggle_favorite

    get "cart", to: "carts#show", as: :cart
    post "cart/:slug", to: "carts#add", as: :add_to_cart
    delete "cart/:slug", to: "carts#remove", as: :remove_from_cart

    get "checkout", to: "checkouts#show", as: :checkout
    post "checkout", to: "checkouts#create", as: :place_order

    get "orders", to: "orders#index", as: :orders
    get "orders/:id", to: "orders#show", as: :order
    get "orders/:id/pay", to: "order_payments#show", as: :order_payment
    post "orders/:id/pay", to: "order_payments#create", as: :pay_order
    post "orders/:order_id/fulfillments/:id/delivered",
      to: "delivery_confirmations#create", as: :confirm_delivery

    get "account", to: "account#show", as: :account
    post "account/notifications/:id/read", to: "notifications#update", as: :read_notification
  end

  root "shop/storefront#show"
end
