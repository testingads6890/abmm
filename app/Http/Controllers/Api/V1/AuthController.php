<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, ApiToken, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public static function payload(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile,
            'role' => $u->role, 'role_label' => $u->roleLabel(),
            'area' => ['pradesh' => $u->pradesh, 'district' => $u->district, 'tehsil' => $u->tehsil],
            'permissions' => $u->isSuper() ? User::allPermissionKeys() : array_values($u->permissions ?? []),
            'can_create_roles' => $u->creatableRoles(), 'must_change_password' => (bool) $u->must_change_password,
        ];
    }

    public function login(Request $r)
    {
        $d = $r->validate(['login' => 'required|string|max:150', 'password' => 'required|string|max:200', 'device_name' => 'nullable|string|max:100', 'fcm_token' => 'nullable|string|max:255']);
        $key = 'api-login:' . strtolower($d['login']) . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many attempts. ' . RateLimiter::availableIn($key) . ' sec.'], 429);
        }
        $u = User::where(filter_var($d['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile', $d['login'])->first();
        if (! $u || ! $u->is_active || $u->role === 'member' || ! Hash::check($d['password'], $u->password)) {
            RateLimiter::hit($key, 300);
            return response()->json(['message' => 'Wrong login or password.'], 422);
        }
        RateLimiter::clear($key);
        [, $token] = ApiToken::issue($u, $d['device_name'] ?? null, $d['fcm_token'] ?? null);
        $u->forceFill(['last_login_at' => now()])->save();
        ActivityLog::create(['user_id' => $u->id, 'action' => 'API_LOGIN', 'ip' => $r->ip()]);
        return ['token' => $token, 'user' => self::payload($u)];
    }

    public function me(Request $r) { return ['user' => self::payload($r->user())]; }

    public function fcm(Request $r)
    {
        $d = $r->validate(['fcm_token' => 'required|string|max:255']);
        $r->attributes->get('api_token')->update(['fcm_token' => $d['fcm_token']]);
        return response()->noContent();
    }

    public function logout(Request $r)
    {
        $r->attributes->get('api_token')->delete();
        return response()->noContent();
    }
}
