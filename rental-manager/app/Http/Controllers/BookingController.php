<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Equipment;
use App\Services\RentalService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:quote,confirmed,out,returned,cancelled']);
        $bookings = Booking::with('customer', 'payments')->when($r->q, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$r->q.'%')->orWhere('event_name', 'like', '%'.$r->q.'%')->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'))))->when($r->status, fn ($q) => $q->where('status', $r->status))->latest()->paginate(15)->withQueryString();

        return view('bookings.index', compact('bookings'));
    }

    public function create()
    {
        return $this->form(new Booking);
    }

    public function edit(Booking $booking)
    {
        abort_unless($booking->status === 'quote', 409, 'Only quotes can be edited.');
        $booking->load('items');

        return $this->form($booking);
    }

    private function form(Booking $booking)
    {
        return view('bookings.form', ['booking' => $booking, 'customers' => Customer::orderBy('name')->get(), 'equipment' => Equipment::where('active', true)->orderBy('name')->get()]);
    }

    private function data(Request $r): array
    {
        $data = $r->validate(['customer_id' => 'required|exists:customers,id', 'event_name' => 'required|string|max:150', 'venue' => 'nullable|string|max:255', 'fulfillment' => 'required|in:pickup,delivery', 'starts_on' => 'required|date_format:Y-m-d|after_or_equal:'.today()->subYear()->format('Y-m-d'), 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on|before_or_equal:'.now()->addYears(5)->format('Y-m-d'), 'notes' => 'nullable|string|max:5000', 'discount' => Money::rule(), 'delivery_fee' => Money::rule(), 'deposit_required' => Money::rule(), 'items' => 'required|array|min:1|max:50', 'items.*.equipment_id' => 'required|integer|distinct|exists:equipment,id', 'items.*.quantity' => 'required|integer|min:1|max:10000']);
        if (Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) > 365) {
            throw ValidationException::withMessages(['ends_on' => 'A single rental can cover up to 366 days.']);
        }

        return $data;
    }

    public function store(Request $r, RentalService $s)
    {
        $b = $s->saveQuote($this->data($r));

        return redirect()->route('bookings.show', $b)->with('success', 'Quote created. Review availability, then confirm the booking.');
    }

    public function update(Request $r, Booking $booking, RentalService $s)
    {
        $s->saveQuote($this->data($r), $booking);

        return redirect()->route('bookings.show', $booking)->with('success', 'Quote updated.');
    }

    public function show(Booking $booking)
    {
        $booking->load('customer', 'items.equipment', 'payments.user');

        return view('bookings.show', compact('booking'));
    }

    public function action(Request $r, Booking $booking, string $action, RentalService $s)
    {
        abort_unless(in_array($action, ['confirm', 'checkout', 'return', 'cancel']), 404);
        abort_unless($r->user()->allows(in_array($action, ['checkout', 'return']) ? 'operations' : 'bookings'), 403);
        $data = $r->validate(['return_fee' => ['nullable', ...Money::ruleWithoutRequired()], 'return_notes' => 'nullable|string|max:5000']);
        $s->transition($booking, $action, $data);

        return back()->with('success', 'Booking updated successfully.');
    }

    public function pay(Request $r, Booking $booking, RentalService $s)
    {
        $data = $r->validate(['request_key' => 'required|uuid', 'kind' => 'required|in:rental,refund,deposit,deposit_refund', 'amount' => Money::rule(), 'method' => 'required|in:cash,bank,card,ewallet', 'reference' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:1000', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today']);
        $s->payment($booking, $data);

        return back()->with('success', 'Payment recorded.');
    }

    public function print(Booking $booking)
    {
        $booking->load('customer', 'items', 'payments');

        return view('bookings.print', compact('booking'));
    }
}
