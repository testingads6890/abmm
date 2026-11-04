<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Member;
use App\Services\DirectorySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Community directory. A member only ever sees people of HIS / HER OWN pradesh (state-wise data).
 * Phone numbers are shown only for members who opted in; anybody can hide from the directory completely.
 */
class DirectoryController extends Controller
{
    private function pradesh(Request $r): ?string
    {
        return Family::withoutGlobalScopes()->whereKey($r->user()->family_id)->value('pradesh') ?: null;
    }

    private function esc(string $s): string { return str_replace(['%', '_'], ['\%', '\_'], trim($s)); }

    /** GET directory?q=&district=&city=&occupation=&education=&page= (20 a page, at most 10 pages). */
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:60', 'district' => 'nullable|string|max:80', 'city' => 'nullable|string|max:80', 'occupation' => 'nullable|string|max:80', 'education' => 'nullable|string|max:80', 'page' => 'nullable|integer|min:1|max:10']);
        $pr = $this->pradesh($r);
        if (! $pr) return ['data' => [], 'next_page' => null];
        abort_unless(DirectorySettings::enabled($pr), 403, 'Directory is currently closed in your state.');
        $q = trim((string) $r->query('q'));
        abort_if($q !== '' && mb_strlen($q) < 2, 422, 'Kam se kam 2 akshar likhein.');

        $rows = Member::withoutGlobalScopes()->join('families as f', 'f.id', '=', 'members.family_id')
            ->where('f.pradesh', $pr)->where('members.status', 'active')->where('members.approval', 'approved')->whereNull('members.archived_at')->where('members.dir_hidden', false)
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('members.name', 'like', $this->esc($q) . '%')->orWhere('f.surname', 'like', $this->esc($q) . '%')))
            ->when(filled($r->query('district')), fn ($x) => $x->where('f.district', $r->query('district')))
            ->when(filled($r->query('city')), fn ($x) => $x->where('f.city', 'like', $this->esc($r->query('city')) . '%'))
            ->when(filled($r->query('occupation')), fn ($x) => $x->where('members.occupation', 'like', '%' . $this->esc($r->query('occupation')) . '%'))
            ->when(filled($r->query('education')), fn ($x) => $x->where('members.education', 'like', '%' . $this->esc($r->query('education')) . '%'))
            ->select('members.id', 'members.name', 'members.occupation', 'members.education', 'members.mobile', 'members.dir_show_phone', 'f.surname', 'f.city', 'f.district')
            ->orderBy('members.name')->simplePaginate(20);

        return [
            'data' => $rows->getCollection()->map(fn ($m) => [
                'id' => $m->id, 'name' => trim($m->name . ' ' . $m->surname), 'city' => $m->city, 'district' => $m->district, 'occupation' => $m->occupation, 'education' => $m->education,
                'phone' => $m->dir_show_phone ? $m->mobile : null,
            ])->values(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    /** Districts of my pradesh, for the filter. */
    public function districts(Request $r)
    {
        $pr = $this->pradesh($r);
        if (! $pr) return ['data' => []];
        return ['data' => Cache::remember('dir_districts_' . md5($pr), 3600, fn () => DB::table('families')->where('pradesh', $pr)->whereNotNull('district')->where('district', '!=', '')->distinct()->orderBy('district')->pluck('district')->all())];
    }

    public function privacy(Request $r)
    {
        $m = Member::withoutGlobalScopes()->findOrFail($r->user()->member_id);
        return ['hidden' => (bool) $m->dir_hidden, 'show_phone' => (bool) $m->dir_show_phone];
    }

    public function setPrivacy(Request $r)
    {
        $d = $r->validate(['hidden' => 'nullable|boolean', 'show_phone' => 'nullable|boolean']);
        $m = Member::withoutGlobalScopes()->findOrFail($r->user()->member_id);
        $m->update(['dir_hidden' => $r->boolean('hidden', $m->dir_hidden), 'dir_show_phone' => $r->boolean('show_phone', $m->dir_show_phone)]);
        return ['ok' => true, 'hidden' => (bool) $m->dir_hidden, 'show_phone' => (bool) $m->dir_show_phone];
    }
}
