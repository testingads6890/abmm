<?php

namespace App\Services;

use App\Models\{Family, User, UserNotification};
use Illuminate\Support\Facades\DB;

/** Points ledger, auto badges. Every award is idempotent per (user, action, ref) and respects the rule's daily cap. */
class PointsService
{
    public function award(User|int $user, string $action, ?string $ref = null, ?int $override = null, ?string $note = null): int
    {
        $u = $user instanceof User ? $user : User::withoutGlobalScopes()->find($user);
        if (! $u || $u->role !== 'member') return 0;
        $rule = DB::table('point_rules')->where('action', $action)->first();
        $points = $override ?? ($rule && $rule->is_active ? (int) $rule->points : 0);
        if ($points === 0) return 0;
        if ($rule && $rule->daily_cap && DB::table('point_ledger')->where('user_id', $u->id)->where('action', $action)->whereDate('created_at', today())->count() >= $rule->daily_cap) return 0;
        $f = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id, ['id', 'country', 'city', 'zone']) : null;
        $ok = DB::table('point_ledger')->insertOrIgnore(['user_id' => $u->id, 'family_id' => $f?->id, 'country' => $f?->country, 'city' => $f?->city, 'zone' => $f?->zone,
            'action' => $action, 'ref' => $ref ?? '', 'points' => $points, 'note' => $note, 'created_at' => now()]);
        if (! $ok) return 0;
        $this->badges($u);
        return $points;
    }

    public function balance(int $userId): int { return (int) DB::table('point_ledger')->where('user_id', $userId)->sum('points'); }

    /** Lifetime earned (redemptions excluded) drives badges. */
    public function earned(int $userId): int { return (int) DB::table('point_ledger')->where('user_id', $userId)->where('points', '>', 0)->where('action', '!=', 'redeem_refund')->sum('points'); }

    private function badges(User $u): void
    {
        $earned = $this->earned($u->id);
        foreach (DB::table('badges')->where('is_active', true)->whereNotNull('min_points')->where('min_points', '<=', $earned)->get() as $b) {
            if (DB::table('user_badges')->insertOrIgnore(['user_id' => $u->id, 'badge_id' => $b->id, 'ref' => '', 'created_at' => now()]))
                UserNotification::create(['user_id' => $u->id, 'title' => "New badge earned: {$b->name}"]);
        }
    }

    /** Leaderboard rows: user_id, name, city, points. $period: month | all. */
    public function leaderboard(string $period = 'month', array $geo = [], int $limit = 50, ?string $month = null)
    {
        $q = DB::table('point_ledger as l')->join('users as u', 'u.id', '=', 'l.user_id')->where('l.points', '>', 0)->whereNotIn('l.action', ['redeem', 'redeem_refund']);
        foreach (['country', 'city', 'zone'] as $k) if (! empty($geo[$k])) $q->where("l.$k", $geo[$k]);
        if ($period === 'month') {
            $m = $month ? \Carbon\Carbon::parse($month . '-01') : now();
            $q->whereBetween('l.created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()]);
        }
        return $q->groupBy('l.user_id', 'u.name', 'l.city', 'l.country')->orderByDesc(DB::raw('SUM(l.points)'))->orderBy('l.user_id')->limit($limit)
            ->get(['l.user_id', 'u.name', 'l.country', 'l.city', DB::raw('SUM(l.points) as points')]);
    }
}
