<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\{PointsService, StreakService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Point rules, badges, rewards & redemptions, leaderboards, certificates, active-today counter. */
class PointsController extends Controller
{
    private function geo(Request $r): array
    {
        $u = $r->user(); $g = array_filter($r->only(['country', 'city', 'zone']));
        if (! $u->isSuper()) { abort_unless($u->hasArea(), 403); foreach ($u->areaFilter() as $k => $v) if (in_array($k, ['country', 'city', 'zone'], true)) $g[$k] = $v; }
        return $g;
    }

    public function index(Request $r, PointsService $p)
    {
        $geo = $this->geo($r);
        return view('admin.engage.points', [
            'rules' => DB::table('point_rules')->orderBy('id')->get(), 'badges' => DB::table('badges')->orderBy('min_points')->get(),
            'board' => $p->leaderboard($r->input('period', 'month') === 'all' ? 'all' : 'month', $geo, 50, $r->input('month')), 'geo' => $geo,
            'period' => $r->input('period', 'month'), 'activeToday' => StreakService::activeToday($geo),
            'streaks' => DB::table('member_streaks')->orderByDesc('current')->limit(10)->join('users', 'users.id', '=', 'member_streaks.user_id')->get(['users.name', 'member_streaks.current', 'member_streaks.longest']),
        ]);
    }

    public function ruleSave(Request $r, int $id)
    {
        $d = $r->validate(['points' => 'required|integer|min:0|max:10000', 'daily_cap' => 'nullable|integer|min:1|max:1000']);
        DB::table('point_rules')->where('id', $id)->update(['points' => $d['points'], 'daily_cap' => $d['daily_cap'] ?? null, 'is_active' => $r->boolean('is_active')]);
        return back()->with('ok', 'Rule saved.');
    }

    public function badgeSave(Request $r)
    {
        $d = $r->validate(['code' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:80', 'description' => 'nullable|string|max:200', 'min_points' => 'nullable|integer|min:1']);
        DB::table('badges')->updateOrInsert(['code' => $d['code']], ['name' => $d['name'], 'description' => $d['description'] ?? null, 'min_points' => $d['min_points'] ?? null, 'is_active' => true]);
        return back()->with('ok', 'Badge saved.');
    }

    public function rewards()
    {
        return view('admin.engage.rewards', [
            'rewards' => DB::table('rewards')->orderByDesc('id')->get(),
            'reds' => DB::table('redemptions as d')->join('rewards as w', 'w.id', '=', 'd.reward_id')->join('users as u', 'u.id', '=', 'd.user_id')->orderByRaw("d.status = 'pending' DESC")->orderByDesc('d.id')->limit(100)->get(['d.*', 'w.name as reward', 'u.name', 'u.mobile']),
        ]);
    }

    public function rewardSave(Request $r, ?int $id = null)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'description' => 'nullable|string|max:1000', 'cost' => 'required|integer|min:1|max:1000000', 'stock' => 'nullable|integer|min:0']);
        $row = $d + ['is_active' => $r->boolean('is_active', true), 'updated_at' => now()];
        $id ? DB::table('rewards')->where('id', $id)->update($row) : DB::table('rewards')->insert($row + ['created_at' => now()]);
        return back()->with('ok', 'Reward saved.');
    }

    /** Fulfil keeps the deducted points; reject refunds them (and the stock). */
    public function redemptionDecide(Request $r, int $id)
    {
        $d = $r->validate(['decision' => 'required|in:fulfilled,rejected', 'remark' => 'nullable|string|max:200']);
        DB::transaction(function () use ($d, $id, $r) {
            $x = DB::table('redemptions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($x && $x->status === 'pending', 422, 'Already decided.');
            DB::table('redemptions')->where('id', $id)->update(['status' => $d['decision'], 'remark' => $d['remark'] ?? null, 'decided_by' => $r->user()->id, 'updated_at' => now()]);
            if ($d['decision'] === 'rejected') {
                DB::table('point_ledger')->insertOrIgnore(['user_id' => $x->user_id, 'family_id' => $x->family_id, 'action' => 'redeem_refund', 'ref' => (string) $id, 'points' => $x->points, 'note' => 'Redemption rejected', 'created_at' => now()]);
                DB::table('rewards')->where('id', $x->reward_id)->whereNotNull('stock')->increment('stock');
            }
            UserNotification::create(['user_id' => $x->user_id, 'title' => 'Your reward request ' . ($d['decision'] === 'fulfilled' ? 'is complete' : 'rejected (points returned)') . ($d['remark'] ? ': ' . $d['remark'] : '')]);
        });
        return back()->with('ok', 'Updated.');
    }

    public function certificates()
    {
        return view('admin.engage.certificates', ['rows' => DB::table('certificates as c')->join('users as u', 'u.id', '=', 'c.user_id')->orderByDesc('c.id')->limit(200)->get(['c.*', 'u.name'])]);
    }

    public function certificateFile(int $id)
    {
        $c = DB::table('certificates')->where('id', $id)->first();
        abort_unless($c && $c->path && is_file(storage_path('app/private/' . $c->path)), 404);
        return response()->file(storage_path('app/private/' . $c->path), ['Content-Type' => 'application/pdf']);
    }
}
