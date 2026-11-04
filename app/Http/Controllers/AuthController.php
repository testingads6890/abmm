<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function show() { return view('public.login'); }

    public function login(Request $r)
    {
        $data = $r->validate(['login' => 'required|string', 'password' => 'required|string']);
        $key = 'login:' . strtolower($data['login']) . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['login' => 'Too many attempts. ' . RateLimiter::availableIn($key) . ' sec.']);
        }
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $ok = Auth::attempt([$field => $data['login'], 'password' => $data['password'], 'is_active' => true], $r->boolean('remember'));
        if ($ok && Auth::user()->role === 'member') { Auth::logout(); $ok = false; }   // app accounts never get the web panel
        if ($ok) {
            $r->session()->regenerate();
            RateLimiter::clear($key);
            Auth::user()->forceFill(['last_login_at' => now()])->save();
            ActivityLog::create(['user_id' => Auth::id(), 'action' => 'LOGIN', 'ip' => $r->ip()]);
            return redirect()->intended(route('admin.dashboard'));
        }
        RateLimiter::hit($key, 300);
        return back()->withErrors(['login' => 'Wrong login or password.'])->onlyInput('login');
    }

    public function logout(Request $r)
    {
        ActivityLog::create(['user_id' => Auth::id(), 'action' => 'LOGOUT', 'ip' => $r->ip()]);
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect()->route('home');
    }
}
