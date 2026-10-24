<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Maintenance;
use App\Models\Setting;
use App\Support\Audit;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalService
{
    public function available(Equipment $equipment, string $start, string $end, ?int $except = null): int
    {
        return $this->availabilityFor(collect([$equipment]), $start, $end, $except)[$equipment->id];
    }

    public function availabilityFor(Collection $equipment, string $start, string $end, ?int $except = null): Collection
    {
        $reservations = DB::table('booking_items')->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereIn('equipment_id', $equipment->pluck('id'))->when($except, fn ($q) => $q->where('bookings.id', '!=', $except))
            ->where(fn ($q) => $q->where('status', 'out')->orWhere(fn ($q) => $q->where('status', 'confirmed')->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)))
            ->get(['booking_items.equipment_id', 'booking_items.quantity', 'bookings.status', 'bookings.starts_on', 'bookings.ends_on'])->groupBy('equipment_id');
        $holds = Maintenance::whereIn('equipment_id', $equipment->pluck('id'))->whereNull('completed_at')->groupBy('equipment_id')->selectRaw('equipment_id, SUM(quantity) AS held')->pluck('held', 'equipment_id');

        return $equipment->mapWithKeys(fn ($item) => [$item->id => $this->calculateAvailability($item, $reservations->get($item->id, collect()), (int) $holds->get($item->id, 0), $start, $end)]);
    }

    private function calculateAvailability(Equipment $equipment, Collection $reservations, int $repair, string $start, string $end): int
    {
        // Sweep inclusive date ranges to find peak usage, rather than adding disjoint rentals.
        $events = [];
        $out = 0;
        foreach ($reservations as $r) {
            if ($r->status === 'out') {
                $out += $r->quantity;

                continue;
            }
            $first = max($start, $r->starts_on);
            $after = Carbon::parse(min($end, $r->ends_on))->addDay()->toDateString();
            $events[$first] = ($events[$first] ?? 0) + $r->quantity;
            $events[$after] = ($events[$after] ?? 0) - $r->quantity;
        }
        ksort($events);
        $running = 0;
        $peak = 0;
        foreach ($events as $change) {
            $running += $change;
            $peak = max($peak, $running);
        }
        $reserved = $out + $peak;

        return max(0, $equipment->quantity - $reserved - $repair);
    }

    public function saveQuote(array $data, ?Booking $booking = null): Booking
    {
        return DB::transaction(function () use ($data, $booking) {
            $settings = Setting::lockForUpdate()->firstOrFail();
            if ($booking) {
                $booking = Booking::lockForUpdate()->findOrFail($booking->id);
                $this->ensure($booking->status === 'quote', 'Only quotes can be edited.');
            }
            $days = (int) Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) + 1;
            $items = [];
            $subtotal = 0;
            foreach ($data['items'] as $row) {
                $e = Equipment::findOrFail($row['equipment_id']);
                $this->ensure((bool) $e->active, 'An item is archived. Select active equipment.');
                $total = $e->daily_rate * $row['quantity'] * $days;
                $subtotal += $total;
                $items[] = ['equipment_id' => $e->id, 'name' => $e->name, 'quantity' => $row['quantity'], 'daily_rate' => $e->daily_rate, 'days' => $days, 'total' => $total];
            }
            $this->ensure($subtotal <= 9999999900, 'The equipment subtotal exceeds the supported quote limit of 99,999,999.00. Split this into smaller bookings.');
            $discount = Money::cents($data['discount']);
            $this->ensure($discount <= $subtotal, 'Discount cannot exceed the equipment subtotal.');
            $delivery = Money::cents($data['delivery_fee']);
            $bps = $settings->tax_bps;
            $tax = intdiv(($subtotal - $discount + $delivery) * $bps + 5000, 10000);
            $values = collect($data)->only(['customer_id', 'event_name', 'venue', 'fulfillment', 'starts_on', 'ends_on', 'notes'])->all() + ['subtotal' => $subtotal, 'discount' => $discount, 'delivery_fee' => $delivery, 'tax_bps' => $bps, 'tax' => $tax, 'total' => $subtotal - $discount + $delivery + $tax, 'deposit_required' => Money::cents($data['deposit_required'])];
            if ($booking) {
                $booking->update($values);
                $booking->items()->delete();
            } else {
                $booking = Booking::create($values + ['created_by' => auth()->id(), 'number' => 'GR-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(6)))]);
            }
            $booking->items()->createMany($items);
            Audit::log('Quote saved', $booking->number);

            return $booking;
        }, 3);
    }

    public function transition(Booking $booking, string $action, array $data = []): void
    {
        DB::transaction(function () use ($booking, $action, $data) {
            $b = Booking::lockForUpdate()->findOrFail($booking->id);
            // Current reads avoid opening a stale MySQL REPEATABLE READ snapshot
            // before waiting for another operator's equipment reservation lock.
            $b->setRelation('items', $b->items()->lockForUpdate()->get());
            // The same equipment locks serialize reservations, checkout, stock edits and maintenance.
            $equipment = Equipment::whereIn('id', $b->items->pluck('equipment_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $b->load('payments');
            if (in_array($action, ['confirm', 'checkout'])) {
                $this->ensure($b->status === ($action === 'confirm' ? 'quote' : 'confirmed'), 'This booking has changed. Refresh and try again.');
                $this->ensure($b->ends_on->endOfDay()->isFuture(), 'The rental period has ended. Edit the quote dates before confirming.');
                $availability = $this->availabilityFor($equipment, $b->starts_on->toDateString(), $b->ends_on->toDateString(), $b->id);
                foreach ($b->items as $item) {
                    $e = $equipment[$item->equipment_id];
                    $this->ensure((bool) $e->active && $item->quantity <= $availability[$e->id], "Not enough available stock for {$e->name}. Review inventory and dates.");
                }
                if ($action === 'checkout') {
                    $this->ensure($b->starts_on->startOfDay()->lte(now()), 'Check-out is available on the rental start date.');
                    $this->ensure($b->depositHeld() >= $b->deposit_required, 'Collect the required security deposit before check-out.');
                }
                $b->update($action === 'confirm' ? ['status' => 'confirmed'] : ['status' => 'out', 'checked_out_at' => now()]);
            } elseif ($action === 'return') {
                $this->ensure($b->status === 'out', 'Only checked-out bookings can be returned.');
                $fee = Money::cents($data['return_fee'] ?? 0);
                $b->update(['status' => 'returned', 'returned_at' => now(), 'return_fee' => $fee, 'total' => $b->total + $fee, 'return_notes' => $data['return_notes'] ?? null]);
            } elseif ($action === 'cancel') {
                $this->ensure(in_array($b->status, ['quote', 'confirmed']), 'Only a quote or confirmed booking can be cancelled.');
                $b->update(['status' => 'cancelled']);
            }
            Audit::log('Booking '.$action, $b->number);
        }, 3);
    }

    public function payment(Booking $booking, array $data): void
    {
        DB::transaction(function () use ($booking, $data) {
            $b = Booking::lockForUpdate()->findOrFail($booking->id);
            $b->load('payments');
            $amount = Money::cents($data['amount']);
            $this->ensure($amount > 0, 'Enter an amount greater than zero.');
            if (! empty($data['request_key']) && $b->payments()->where('request_key', $data['request_key'])->exists()) {
                return;
            }
            $limit = match ($data['kind']) {
                'rental' => $b->balance(),'refund' => $b->netPaid(),'deposit' => max(0, $b->deposit_required - $b->depositHeld()),'deposit_refund' => $b->depositHeld()
            };
            if (in_array($data['kind'], ['rental', 'deposit'])) {
                $this->ensure(in_array($b->status, ['confirmed', 'out', 'returned']), 'Confirm the quote before collecting payments.');
            }
            if ($data['kind'] === 'deposit_refund') {
                $this->ensure(in_array($b->status, ['returned', 'cancelled']), 'Return or cancel the booking before refunding its security deposit.');
            }
            $this->ensure($amount <= $limit, 'This amount exceeds the outstanding balance or refundable amount.');
            $b->payments()->create(collect($data)->except('amount')->all() + ['amount' => $amount, 'user_id' => auth()->id()]);
            Audit::log('Payment '.$data['kind'], $b->number);
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['booking' => $message]);
        }
    }
}
