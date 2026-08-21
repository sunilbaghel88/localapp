@extends('layouts.partner')

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
            <div class="text-xs uppercase tracking-wider text-gray-500">Available points</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ (int) ($user->reward_points ?? 0) }}</div>
            <div class="mt-2 text-xs uppercase tracking-wider text-gray-500">Total points Earned</div>
            <div class="mt-1 text-xl font-semibold text-gray-900">{{ (int) $totalGranted }}</div>
        </div>
    </div>

    <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
        <div class="border-b border-gray-200 p-5">
            <h2 class="text-lg font-semibold text-gray-900">Redeem points</h2>
            <p class="mt-1 text-sm text-gray-600">
                Submit a request. The shop owner will approve/reject after giving cash or gift.
            </p>

            @if ($shops->isEmpty())
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    You are not attached to any shop yet. Ask a shop owner to link you first.
                </div>
            @else
                <form method="POST" action="{{ route('partner.rewards.redeem.store') }}" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shop</label>
                        <select name="shop_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                            <option value="">Select shop</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((int) old('shop_id') === (int) $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                        @error('shop_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Points</label>
                        <input type="number" min="1" name="requested_points" value="{{ old('requested_points') }}"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                        @error('requested_points')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="redemption_type" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                            <option value="cash" @selected(old('redemption_type') === 'cash')>Cash</option>
                            <option value="gift" @selected(old('redemption_type') === 'gift')>Gift</option>
                        </select>
                        @error('redemption_type')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            Send request
                        </button>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                        <textarea name="note" rows="2" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old('note') }}</textarea>
                        @error('note')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </form>
            @endif
        </div>

        <div class="border-b border-gray-200">
            <div class="px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Redemption requests</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($redemptions as $redemption)
                            <tr>
                                <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    {{ $redemption->created_at?->format('d M Y, h:i A') ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-900 whitespace-nowrap">
                                    {{ $redemption->shop?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-sm font-semibold text-gray-900 whitespace-nowrap">
                                    -{{ (int) $redemption->requested_points }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700 whitespace-nowrap uppercase">
                                    {{ $redemption->redemption_type }}
                                </td>
                                <td class="px-5 py-3 text-sm whitespace-nowrap">
                                    @php
                                        $statusColor = match ($redemption->status) {
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            default => 'bg-amber-100 text-amber-800',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                                        {{ ucfirst($redemption->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-700">
                                    @if ($redemption->status === 'rejected' && $redemption->rejection_reason)
                                        {{ $redemption->rejection_reason }}
                                    @elseif ($redemption->note)
                                        {{ $redemption->note }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-sm text-gray-600 text-center">
                                    No redemption requests yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($redemptions, 'links'))
                <div class="px-5 py-4 border-t border-gray-200">
                    {{ $redemptions->links() }}
                </div>
            @endif
        </div>

        <div class="px-5 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Reward points grants</h2>
        </div>
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

