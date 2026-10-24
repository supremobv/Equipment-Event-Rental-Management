@props(['amount'=>0,'currency'=>true])<span class="money">{{ $currency ? ($business?->currency ?? 'PHP').' ' : '' }}{{ \App\Support\Money::format($amount) }}</span>
