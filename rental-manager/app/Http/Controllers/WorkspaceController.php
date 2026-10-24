<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function dashboard()
    {
        $active = Booking::whereIn('status', ['confirmed', 'out'])->count();
        $out = Booking::where('status', 'out')->count();
        $overdue = Booking::where('status', 'out')->whereDate('ends_on', '<', today())->count();
        $monthPaid = $this->netPayments(today()->startOfMonth()->toDateString(), today()->toDateString());
        $upcoming = Booking::with('customer', 'payments')->whereIn('status', ['confirmed', 'out'])->orderBy('starts_on')->limit(6)->get();
        $quotes = Booking::where('status', 'quote')->count();
        $units = Equipment::where('active', true)->sum('quantity');
        $repair = Maintenance::whereNull('completed_at')->sum('quantity');
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = today()->startOfMonth()->subMonths($i);
            $trend[] = ['label' => $d->format('M'), 'total' => $this->netPayments($d->toDateString(), $d->copy()->endOfMonth()->toDateString())];
        }

        return view('dashboard', compact('active', 'out', 'overdue', 'monthPaid', 'upcoming', 'quotes', 'units', 'repair', 'trend'));
    }

    private function netPayments(string $from, string $to): int
    {
        return (int) Payment::whereBetween('paid_on', [$from, $to])->selectRaw("COALESCE(SUM(CASE WHEN kind='rental' THEN amount WHEN kind='refund' THEN -amount ELSE 0 END),0) AS total")->value('total');
    }

    public function calendar(Request $r)
    {
        $data = $r->validate(['month' => 'nullable|date_format:Y-m']);
        $date = Carbon::parse(($data['month'] ?? today()->format('Y-m')).'-01');
        $start = $date->copy()->startOfMonth()->startOfWeek();
        $end = $date->copy()->endOfMonth()->endOfWeek();
        $bookings = Booking::with('customer')->whereIn('status', ['confirmed', 'out'])->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->get();

        return view('calendar', compact('date', 'start', 'end', 'bookings'));
    }

    public function availability(Request $r, RentalService $s)
    {
        $data = $r->validate(['starts_on' => 'nullable|date_format:Y-m-d', 'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on']);
        $start = $data['starts_on'] ?? today()->toDateString();
        $end = $data['ends_on'] ?? $start;
        $equipment = Equipment::with('category')->where('active', true)->orderBy('name')->get();
        $available = $s->availabilityFor($equipment, $start, $end);
        if ($r->expectsJson() || $r->routeIs('api.availability')) {
            return response()->json(['starts_on' => $start, 'ends_on' => $end, 'data' => $equipment->map(fn ($e) => ['id' => $e->id, 'sku' => $e->sku, 'name' => $e->name, 'quantity' => $e->quantity, 'available' => $available[$e->id], 'daily_rate_cents' => $e->daily_rate])]);
        }

        return view('inventory.availability', compact('equipment', 'available', 'start', 'end'));
    }

    public function reports(Request $r)
    {
        $data = $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $payments = Payment::with('booking.customer', 'user')->whereBetween('paid_on', [$from, $to])->orderByDesc('paid_on')->orderByDesc('id')->paginate(25, ['*'], 'payments_page')->withQueryString();
        $paid = Payment::selectRaw("booking_id, SUM(CASE WHEN kind='rental' THEN amount WHEN kind='refund' THEN -amount ELSE 0 END) AS paid_total")->groupBy('booking_id');
        $balances = Booking::query()->leftJoinSub($paid, 'rental_payments', 'bookings.id', '=', 'rental_payments.booking_id')->whereIn('status', ['confirmed', 'out', 'returned'])->select('bookings.*')->selectRaw('CASE WHEN total > COALESCE(paid_total,0) THEN total - COALESCE(paid_total,0) ELSE 0 END AS outstanding_amount');
        $outstanding = DB::query()->fromSub((clone $balances)->toBase(), 'balance_totals')->sum('outstanding_amount');
        $bookings = $balances->whereRaw('total > COALESCE(paid_total,0)')->with('customer', 'payments')->orderByDesc('bookings.id')->paginate(15, ['bookings.*'], 'balances_page')->withQueryString();
        $rental = $this->netPayments($from, $to);
        $deposits = Payment::selectRaw("COALESCE(SUM(CASE WHEN kind='deposit' THEN amount WHEN kind='deposit_refund' THEN -amount ELSE 0 END),0) AS total")->value('total');

        return view('reports', compact('payments', 'bookings', 'outstanding', 'rental', 'deposits', 'from', 'to'));
    }

    public function export(Request $r)
    {
        $data = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);

        return response()->streamDownload(function () use ($data) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Date', 'Booking', 'Customer', 'Kind', 'Amount', 'Method', 'Reference']);
            Payment::with('booking.customer')->whereBetween('paid_on', [$data['from'], $data['to']])->orderBy('id')->chunkById(200, function ($rows) use ($f) {
                foreach ($rows as $p) {
                    $cells = [$p->paid_on, $p->booking->number, $p->booking->customer->name, $p->kind, number_format($p->amount / 100, 2, '.', ''), $p->method, $p->reference];
                    fputcsv($f, array_map(fn ($v) => preg_match('/^[\s]*[=+@\-]/', (string) $v) ? "'".$v : $v, $cells));
                }
            });
            fclose($f);
        }, 'payments-'.$data['from'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
