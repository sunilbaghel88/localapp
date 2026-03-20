@extends('layouts.electrician')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-600">
            Welcome, {{ $user->name }}. Here’s your reward points summary.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="rounded-xl bg-white border border-gray-200 p-4">
            <div class="text-sm text-gray-600">Current reward points</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ (int) ($user->reward_points ?? 0) }}</div>
        </div>
        <div class="rounded-xl bg-white border border-gray-200 p-4">
            <div class="text-sm text-gray-600">Total points granted (audit)</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ (int) $totalGranted }}</div>
        </div>
        <div class="rounded-xl bg-white border border-gray-200 p-4">
            <div class="text-sm text-gray-600">Quick links</div>
            <div class="mt-3">
                <a class="inline-flex items-center rounded-md bg-amber-500 px-4 py-1 mt-2 text-white text-sm font-medium hover:bg-amber-700"
                   href="{{ route('electrician.rewards.index') }}">
                    View reward points (order-wise)
                </a>
            </div>
        </div>
    </div>

    <div class="rounded-xl bg-white border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Recent reward grants</h2>
            <a href="{{ route('electrician.rewards.index') }}" class="text-sm text-amber-700 hover:text-amber-800">
                View all
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($recentGrants as $grant)
                        <tr>
                            <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ $grant->created_at?->format('d M Y, h:i A') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-900 whitespace-nowrap">
                                {{ $grant->order_id ? ('#' . $grant->order_id) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ $grant->order?->shop?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                +{{ (int) $grant->points }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700">
                                {{ $grant->notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-sm text-gray-600 text-center">
                                No reward points granted yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

