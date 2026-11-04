<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || ! $u->is_active || $u->role === 'member') { // app members never enter the admin panel
            auth()->logout();
            return redirect()->route('login')->withErrors(['login' => 'Account inactive or login required.']);
        }
        // Accounts created with a shared / reset password must pick their own before doing anything else.
        if ($u->must_change_password && ! $request->routeIs('admin.account.password', 'admin.password') && ! $request->is('logout')) {
            return redirect()->route('admin.account.password')->with('warn', 'First login: please set your new password.');
        }
        return $next($request);
    }
}
