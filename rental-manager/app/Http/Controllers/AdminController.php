<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function settings()
    {
        return view('admin.settings');
    }

    public function saveSettings(Request $r)
    {
        $data = $r->validate(['business_name' => 'required|string|max:100', 'tagline' => 'nullable|string|max:150', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:1000', 'currency' => 'required|in:PHP,SGD,USD,EUR,GBP,AUD,MYR,IDR,THB', 'tax_percent' => 'required|numeric|min:0|max:100|decimal:0,2', 'terms' => 'nullable|string|max:5000', 'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=3000,max_height=3000']);
        $data['tagline'] = $data['tagline'] ?? '';
        $data['tax_bps'] = (int) round((float) $data['tax_percent'] * 100);
        unset($data['tax_percent'],$data['logo']);
        if ($r->hasFile('logo')) {
            $data['logo_path'] = $r->file('logo')->store('branding', 'public');
        }
        DB::transaction(function () use ($data) {
            $settings = Setting::lockForUpdate()->firstOrFail();
            if ($data['currency'] !== $settings->currency && Booking::exists()) {
                throw ValidationException::withMessages(['currency' => 'Currency cannot change after bookings exist. No automatic exchange conversion is performed.']);
            }
            $settings->update($data);
            Audit::log('Business settings updated');
        });

        return back()->with('success', 'Business settings updated. Tax changes apply to new or edited quotes.');
    }

    public function users()
    {
        return view('admin.users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function userForm(?User $user = null)
    {
        return view('admin.user-form', ['staff' => $user ?? new User]);
    }

    public function saveUser(Request $r, ?User $user = null)
    {
        $data = $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)], 'role' => 'required|in:admin,manager,operations,finance,viewer', 'active' => 'required|boolean', 'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($r, $user, $data) {
            $admins = User::where('role', 'admin')->where('active', true)->orderBy('id')->lockForUpdate()->get();
            if ($user && $admins->contains('id', $user->id) && ($data['role'] !== 'admin' || ! $data['active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
            }
            if ($user?->id === $r->user()->id && ($data['role'] !== 'admin' || ! $data['active'])) {
                throw ValidationException::withMessages(['role' => 'Another administrator must change your own role or access.']);
            }
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['must_change_password'] = true;
            }
            if ($user) {
                $user->update($data);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } else {
                $user = User::create($data);
            }
            Audit::log('Staff access updated', (string) $user->id);
        }, 3);

        return redirect()->route('users')->with('success', 'Staff account saved. New and reset passwords must be changed on sign-in.');
    }

    public function audit()
    {
        return view('admin.audit', ['logs' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->select('audit_logs.*', 'users.name')->orderByDesc('audit_logs.id')->paginate(30)]);
    }
}
