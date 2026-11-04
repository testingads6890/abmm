<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CommitteeMember, Lookup};
use Illuminate\Http\Request;

class CommitteeController extends Controller
{
    public function index(Request $r)
    {
        $rows = CommitteeMember::query()
            ->when($r->designation, fn ($q, $v) => $q->where('designation', $v))
            ->when($r->level, fn ($q, $v) => $q->where('level', $v))
            ->when($r->tehsil, fn ($q, $v) => $q->where('tehsil', $v))
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('mobile', 'like', "%$t%")))
            ->orderBy('name')->paginate(in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 10)->withQueryString();
        return view('admin.committee.index', ['rows' => $rows, 'designations' => $this->designations(), 'surnames' => Lookup::grouped()['surname'] ?? [], 'total' => CommitteeMember::count()]);
    }

    public function store(Request $r)
    {
        CommitteeMember::create($this->valid($r));
        return back()->with('ok', 'Committee member added.');
    }

    public function update(Request $r, CommitteeMember $committee)
    {
        $committee->update($this->valid($r, $committee));
        return back()->with('ok', 'Updated.');
    }

    public function destroy(CommitteeMember $committee)
    {
        $committee->delete();
        return back()->with('ok', 'Committee member removed.');
    }

    /** Sample file the "Download File" link gives out (CSV opens in Excel). */
    public function sample()
    {
        return response("name,surname,designation,mobile,dob,address\nAmit,MUNDHRA,Secretary,9830000000,1985-01-31,\"Sample address, City\"\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="committee-member-sample.csv"']);
    }

    /** Bulk upload (.csv, or .xls/.xlsx when phpoffice/phpspreadsheet is installed). Date of birth is mandatory: rows without it are discarded. */
    public function import(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt,xls,xlsx|max:4096']);
        $path = $r->file('file')->getRealPath();
        $ext = strtolower($r->file('file')->getClientOriginalExtension());
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                return back()->withErrors(['file' => 'For Excel (.xls/.xlsx): composer require phpoffice/phpspreadsheet - or save the file as CSV and upload it.']);
            }
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
            $head = array_map(fn ($x) => strtolower(trim((string) $x)), array_shift($sheet) ?: []);
            $rows = $sheet;
        } else {
            $h = fopen($path, 'r');
            $head = array_map(fn ($x) => strtolower(trim($x)), fgetcsv($h) ?: []);
            $rows = [];
            while (($row = fgetcsv($h)) !== false) $rows[] = $row;
            fclose($h);
        }
        $n = 0; $discarded = 0; $now = now();
        foreach ($rows as $row) {
            $d = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), null));
            if (empty($d['name']) || empty($d['designation'])) continue;
            $dob = ! empty($d['dob']) ? strtotime((string) $d['dob']) : false;
            if (! $dob) { $discarded++; continue; }                 // "Without Date Of Birth Data Will Be Discarded"
            CommitteeMember::create([
                'name' => trim($d['name']), 'surname' => $d['surname'] ?? null, 'designation' => $d['designation'], 'level' => $d['level'] ?? 'country', 'mobile' => $d['mobile'] ?? null,
                'dob' => date('Y-m-d', $dob), 'address' => $d['address'] ?? null, 'pradesh' => $d['pradesh'] ?? null, 'district' => $d['district'] ?? null, 'tehsil' => $d['tehsil'] ?? null,
            ]);
            $n++;
        }
        return back()->with('ok', "$n members imported" . ($discarded ? ", $discarded rows were discarded for missing Date of Birth." : '.'));
    }

    private function valid(Request $r, ?CommitteeMember $m = null): array
    {
        $x = \App\Support\CustomForm::collect($r, 'committee', $m?->extra);
        return ['extra' => $x] + $r->validate(['name' => 'required|max:120', 'surname' => 'nullable|max:80', 'designation' => 'required|max:80', 'level' => 'required|in:country,pradesh,state,district,tehsil', 'mobile' => 'nullable|digits:10', 'dob' => 'nullable|date', 'address' => 'nullable|max:400', 'pradesh' => 'nullable|max:120', 'district' => 'nullable|max:80', 'tehsil' => 'nullable|max:80', 'term_start' => 'nullable|date', 'term_end' => 'nullable|date', 'is_active' => 'boolean']);
    }

    private function designations()
    {
        return collect(Lookup::grouped()['designation'] ?? []);
    }
}
