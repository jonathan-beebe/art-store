@extends('layouts.seller')

@section('title', 'Dashboard — Art Store seller')

@section('content')
    <h1 class="text-xl font-semibold">Dashboard</h1>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-gray-700">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-gray-300 bg-white p-4">
                    <dt class="text-gray-600">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="work-heading" class="mt-6">
        <h2 id="work-heading" class="font-semibold text-gray-700">Money and work</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Awaiting shipment</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $openFulfillments }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Held in escrow</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Available</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Unread notifications</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $unreadNotifications }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="notifications-heading" class="mt-6">
        <h2 id="notifications-heading" class="font-semibold text-gray-700">Recent notifications</h2>

        @if ($notifications->isEmpty())
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">Nothing yet.</p>
        @else
            <ul class="mt-2 divide-y divide-gray-200 rounded border border-gray-300 bg-white">
                @foreach ($notifications as $notification)
                    <li class="p-4">
                        <p class="font-medium">{{ $notification->subject }}</p>
                        <p class="mt-1 text-gray-600">{{ $notification->body }}</p>
                        <p class="mt-1 text-gray-500">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                    </li>
                @endforeach
            </ul>

            <p class="mt-2"><a href="{{ route('seller.notifications.index') }}" class="text-gray-700 underline">All notifications</a></p>
        @endif
    </section>
@endsection
