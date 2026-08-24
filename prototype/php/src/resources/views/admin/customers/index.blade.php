<x-layouts.admin title="Customers — Art Store admin">
    <h1 class="text-xl font-semibold">Customers</h1>

    @if ($customers->isEmpty())
        <p class="mt-4 rounded border border-gray-300 bg-white p-4 text-gray-600">No customers yet.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Every customer on the platform</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Customer</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Standing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($customers as $customer)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium underline">
                                    {{ $customer->name ?? 'Customer #'.$customer->id }}
                                </a>
                            </th>
                            <td class="px-4 py-2">{{ $customer->email ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($customer->activeBlock)
                                    <span class="text-red-700">Blocked</span>
                                @else
                                    <span class="text-gray-600">OK</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
