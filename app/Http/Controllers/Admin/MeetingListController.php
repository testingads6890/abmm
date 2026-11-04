<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Lookup, MeetingEntry};
use Illuminate\Http\Request;

class MeetingListController extends Controller
{
    /** Pradesh options with their district and anchal. Super Admin sees all; a sub-admin sees only their own pradesh. */
    private function areaOptions(Request $r): array
    {
        $user = $r->user();
        // Every ABMM Pradesh in Master Data, with its district and Anchal (see Lookup::districtAnchalOf).
        $rows = [];
        foreach (Lookup::ofType('pradesh')->active()->orderBy('name')->pluck('name')->unique() as $pradesh) {
            $area = Lookup::districtAnchalOf($pradesh);
            $rows[] = ['pradesh' => $pradesh, 'district' => $area['district'], 'anchal' => $area['anchal']];
        }
        usort($rows, fn ($a, $b) => [$a['district'], $a['pradesh']] <=> [$b['district'], $b['pradesh']]);
        if (! $user->isSuper()) {
            $rows = array_values(array_filter($rows, fn ($x) => strcasecmp($x['pradesh'], (string) $user->pradesh) === 0));
        }
        return $rows;
    }

    public function index(Request $r)
    {
        $q = MeetingEntry::query()->with('creator')->latest('meeting_date');
        if (! $r->user()->isSuper()) $q->where('pradesh', $r->user()->pradesh);
        if ($r->committee) $q->where('committee', $r->committee);
        if ($r->meeting_type) $q->where('meeting_type', $r->meeting_type);
        if ($r->pradesh) $q->where('pradesh', $r->pradesh);
        if ($r->anchal) {
            $list = Lookup::pradeshesOf($r->anchal);
            $list ? $q->whereIn('pradesh', $list) : $q->whereRaw('1 = 0');
        }
        $rows = $q->paginate(20)->withQueryString();
        $areas = $this->areaOptions($r);
        return view('admin.meeting_list.index', [
            'rows' => $rows,
            'committees' => MeetingEntry::COMMITTEES,
            'types' => MeetingEntry::TYPES,
            'pradeshes' => collect($areas)->pluck('pradesh')->unique()->values(),
            'anchals' => $r->user()->isSuper() ? array_keys(Lookup::anchalPradesh()) : collect($areas)->pluck('anchal')->unique()->values(),
        ]);
    }

    public function create(Request $r)
    {
        return view('admin.meeting_list.create', [
            'areas' => $this->areaOptions($r),
            'committees' => MeetingEntry::COMMITTEES,
            'types' => MeetingEntry::TYPES,
        ]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'committee' => 'required|in:' . implode(',', array_keys(MeetingEntry::COMMITTEES)),
            'meeting_type' => 'required|in:' . implode(',', array_keys(MeetingEntry::TYPES)),
            'pradesh' => 'required|max:120',
            'venue' => 'required|max:200',
            'location' => 'required|max:300',
            'meeting_date' => 'required|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'agenda' => 'nullable|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        // District and anchal are never trusted from the form: they are looked up from the chosen pradesh.
        $area = collect($this->areaOptions($r))->first(fn ($x) => strcasecmp($x['pradesh'], $d['pradesh']) === 0);
        abort_unless($area, 422, 'Pradesh not found in your area.');
        $d['pradesh'] = $area['pradesh'];
        $d['district'] = $area['district'];
        $d['anchal'] = $area['anchal'];

        if ($r->hasFile('attachment')) $d['attachment'] = $r->file('attachment')->store('meetings', 'public');
        $d['created_by'] = $r->user()->id;

        MeetingEntry::create($d);
        return redirect()->route('admin.meeting_list.index')->with('ok', 'Meeting added.');
    }

    public function destroy(MeetingEntry $meeting)
    {
        $meeting->delete();
        return back()->with('ok', 'Meeting removed.');
    }
}
