<x-layouts.admin title="Dashboard — Art Store admin">
    <h1 class="text-xl font-semibold">Dashboard</h1>

    <ul class="mt-4 space-y-2">
        <li>
            <a href="{{ route('admin.sellers.index') }}" class="text-gray-700 underline">Sellers</a>
        </li>
        <li>
            <a href="{{ route('admin.customers.index') }}" class="text-gray-700 underline">Customers</a>
        </li>
        <li>
            <a href="{{ route('admin.listings.index') }}" class="text-gray-700 underline">Listings</a>
        </li>
        <li>
            <a href="{{ route('admin.orders.index') }}" class="text-gray-700 underline">Orders</a>
        </li>
        <li>
            <a href="{{ route('admin.fulfillments.index') }}" class="text-gray-700 underline">Fulfillments</a>
        </li>
    </ul>
</x-layouts.admin>
