@props(['refunds', 'caption'])

@if ($refunds->isEmpty())
    <x-admin.nothing>No refunds.</x-admin.nothing>
@else
    <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-gray-300 bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Refund</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Fulfillment</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Issued by</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Reason</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Issued</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($refunds as $refund)
                    <tr>
                        <th scope="row" class="px-4 py-2 font-normal">{{ $refund->id }}</th>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.fulfillments.show', $refund->fulfillment) }}" class="underline">{{ $refund->fulfillment->seller->displayName() }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $refund->issuerLabel() }}</td>
                        <td class="px-4 py-2">{{ $refund->reason }}</td>
                        <td class="px-4 py-2">{{ $refund->created_at?->format('M j, Y g:ia') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $refund->amount()->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
