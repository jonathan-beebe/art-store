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
  end

  get "account", to: "shop/account#show", as: :shop_account

  root "shop/storefront#show"
end
