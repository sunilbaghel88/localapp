@extends('layouts.electrician')

@section('title', 'Reward Points')

@section('content')
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Reward Points (order-wise)</h1>
            <p class="mt-1 text-sm text-gray-600">
                This is your reward points history grouped by order grant entries.
            </p>
        </div>
        <div class="rounded-xl bg-white border border-gray-200 px-5 py-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Current points</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ (int) ($user->reward_points ?? 0) }}</div>
            <div class="mt-2 text-xs uppercase tracking-wider text-gray-500">Total granted (audit)</div>
            <div class="mt-1 text-xl font-semibold text-gray-900">{{ (int) $totalGranted }}</div>
        </div>
    </div>

    <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Granted by</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($grants as $grant)
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
                            <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ $grant->grantedBy?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-700">
                                {{ $grant->notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-sm text-gray-600 text-center">
                                No reward points granted yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($grants, 'links'))
            <div class="px-5 py-4 border-t border-gray-200">
                {{ $grants->links() }}
            </div>
        @endif
    </div>
@endsection

