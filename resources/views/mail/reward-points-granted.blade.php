<x-mail::message>
# Reward points granted

Hello {{ $electricianName }},

@if($grantedByName)
**{{ $grantedByName }}** granted you **{{ $points }}** reward point{{ $points === 1 ? '' : 's' }} for the order below.
@else
You have been granted **{{ $points }}** reward point{{ $points === 1 ? '' : 's' }} for the order below.
@endif

Your current reward balance is **{{ number_format($newRewardBalance) }}** points.

@if($notes)
**Note from the shop**

{{ $notes }}

@endif
## Order #{{ $order->getKey() }}

<x-mail::panel>
**Shop:** {{ $order->shop?->name ?? '—' }}

**Customer:** {{ $order->user?->name ?? '—' }}

**Order status:** {{ $order->status }}

**Payment:** {{ $order->payment_status }}

**Order total:** {{ number_format((float) $order->grand_total, 2) }}
</x-mail::panel>

@if($order->items->isNotEmpty())
### Items

@foreach ($order->items as $item)
- {{ $item->name }} × {{ $item->quantity }} — {{ number_format((float) $item->total, 2) }}
@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
