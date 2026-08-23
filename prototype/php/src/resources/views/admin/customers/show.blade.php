<x-layouts.admin :title="($customer->name ?? 'Customer #'.$customer->id).' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $customer->name ?? 'Customer #'.$customer->id }}</h1>
        <a href="{{ route('admin.customers.index') }}" class="ml-auto text-gray-700 underline">All customers</a>
    </div>

    <dl class="mt-4 rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Email</dt>
        <dd class="mt-1">{{ $customer->email ?? '—' }}</dd>
        <dt class="mt-3 text-gray-600">Joined</dt>
        <dd class="mt-1">{{ $customer->created_at?->format('M j, Y') }}</dd>
    </dl>

    <section aria-labelledby="standing-heading" class="mt-6 max-w-xl">
        <h2 id="standing-heading" class="font-semibold text-gray-700">Standing</h2>

        @if ($customer->activeBlock)
            <dl class="mt-2 rounded border border-red-300 bg-red-50 p-4 text-red-900">
                <dt class="font-semibold">Blocked</dt>
                <dd class="mt-1">{{ $customer->activeBlock->reason }}</dd>
                <dd class="mt-1 text-red-700">Since {{ $customer->activeBlock->created_at?->format('M j, Y g:ia') }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.customers.blocks.lift', $customer) }}" class="mt-2">
                @csrf
                <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Lift block</button>
            </form>
        @else
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">Not blocked.</p>

            <form method="POST" action="{{ route('admin.customers.blocks.store', $customer) }}"
                  class="mt-2 rounded border border-gray-300 bg-white p-4">
                @csrf

                <label for="reason" class="block font-medium text-gray-700">Reason</label>
                <input id="reason" name="reason" type="text" required maxlength="500" value="{{ old('reason') }}"
                       class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
                @error('reason')
                    <p class="mt-1 text-red-700">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Block</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="orders-heading" class="mt-6">
        <h2 id="orders-heading" class="font-semibold text-gray-700">Orders</h2>

        @if ($customer->orders->isEmpty())
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">No orders yet.</p>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Orders this customer placed</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Total</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Placed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($customer->orders as $order)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">#{{ $order->id }}</th>
                                <td class="px-4 py-2">{{ $order->status->label() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $order->total()->format() }}</td>
                                <td class="px-4 py-2">{{ $order->placed_at?->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
