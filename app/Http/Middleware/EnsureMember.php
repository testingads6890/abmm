<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;

/** Only member-app accounts (role = member) with an approved family membership pass; pending/rejected joiners may only call /m/me. */
class EnsureMember
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || $u->role !== 'member') return response()->json(['message' => 'Forbidden.'], 403);
        if (! $request->is('api/v1/m/me', 'api/v1/m/auth/logout')) {
            $state = $u->member_id ? Member::withoutGlobalScopes()->whereKey($u->member_id)->value('approval') : null;
            if ($state !== 'approved') return response()->json(['message' => 'Your request is not approved yet.', 'state' => $state ?? 'none'], 403);
        }
        return $next($request);
    }
}
