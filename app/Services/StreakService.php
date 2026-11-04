<?php

namespace App\Services;

use App\Models\{Family, User};
use Illuminate\Support\Facades\DB;

/** "Opened the app today" -> streak + active-today counter + daily points. Called by POST /api/v1/m/ping. */
class StreakService
{
    public function __construct(private PointsService $points) {}

    public function ping(User $u): array
    {
        $today = today();
        $s = DB::table('member_streaks')->where('user_id', $u->id)->first();
        $first = ! $s || $s->last_date !== $today->toDateString();
        if ($first) {
            $cur = ($s && $s->last_date === $today->copy()->subDay()->toDateString()) ? $s->current + 1 : 1;
            $longest = max($cur, (int) ($s->longest ?? 0));
            DB::table('member_streaks')->updateOrInsert(['user_id' => $u->id], ['current' => $cur, 'longest' => $longest, 'last_date' => $today->toDateString(), 'updated_at' => now(), 'created_at' => $s->created_at ?? now()]);
            $f = $u->family_id ? Family::withoutGlobalScopes()->find($u->family_id, ['id', 'country', 'city']) : null;
            DB::table('daily_active')->insertOrIgnore(['day' => $today->toDateString(), 'user_id' => $u->id, 'family_id' => $f?->id, 'country' => $f?->country, 'city' => $f?->city]);
            $this->points->award($u, 'daily_open', $today->toDateString());
            if ($cur === 7) $this->points->award($u, 'streak_7', $today->toDateString());
            if ($cur === 30) $this->points->award($u, 'streak_30', $today->toDateString());
        } else { $cur = (int) $s->current; $longest = (int) $s->longest; }
        return ['streak' => $cur, 'longest' => $longest, 'points' => $this->points->balance($u->id), 'first_today' => $first];
    }

    public static function activeToday(array $geo = []): int
    {
        return DB::table('daily_active')->where('day', today()->toDateString())->when($geo['country'] ?? null, fn ($q, $v) => $q->where('country', $v))->when($geo['city'] ?? null, fn ($q, $v) => $q->where('city', $v))->count();
    }
}
