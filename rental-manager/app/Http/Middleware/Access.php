<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Access
{
    public function handle(Request $r, Closure $next, string $ability = 'view')
    {
        if (! $r->user()?->active) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'This account is inactive. Contact your administrator.']);
        }
        if ($r->user()->must_change_password && ! $r->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }
        abort_unless($r->user()->allows($ability), 403, 'Your role does not have permission for this action.');

        return $next($r);
    }
}
