<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Bearer "<id>|<plain>" token auth for the Flutter app. Only the SHA-256 hash is stored; expired/disabled users are rejected. */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $raw = (string) $request->bearerToken();
        [$id, $plain] = array_pad(explode('|', $raw, 2), 2, '');
        $t = ctype_digit($id) && $plain !== '' ? ApiToken::with('user')->find((int) $id) : null;

        if (! $t || ! hash_equals($t->token_hash, hash('sha256', $plain)) || $t->expires_at->isPast() || ! $t->user?->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! $t->last_used_at || $t->last_used_at->lt(now()->subMinutes(5))) $t->forceFill(['last_used_at' => now()])->saveQuietly();

        Auth::setUser($t->user);            // so Gate / area scopes work exactly like the web panel
        $request->attributes->set('api_token', $t);
        return $next($request);
    }
}
