<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Maintenance;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function inventory(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|integer', 'archived' => 'nullable|in:1']);
        $equipment = Equipment::with('category')->withSum(['maintenance as repair_quantity' => fn ($q) => $q->whereNull('completed_at')], 'quantity')->when(! $r->archived, fn ($q) => $q->where('active', true))->when($r->q, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('sku', 'like', '%'.$r->q.'%')))->when($r->category, fn ($q) => $q->where('category_id', $r->category))->orderBy('name')->paginate(12)->withQueryString();

        return view('inventory.index', ['equipment' => $equipment, 'categories' => Category::orderBy('name')->get()]);
    }

    public function equipmentForm(?Equipment $equipment = null)
    {
        return view('inventory.form', ['item' => $equipment ?? new Equipment, 'categories' => Category::orderBy('name')->get()]);
    }

    public function saveEquipment(Request $r, ?Equipment $equipment = null)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'sku' => ['required', 'string', 'max:50', Rule::unique('equipment')->ignore($equipment?->id)], 'category_id' => 'required|exists:categories,id', 'quantity' => 'required|integer|min:0|max:100000', 'daily_rate' => Money::rule(), 'replacement_cost' => Money::rule(), 'location' => 'nullable|string|max:150', 'description' => 'nullable|string|max:3000', 'active' => 'required|boolean']);
        $data['daily_rate'] = Money::cents($data['daily_rate']);
        $data['replacement_cost'] = Money::cents($data['replacement_cost']);
        DB::transaction(function () use ($equipment, $data) {
            if ($equipment) {
                $e = Equipment::lockForUpdate()->findOrFail($equipment->id);
                if ($data['quantity'] < $e->quantity) {
                    $has = $e->items()->whereHas('booking', fn ($q) => $q->whereIn('status', ['confirmed', 'out']))->exists() || $e->maintenance()->whereNull('completed_at')->exists();
                    if ($has) {
                        throw ValidationException::withMessages(['quantity' => 'Resolve active bookings and maintenance before reducing stock.']);
                    }
                }$e->update($data);
            } else {
                $e = Equipment::create($data);
            }
            Audit::log('Equipment saved', $e->sku);
        }, 3);

        return redirect()->route('inventory')->with('success', 'Equipment saved.');
    }

    public function category(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100|unique:categories']);
        Category::create($data);
        Audit::log('Category created', $data['name']);

        return back()->with('success', 'Category created.');
    }

    public function customers(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $customers = Customer::withCount('bookings')->when($r->q, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('email', 'like', '%'.$r->q.'%')->orWhere('company', 'like', '%'.$r->q.'%')))->orderBy('name')->paginate(15)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function customerForm(?Customer $customer = null)
    {
        return view('customers.form', ['customer' => $customer ?? new Customer]);
    }

    public function saveCustomer(Request $r, ?Customer $customer = null)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'company' => 'nullable|string|max:150', 'email' => 'nullable|email|max:255', 'phone' => 'required|string|max:40', 'address' => 'nullable|string|max:1000', 'notes' => 'nullable|string|max:3000']);
        if ($customer) {
            $customer->update($data);
        } else {
            $customer = Customer::create($data);
        }
        Audit::log('Customer saved', (string) $customer->id);

        return redirect()->route('customers')->with('success', 'Customer saved.');
    }

    public function maintenance()
    {
        return view('maintenance.index', ['jobs' => Maintenance::with('equipment')->orderByRaw('completed_at IS NOT NULL')->latest()->paginate(15), 'equipment' => Equipment::where('active', true)->orderBy('name')->get()]);
    }

    public function storeMaintenance(Request $r)
    {
        $data = $r->validate(['equipment_id' => 'required|exists:equipment,id', 'quantity' => 'required|integer|min:1|max:100000', 'reason' => 'required|string|max:150', 'notes' => 'nullable|string|max:3000', 'due_on' => 'nullable|date_format:Y-m-d']);
        DB::transaction(function () use ($data) {
            $e = Equipment::lockForUpdate()->findOrFail($data['equipment_id']);
            $reserved = $e->items()->whereHas('booking', fn ($q) => $q->whereIn('status', ['confirmed', 'out']))->sum('quantity');
            $held = $e->maintenance()->whereNull('completed_at')->sum('quantity');
            if ($data['quantity'] > $e->quantity - $reserved - $held) {
                throw ValidationException::withMessages(['quantity' => 'Not enough unreserved stock. Resolve affected reservations first.']);
            }Maintenance::create($data);
            Audit::log('Maintenance opened', $e->sku);
        }, 3);

        return back()->with('success', 'Equipment placed on maintenance hold.');
    }

    public function completeMaintenance(Maintenance $maintenance)
    {
        DB::transaction(function () use ($maintenance) {
            Equipment::whereKey($maintenance->equipment_id)->lockForUpdate()->firstOrFail();
            $m = Maintenance::lockForUpdate()->findOrFail($maintenance->id);
            if (! $m->completed_at) {
                $m->update(['completed_at' => now()]);
                Audit::log('Maintenance completed', (string) $m->id);
            }
        }, 3);

        return back()->with('success', 'Maintenance completed. Stock is available again.');
    }
}
