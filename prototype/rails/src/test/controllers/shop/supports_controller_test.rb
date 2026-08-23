require "test_helper"

module Shop
  class SupportsControllerTest < ActionDispatch::IntegrationTest
    test "a visitor who has given no address still reaches the desk" do
      desk = create_admin

      post shop_support_path

      conversation = Conversation.sole
      assert_redirected_to shop_conversation_path(conversation)
      assert_equal desk, conversation.admin
      assert_predicate conversation.customer, :anonymous?
    end

    test "the button opens the thread with the operator on duty" do
      sign_in_as_customer(email: "buyer@example.com")
      desk = create_admin
      create_admin(email: "later@example.test")

      post shop_support_path

      conversation = Conversation.sole
      assert_redirected_to shop_conversation_path(conversation)
      assert_equal desk, conversation.admin
      assert_equal visiting_customer, conversation.customer
    end

    test "pressing it again reaches the thread already open" do
      sign_in_as_customer(email: "buyer@example.com")
      create_admin

      post shop_support_path
      post shop_support_path

      assert_equal 1, Conversation.count
    end

    test "with nobody on the desk the button says so and opens nothing" do
      sign_in_as_customer(email: "buyer@example.com")

      post shop_support_path

      assert_redirected_to root_path
      assert_equal "Nobody is on the support desk yet.", flash[:alert]
      assert_empty Conversation.all
    end

    test "the account page carries the button" do
      sign_in_as_customer(email: "buyer@example.com")

      get shop_account_path

      assert_select "form[action=?][method=post] button", shop_support_path, text: "Contact support"
    end
  end
end
