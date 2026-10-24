<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt($data + ['active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect, or the account is inactive.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();
        Audit::log('Signed in');

        return redirect()->intended('/');
    }

    public function logout(Request $r)
    {
        Audit::log('Signed out');
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function edit()
    {
        return view('auth.password');
    }

    public function update(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(), 'different:current_password']]);
        $r->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate();
        Audit::log('Password changed');

        return redirect('/')->with('success', 'Password updated. Other sessions have been signed out.');
    }
}
