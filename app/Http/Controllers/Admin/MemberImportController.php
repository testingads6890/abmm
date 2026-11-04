<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Family, ImportBatch};
use App\Services\MemberSheetImporter;
use App\Support\Exporter;
use Illuminate\Http\Request;

/** Members: bulk CSV import (one row per person), CSV/Excel export in the same layout, sample file, undo. */
class MemberImportController extends Controller
{
    /** Column order of the sheet (matches the Bharatpur CSV; the last block is optional extras). */
    public const HEADERS = ['Family ID', 'Last Name', 'First Name', 'Father/Husband', 'Relationship', 'Mobile', 'Email', 'Address', 'City / Village', 'Tehsil', 'District', 'Pincode',
        'Gender', 'Blood Group', 'Business type', 'Business Type Detail', 'Education', 'Education Detail', 'Occupation', 'Occupation Detail', 'Profession', 'Profession Detail',
        'Marital Status', 'Marriage Anniversary', 'Date Of Birth', 'Income', 'Do All Family Member Have Mediclaim /Health Insurance Policy', 'Are You Member Of Maheshwari Pakshik (Nagpur)',
        'Profile last updated', 'State', 'Pradesh', 'Country', 'Zone', 'Caste', 'Gotra', 'Anchal'];

    public function index(Request $r)
    {
        $u = $r->user();
        $batches = ImportBatch::with('user')->when(! $u->isSuper(), fn ($q) => $q->where('user_id', $u->id))->latest()->limit(15)->get();
        return view('admin.members-import', ['batches' => $batches, 'canImport' => $u->can('families.import'), 'canExport' => $u->can('families.export'), 'headers' => self::HEADERS]);
    }

    public function sample()
    {
        $rows = [
            ['380717', 'MAHESHWARI', 'Sanjay Kumar', 'Brij Ratan Maheshwari', 'HOF', '9636599737', 'sanjay@example.com', 'Near Radha Krishna Mandir', 'Bharatpur', 'BHARATPUR', 'BHARATPUR', '321001', 'MALE', 'O+', '', '', 'Master of Arts', 'History', 'Other Teacher or Instructor', '', 'TEACHER', '', 'MARRIED', '04-Dec-1998', '19-Aug-1971', '6 Lakh+ To 10 Lakh', 'Yes', 'No', '02-Jul-2022', 'RAJASTHAN', '', 'INDIA', '', '', ''],
            ['380717', 'MAHESHWARI', 'Manisha', 'Sanjay Kumar Maheshwari', 'WIFE', '9783409175', '', 'Near Radha Krishna Mandir', 'Bharatpur', 'BHARATPUR', 'BHARATPUR', '321001', 'FEMALE', 'O+', '', '', 'Master of Science', 'Mathematics', 'Home Maker', '', '', '', 'MARRIED', '04-Dec-1998', '20-Dec-1975', '', 'Yes', 'No', '02-Jul-2022', '', '', '', '', '', ''],
            ['380717', 'MAHESHWARI', 'Divyansh', 'Sanjay Kumar Maheshwari', 'SON', '', '', 'Near Radha Krishna Mandir', 'Bharatpur', 'BHARATPUR', 'BHARATPUR', '321001', 'MALE', 'O+', '', '', '', '', '', '', '', '', 'UNMARRIED', '', '13-Dec-2003', '', '', '', '', '', '', '', '', '', ''],
        ];
        return response()->streamDownload(function () use ($rows) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, self::HEADERS);
            foreach ($rows as $row) fputcsv($o, $row);
            fclose($o);
        }, 'abmm-members-import-sample.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:51200', 'country' => 'nullable|max:60', 'state' => 'nullable|max:80', 'pradesh' => 'nullable|max:120', 'city' => 'nullable|max:100', 'zone' => 'nullable|max:80']);
        set_time_limit(600);
        $dry = $r->boolean('dry_run');
        $imp = new MemberSheetImporter($r->user(), $r->only('country', 'state', 'pradesh', 'city', 'zone'));
        try {
            $imp->import($r->file('file')->getRealPath(), $r->file('file')->getClientOriginalName(), $dry);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }
        if (! $dry) ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MEMBER_IMPORT', 'detail' => "batch #{$imp->batchId} +{$imp->stats['families_created']} families, +{$imp->stats['members_created']} members", 'ip' => $r->ip()]);
        return back()->with('import_result', ['dry' => $dry, 'batch' => $imp->batchId, 'stats' => $imp->stats, 'counters' => $imp->counters, 'errors' => $imp->errors, 'notes' => $imp->notes, 'sample' => $imp->sample]);
    }

    public function undo(Request $r, ImportBatch $batch)
    {
        abort_unless($r->user()->isSuper() || $batch->user_id === $r->user()->id, 403);
        if ($batch->undone_at) return back()->withErrors(['file' => 'This import was already undone.']);
        $n = MemberSheetImporter::undo($batch);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MEMBER_IMPORT_UNDO', 'detail' => "batch #{$batch->id}", 'ip' => $r->ip()]);
        return back()->with('ok', "Import #{$batch->id} undone: {$n['deleted']} families removed" . ($n['kept'] ? ", {$n['kept']} deactivated (they already have an app login)" : '') . ". Records that the import only updated were not changed.");
    }

    public function export(Request $r)
    {
        $fmt = in_array($r->get('format'), ['csv', 'xlsx'], true) ? $r->get('format') : 'csv';
        $q = Family::query()->with(['members' => fn ($m) => $m->withoutGlobalScopes()->where('status', 'active')->orderByDesc('is_head')->orderBy('id')])
            ->where('status', $r->get('status') === 'inactive' ? 'inactive' : 'active')
            ->when($r->filled('district'), fn ($x) => $x->where('district', strtoupper($r->district)))
            ->when($r->filled('tehsil'), fn ($x) => $x->where('tehsil', strtoupper($r->tehsil)))
            ->when($r->filled('pradesh'), fn ($x) => $x->where('pradesh', strtoupper($r->pradesh)))
            ->when($r->filled('city'), fn ($x) => $x->where('city', $r->city))
            ->when($r->filled('q'), fn ($x) => $x->search($r->q))
            ->orderBy('id');
        $gen = (function () use ($q) {
            foreach ($q->lazy(500) as $f) {
                foreach ($f->members as $m) yield self::row($f, $m);
            }
        })();
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MEMBER_EXPORT', 'detail' => $fmt, 'ip' => $r->ip()]);
        return Exporter::download($fmt, 'abmm-members', 'Members (family-wise)', self::HEADERS, $gen);
    }

    public static function row(Family $f, $m): array
    {
        $yn = fn ($v) => $v === null ? 'Not Available' : ($v ? 'Yes' : 'No');
        $d = fn ($v) => $v ? $v->format('d-M-Y') : '';
        $head = (bool) $m->is_head;
        return [
            $f->family_code, $m->surname ?: $f->surname, $m->name, $m->father_husband_name ?: ($head ? $f->father_husband_name : ''),
            $head ? 'HOF' : ($m->relation ?: 'OTHER'), $m->mobile, $m->email, $m->address ?: $f->address, $m->city ?: $f->city, $m->tehsil ?: $f->tehsil, $f->district, $m->pincode ?: $f->pincode,
            strtoupper((string) $m->gender), $m->blood_group, $m->business_type, $m->business_detail, $m->education, $m->education_detail, $m->occupation, $m->occupation_detail,
            $m->profession, $m->profession_detail, $m->marital_status, $d($m->anniversary), $d($m->dob), $m->income_range ?: ($head ? $f->income_range : ''),
            $yn($m->has_mediclaim ?? ($head ? $f->has_mediclaim : null)), $yn($m->pakshik_member ?? ($head ? $f->pakshik_member : null)),
            $d($m->profile_updated_on ?? ($head ? $f->profile_updated_on : null)), $f->state, $f->pradesh, $f->country, $f->zone, $m->caste ?: $f->caste, $m->gotra ?: $f->gotra,
            \App\Models\Lookup::anchalOf($f->pradesh) ?? '',
        ];
    }
}
