<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Daily message: Super Admin schedules a month at a time (all India or one pradesh); a State Sub-Admin with the permission can only manage own pradesh. */
class DailyMessageController extends Controller
{
    /** Member-app cache of "today's message" is versioned, so any admin change shows up immediately. */
    public static function bust(): void { Cache::forever('api.daily.v', (string) microtime(true)); }

    private function visible($q)
    {
        $u = auth()->user();
        return $u->isSuper() ? $q : $q->where(fn ($w) => $w->whereNull('pradesh')->orWhere('pradesh', $u->pradesh));
    }

    /** Pradesh a new row is stored under: own pradesh for non-super users, the chosen one (or all India) for Super Admin. */
    private function pradeshFor(Request $r): ?string
    {
        $u = auth()->user();
        return $u->isSuper() ? ($r->input('pradesh') ?: null) : $u->pradesh;
    }

    private function mine(int $id)
    {
        $row = DB::table('daily_messages')->where('id', $id)->first();
        abort_unless($row && (auth()->user()->isSuper() || $row->pradesh === auth()->user()->pradesh), 404);
        return $row;
    }

    public function index(Request $r)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $r->query('month')) ? $r->query('month') : now()->format('Y-m');
        $from = Carbon::parse("$month-01");
        $rows = $this->visible(DB::table('daily_messages'))->whereBetween('show_on', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])->orderBy('show_on')->orderBy('pradesh')->get();
        return view('admin.daily.index', [
            'rows' => $rows, 'month' => $month, 'prev' => $from->copy()->subMonth()->format('Y-m'), 'next' => $from->copy()->addMonth()->format('Y-m'),
            'pradeshList' => auth()->user()->isSuper() ? Lookup::where('type', 'pradesh')->orderBy('name')->pluck('name') : collect(),
            'today' => DB::table('daily_messages')->where('show_on', today())->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['show_on' => 'required|date', 'tithi' => 'nullable|max:120', 'body_hi' => 'required|max:1000', 'body_en' => 'nullable|max:1000', 'pradesh' => 'nullable|max:200', 'image' => 'nullable|image|max:2048']);
        $pradesh = $this->pradeshFor($r);
        $image = $r->hasFile('image') ? Storage::disk('public')->putFile('daily', $r->file('image')) : null;
        DB::table('daily_messages')->updateOrInsert(['show_on' => $d['show_on'], 'pradesh' => $pradesh], [
            'tithi' => $d['tithi'] ?? null, 'body_hi' => $d['body_hi'], 'body_en' => $d['body_en'] ?? null, 'is_active' => true,
            'created_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now(), ...($image ? ['image' => $image] : []),
        ]);
        self::bust();
        return back()->with('ok', 'Message saved.');
    }

    /** One line per day:  2026-11-01 | message      or      2026-11-01 | tithi | message */
    public function bulk(Request $r)
    {
        $r->validate(['lines' => 'required|string|max:60000', 'pradesh' => 'nullable|max:200']);
        $pradesh = $this->pradeshFor($r);
        $ok = 0; $bad = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($r->input('lines'))) as $i => $line) {
            if (trim($line) === '') continue;
            $p = array_map('trim', explode('|', $line, 3));
            [$date, $tithi, $msg] = count($p) === 3 ? $p : [$p[0], null, $p[1] ?? ''];
            try { $day = Carbon::createFromFormat('Y-m-d', $date); } catch (\Throwable) { $day = false; }
            if (! $day || $day->format('Y-m-d') !== $date || $msg === '' || mb_strlen($msg) > 1000) { $bad[] = $i + 1; continue; }
            DB::table('daily_messages')->updateOrInsert(['show_on' => $date, 'pradesh' => $pradesh], [
                'tithi' => $tithi ?: null, 'body_hi' => $msg, 'is_active' => true, 'created_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now(),
            ]);
            self::bust();
            $ok++;
        }
        return back()->with('ok', "Messages for $ok days saved." . ($bad ? ' These lines were invalid: ' . implode(', ', array_slice($bad, 0, 20)) : ''));
    }

    public function update(Request $r, int $id)
    {
        $row = $this->mine($id);
        $d = $r->validate(['tithi' => 'nullable|max:120', 'body_hi' => 'required|max:1000', 'body_en' => 'nullable|max:1000']);
        DB::table('daily_messages')->where('id', $id)->update($d + ['is_active' => $r->boolean('is_active'), 'updated_at' => now()]);
        self::bust();
        return back()->with('ok', 'Updated.');
    }

    public function destroy(int $id)
    {
        $row = $this->mine($id);
        if ($row->image) Storage::disk('public')->delete($row->image);
        DB::table('daily_messages')->where('id', $id)->delete();
        self::bust();
        return back()->with('ok', 'Hata diya.');
    }
}
