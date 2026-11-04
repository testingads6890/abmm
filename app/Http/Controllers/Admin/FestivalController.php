<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Festival calendar: banner + greeting in the app on the day, and an optional push to all (or one pradesh). */
class FestivalController extends Controller
{
    private function visible($q)
    {
        $u = auth()->user();
        return $u->isSuper() ? $q : $q->where(fn ($w) => $w->whereNull('pradesh')->orWhere('pradesh', $u->pradesh));
    }

    private function mine(int $id)
    {
        $row = DB::table('festivals')->where('id', $id)->first();
        abort_unless($row && (auth()->user()->isSuper() || $row->pradesh === auth()->user()->pradesh), 404);
        return $row;
    }

    public function index(Request $r)
    {
        $year = (int) ($r->query('year') ?: now()->year);
        return view('admin.festivals.index', [
            'rows' => $this->visible(DB::table('festivals'))->whereYear('festival_date', $year)->orderBy('festival_date')->get(),
            'year' => $year,
            'pradeshList' => auth()->user()->isSuper() ? Lookup::where('type', 'pradesh')->orderBy('name')->pluck('name') : collect(),
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|max:150', 'festival_date' => 'required|date', 'message' => 'required|max:1000', 'pradesh' => 'nullable|max:200', 'banner' => 'nullable|image|max:2048']);
        $u = auth()->user();
        DB::table('festivals')->insert([
            'name' => $d['name'], 'festival_date' => $d['festival_date'], 'message' => $d['message'],
            'pradesh' => $u->isSuper() ? ($d['pradesh'] ?? null) : $u->pradesh, 'notify' => $r->boolean('notify'), 'is_active' => true,
            'banner' => $r->hasFile('banner') ? Storage::disk('public')->putFile('festivals', $r->file('banner')) : null,
            'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DailyMessageController::bust();
        return back()->with('ok', 'Festival added.');
    }

    public function update(Request $r, int $id)
    {
        $this->mine($id);
        $d = $r->validate(['name' => 'required|max:150', 'festival_date' => 'required|date', 'message' => 'required|max:1000']);
        DB::table('festivals')->where('id', $id)->update($d + ['notify' => $r->boolean('notify'), 'is_active' => $r->boolean('is_active'), 'updated_at' => now()]);
        DailyMessageController::bust();
        return back()->with('ok', 'Updated.');
    }

    public function destroy(int $id)
    {
        $row = $this->mine($id);
        if ($row->banner) Storage::disk('public')->delete($row->banner);
        DB::table('festivals')->where('id', $id)->delete();
        DailyMessageController::bust();
        return back()->with('ok', 'Hata diya.');
    }
}
