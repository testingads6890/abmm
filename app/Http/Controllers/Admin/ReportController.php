<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Business, BusinessAd, BusinessReview, CommitteeMember, Family, Lookup, Member};
use Illuminate\Http\Request;
use App\Support\Exporter;
use Illuminate\Support\HtmlString;

class ReportController extends Controller
{
    /** Same order as the original sidebar. [slug => [label, route-or-null]] */
    public const TYPES = [
        'member' => 'Member', 'family' => 'Family', 'moved' => 'Hanging Data', 'size' => 'No. of family member wise',
        'committee' => 'Committee Member Report', 'parinay' => 'Mangal Parinay', 'archive-parinay' => 'Archive Mangal Parinay',
        'ads' => 'Business Advertise', 'business' => 'Business', 'reviews' => 'Business Reviews', 'adpay' => 'Advertisement Payment',
    ];
    public const AGE_GROUPS = ['0-5', '6-10', '11-15', '16-20', '21-25', '26-30', '31-40', '41-50', '51-60', '61-70', '71-80', '81-90', '91-100'];
    private const MAX_EXPORT = 100000;

    public function show(Request $r, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        if ($type === 'parinay') return redirect()->route('admin.matrimony.index', $r->query());
        if ($type === 'archive-parinay') return redirect()->route('admin.matrimony.archive', $r->query());
        if ($type === 'size') return $this->size($r);

        [$query, $cols, $filters, $note] = $this->build($type, $r);
        if (in_array($r->get('export'), ['csv', 'xlsx', 'pdf'], true)) return $this->export($r->get('export'), $type, $query, $cols);
        $per = in_array((int) $r->per, [10, 25, 50, 100]) ? (int) $r->per : 100;
        return view('admin.reports.show', [
            'type' => $type, 'title' => self::TYPES[$type], 'rows' => $query->paginate($per)->withQueryString(), 'cols' => $cols,
            'filters' => $filters, 'opts' => $this->options(), 'note' => $note,
        ]);
    }

    /* ---------------- No. of family member wise ---------------- */
    private function size(Request $r)
    {
        $counts = Family::where('status', 'active')->selectRaw('member_count, count(*) c')->groupBy('member_count')->pluck('c', 'member_count');
        $size = (int) $r->get('size');
        if (! $size && in_array($r->get('export'), ['csv', 'xlsx', 'pdf'], true)) {
            $gen = (function () use ($counts) { foreach (range(1, 25) as $n) yield [$n, (int) ($counts[$n] ?? 0)]; })();
            return Exporter::download($r->get('export'), 'abmm-family-member-wise', 'Statistics Family Member Wise', ['No of member(s)', 'Total Families'], $gen);
        }
        $rows = null;
        if ($size) {
            $q = Family::where('status', 'active')->where('member_count', $size)->orderBy('surname');
            if (in_array($r->get('export'), ['csv', 'xlsx', 'pdf'], true)) {
                $rowsGen = (function () use ($q) { foreach ($q->orderBy('id')->lazy(1000) as $f) yield [$f->family_code, $f->surname . ' ' . $f->head_name, $f->mobile, $f->district, $f->state, $f->member_count]; })();
                return Exporter::download($r->get('export'), "abmm-family-size-$size", "Families with $size member(s)", ['Family ID', 'Head', 'Mobile', 'District', 'State', 'Members'], $rowsGen);
            }
            $rows = $q->paginate(50)->withQueryString();
        }
        return view('admin.reports.size', ['counts' => $counts, 'size' => $size, 'rows' => $rows, 'title' => 'Statistics Family Member Wise']);
    }

    /* ---------------- builders ---------------- */
    private function build(string $type, Request $r): array
    {
        $addr = fn ($f) => trim(($f->address ?? '') . "\nCountry : " . $f->country . "\nGovt. State : " . $f->state . "\nABMM Anchal : " . (\App\Models\Lookup::anchalOf($f->pradesh) ?? '') . "\nABMM Pradesh : " . $f->pradesh . "\nDistrict : " . $f->district . "\nTehsil : " . $f->tehsil . "\nPincode : " . $f->pincode . "\nReg. Date: " . $f->created_at?->format('d-M-Y') . "\nLast Updated: " . ($f->updated_at->eq($f->created_at) ? 'N/A' : $f->updated_at?->format('d-M-Y')));
        $pr = fn ($kind, $id, $print = false) => route('admin.print', [$kind, $id]) . ($print ? '?print=1' : '');
        $memberActs = fn ($m) => $this->actions(
            $this->pill('View', '🔍', 'ok', $pr('member', $m->id)), $this->pill('Print', '🖨', 'info', $pr('member', $m->id, true)),
            $this->postPill($m->status === 'inactive' ? 'Activate' : 'De-Activate', $m->status === 'inactive' ? '✓' : '✕', $m->status === 'inactive' ? 'ok' : 'bad', route('admin.members.toggle', $m), [], $m->status === 'inactive' ? null : 'Deactivate this member?'));
        $familyActs = fn ($f) => $this->actions(
            $this->pill('View', '🔍', 'ok', $pr('head', $f->id)), $this->pill('Print', '🖨', 'info', $pr('head', $f->id, true)), $this->pill('Family Tree', '🌳', 'warn', $pr('family', $f->id)),
            $this->postPill($f->status === 'inactive' ? 'Activate' : 'De-Activate', $f->status === 'inactive' ? '✓' : '✕', $f->status === 'inactive' ? 'ok' : 'bad', route('admin.families.toggle', $f), [], $f->status === 'inactive' ? null : 'Deactivate the whole family?'));
        $movedActs = fn ($m) => $this->actions($this->pill('View', '🔍', 'ok', $pr('member', $m->id)), $this->pill('Print', '🖨', 'info', $pr('member', $m->id, true)));

        switch ($type) {
            case 'member':
                $q = $this->memberQuery($r)->whereIn('status', ['active', 'inactive']);
                return [$q->orderBy('family_id')->orderByDesc('is_head'), [
                    'Family ID' => fn ($m) => $m->family?->family_code, 'Name' => fn ($m) => $m->name, 'Mobile' => fn ($m) => $m->mobile ?: 'NA', 'Email' => fn ($m) => $m->email ?: 'Please Update',
                    'Address' => fn ($m) => $m->family ? $addr($m->family) . "\nMember ID: " . $m->member_code : '', 'Members' => fn ($m) => $m->family?->member_count,
                    'Action' => $memberActs,
                ], $this->commonFilters(['q', 'anchal', 'pradesh', 'tehsil', 'state', 'blood_group', 'business_type', 'education', 'gender', 'occupation', 'profession', 'surname', 'gotra', 'marital_status', 'age_group', 'letter', 'age_from', 'age_to', 'income', 'dates']),
                 'Inactive records will not come in Excel or other downloadable report.'];

            case 'family':
                return [$this->familyQuery($r)->whereIn('status', ['active', 'inactive'])->orderBy('surname'), [
                    'Family ID' => 'family_code', 'Family Head' => fn ($f) => $f->surname . ' ' . $f->head_name, 'Mobile' => 'mobile', 'Address' => $addr, 'Members' => 'member_count', 'Action' => $familyActs,
                ], $this->commonFilters(['q', 'anchal', 'pradesh', 'tehsil', 'state', 'blood_group', 'business_type', 'education', 'gender', 'occupation', 'profession', 'surname', 'gotra', 'marital_status', 'age_group', 'letter', 'age_from', 'age_to', 'income', 'resident_type', 'mediclaim', 'pakshik', 'dates']),
                 'Inactive records will not come in Excel or other downloadable report.'];

            case 'moved':
                // Hanging Data: members that need action (default), or the archive of members moved out of families.
                $issues = ($r->show ?? 'issues') !== 'archived';
                $q = $this->memberQuery($r)->when($issues,
                    fn ($q) => $q->whereIn('status', ['active', 'inactive'])->where(fn ($w) => $this->hangingRules($w))->orderBy('family_id'),
                    fn ($q) => $q->where('status', 'archived')->when($r->archive_type, fn ($q, $v) => $q->where('archive_type', $v))->orderByDesc('archived_at'));
                return [$q, [
                    'Family ID' => fn ($m) => $m->family?->family_code, 'Name' => fn ($m) => trim($m->surname . ' ' . $m->name), 'Mobile' => fn ($m) => $m->mobile ?: 'Please Update',
                    ($issues ? 'Issue' : 'Archive Type') => $issues ? fn ($m) => $this->hangingIssue($m) : 'archive_type',
                    'Address' => fn ($m) => $m->family ? trim($m->family->address . "\nCountry : " . $m->family->country . "\nGovt. State : " . $m->family->state . "\nDistrict : " . $m->family->district . "\nTehsil : " . $m->family->tehsil . "\nPincode : " . $m->family->pincode) : '',
                    'Action' => $issues ? fn ($m) => $this->hangingActions($m, $r) : $movedActs,
                ], $this->commonFilters(['show', 'q', 'anchal', 'pradesh', 'tehsil', 'blood_group', 'business_type', 'education', 'gender', 'occupation', 'profession', 'surname', 'gotra', 'marital_status', 'age_group', 'letter', 'archive_type']),
                 $issues ? 'Hanging Data: head changed, family address missing, or married daughters (check their address). Super Admin / State Sub-Admin can Migrate a member to another family (enter its Family ID), or mark an expired member as Died, which removes them from the family list.' : 'Members moved out of their families (archive).'];

            case 'committee':
                $q = CommitteeMember::query()->orderBy('level')->orderBy('name');
                $has = fn ($k) => $r->filled($k);
                if ($has('tehsil') || $has('designation')) {
                    $or = $r->condition === 'or';
                    $q->where(function ($w) use ($r, $or) {
                        if ($r->filled('tehsil')) $w->where('tehsil', $r->tehsil);
                        if ($r->filled('designation')) $or ? $w->orWhere('designation', $r->designation) : $w->where('designation', $r->designation);
                    });
                }
                $q->when($r->level, fn ($q, $v) => $q->where('level', $v))->when($r->q, fn ($q, $t) => $q->where('name', 'like', "%$t%"));
                return [$q, [
                    'Member Type' => fn ($m) => ucfirst($m->level), 'Name' => 'name', 'Designation' => 'designation', 'Mobile' => 'mobile', 'Date Of Birth' => fn ($m) => $m->dob?->format('d-M-Y'), 'Address' => 'address',
                ], [['tehsil', 'Tehsil', 'lookup'], ['designation', 'Designation', 'lookup'], ['condition', 'Select Condition', 'select', ['and' => 'And', 'or' => 'Or']], ['level', 'Member Type', 'select', ['country' => 'Country', 'pradesh' => 'ABMM Pradesh', 'state' => 'State', 'district' => 'District', 'tehsil' => 'Tehsil']], ['q', 'Name', 'text']], null];

            case 'business':
                return [Business::query()->when($r->member_code, fn ($q, $v) => $q->where('member_code', $v))
                    ->when($r->family_id, fn ($q, $v) => $q->whereHas('family', fn ($f) => $f->where('family_code', $v)))
                    ->when($r->status, fn ($q, $v) => $q->where('status', $v))->tap(fn ($q) => $this->dates($q, $r))->latest(), [
                    'Member Id' => 'member_code', 'Family ID' => fn ($b) => $b->family?->family_code, 'Business Name' => 'name', 'Business type' => 'type', 'Address' => 'address',
                    'Contact Details' => fn ($b) => "Email : {$b->email}\nMobile : {$b->mobile}", 'Status' => fn ($b) => ucfirst($b->status),
                    'Action' => fn ($b) => $this->actions($this->sameTab('View', '🔍', 'ok', route('admin.business.edit', $b)), $this->pill('Print', '🖨', 'info', $pr('business', $b->id, true))),
                ], [['member_code', 'Member Id', 'text'], ['family_id', 'Family ID', 'text'], ['status', 'Status', 'select', ['active' => 'Active', 'pending' => 'Pending', 'blocked' => 'Blocked']], ['dates', '', 'dates']], null];

            case 'ads':
                return [BusinessAd::with('business')->when($r->member_code, fn ($q, $v) => $q->where('member_code', $v))->when($r->status, fn ($q, $v) => $q->where('status', $v))->tap(fn ($q) => $this->dates($q, $r))->latest(), [
                    'Member Id' => 'member_code', 'Name' => 'title', 'Location' => 'location', 'Contact Person' => fn ($a) => "Contact Person: {$a->contact_person}\nMobile: {$a->contact_mobile}", 'Status' => fn ($a) => ucfirst($a->status),
                    'Action' => fn ($a) => $this->actions($this->pill('View', '🔍', 'ok', $pr('ad', $a->id)), $this->pill('Print', '🖨', 'info', $pr('ad', $a->id, true))),
                ], [['member_code', 'Member Id', 'text'], ['status', 'Status', 'select', ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected']], ['dates', '', 'dates']], null];

            case 'reviews':
                return [BusinessReview::with('business')->when($r->status, fn ($q, $v) => $q->where('status', $v))->tap(fn ($q) => $this->dates($q, $r))->latest(), [
                    'Business Name' => fn ($x) => $x->business?->name, 'Contact Details' => fn ($x) => "Email : {$x->business?->email}\nMobile : {$x->business?->mobile}", 'Status' => fn ($x) => ucfirst($x->status),
                    'Message' => fn ($x) => "{$x->comment}\nName: {$x->reviewer_name}\nMobile: {$x->reviewer_mobile}\nDate: " . $x->created_at?->format('d-M-Y'),
                    'Action' => fn ($x) => $this->actions(
                        $this->sameTab('Edit', '✎', 'warn', route('admin.business.reviews.edit', $x)),
                        $this->postPill('Approve', '✓', 'ok', route('admin.business.reviews.status', $x), ['status' => 'approved'], null, 'PATCH'),
                        $this->postPill('Deny', '🗑', 'bad', route('admin.business.reviews.status', $x), ['status' => 'rejected'], 'Deny this review?', 'PATCH')),
                ], [['status', 'Status', 'select', ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected']], ['dates', '', 'dates']], null];

            case 'adpay':
                return [BusinessAd::with('business')->where('amount', '>', 0)->tap(fn ($q) => $this->dates($q, $r))->latest(), [
                    'Date' => fn ($a) => $a->created_at?->format('d-M-Y'), 'Business' => fn ($a) => $a->business?->name, 'Amount' => 'amount', 'Payment Id' => 'payment_ref', 'Status' => 'status',
                ], [['dates', '', 'dates']], null];
        }
        abort(404);
    }

    private function dates($q, Request $r)
    {
        return $q->when($r->from && $r->to, fn ($q) => $q->whereBetween('created_at', [$r->from . ' 00:00:00', $r->to . ' 23:59:59']));
    }

    /** Anchal (= every ABMM Pradesh under it) and ABMM Pradesh filters on a families query. */
    private function area($q, Request $r)
    {
        if ($r->filled('anchal')) {
            $list = Lookup::pradeshesOf($r->anchal);
            $list ? $q->whereIn('pradesh', $list) : $q->whereRaw('1 = 0');
        }
        if ($r->filled('pradesh')) $q->where('pradesh', $r->pradesh);
        return $q;
    }

    private function ageRange($q, Request $r, string $col)
    {
        if ($r->filled('age_group') && preg_match('/^(\d+)-(\d+)$/', $r->age_group, $m)) $this->byAge($q, $col, (int) $m[1], (int) $m[2]);
        if ($r->filled('age_from') || $r->filled('age_to')) $this->byAge($q, $col, (int) ($r->age_from ?: 0), (int) ($r->age_to ?: 150));
        return $q;
    }

    private function byAge($q, string $col, int $from, int $to): void
    {
        $q->whereBetween($col, [now()->subYears($to + 1)->addDay()->toDateString(), now()->subYears($from)->toDateString()]);
    }

    private function memberQuery(Request $r)
    {
        $q = Member::with('family')
            // Gotra is usually entered once on the family, so a member matches on own gotra or the family's.
            ->when($r->gotra, fn ($q, $v) => $q->where(fn ($w) => $w->where('gotra', $v)->orWhereHas('family', fn ($f) => $f->where('gotra', $v))))
            ->when($r->blood_group, fn ($q, $v) => $q->where('blood_group', $v))->when($r->business_type, fn ($q, $v) => $q->where('business_type', $v))
            ->when($r->education, fn ($q, $v) => $q->where('education', $v))->when($r->gender, fn ($q, $v) => $q->where('gender', ucfirst(strtolower($v))))
            ->when($r->occupation, fn ($q, $v) => $q->where('occupation', $v))->when($r->profession, fn ($q, $v) => $q->where('profession', $v))
            ->when($r->surname, fn ($q, $v) => $q->where('surname', $v))->when($r->marital_status, fn ($q, $v) => $q->where('marital_status', $v))
            ->when($r->letter, fn ($q, $v) => $q->where('surname', 'like', "$v%"))
            ->when($r->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('surname', 'like', "$t%")->orWhere('name', 'like', "%$t%")->orWhere('mobile', 'like', "%$t%")));
        $this->ageRange($q, $r, 'dob');
        $q->whereHas('family', function ($f) use ($r) {
            $this->area($f, $r);
            $f->when($r->tehsil, fn ($x, $v) => $x->where('tehsil', $v))->when($r->state, fn ($x, $v) => $x->where('state', $v))->when($r->income, fn ($x, $v) => $x->where('income_range', $v));
            $this->dates($f, $r);
        });
        return $q;
    }

    private function familyQuery(Request $r)
    {
        $q = Family::query()
            ->when($r->tehsil, fn ($q, $v) => $q->where('tehsil', $v))->when($r->state, fn ($q, $v) => $q->where('state', $v))
            ->when($r->gotra, fn ($q, $v) => $q->where('gotra', $v))->when($r->blood_group, fn ($q, $v) => $q->where('blood_group', $v))->when($r->business_type, fn ($q, $v) => $q->where('business_type', $v))
            ->when($r->education, fn ($q, $v) => $q->where('education', $v))->when($r->gender, fn ($q, $v) => $q->where('gender', ucfirst(strtolower($v))))
            ->when($r->occupation, fn ($q, $v) => $q->where('occupation', $v))->when($r->profession, fn ($q, $v) => $q->where('profession', $v))
            ->when($r->surname, fn ($q, $v) => $q->where('surname', $v))->when($r->marital_status, fn ($q, $v) => $q->where('marital_status', $v))
            ->when($r->income, fn ($q, $v) => $q->where('income_range', $v))->when($r->resident_type, fn ($q, $v) => $q->where('resident_type', $v))
            ->when($r->letter, fn ($q, $v) => $q->where('surname', 'like', "$v%"))
            ->when($r->mediclaim !== null && $r->mediclaim !== '', fn ($q) => $q->where('has_mediclaim', $r->mediclaim === 'yes'))
            ->when($r->pakshik !== null && $r->pakshik !== '', fn ($q) => $q->where('pakshik_member', $r->pakshik === 'yes'))
            ->search($r->q);
        $this->area($q, $r);
        $this->ageRange($q, $r, 'dob');
        return $this->dates($q, $r);
    }

    /* ---------------- filter definitions / options ---------------- */
    private function commonFilters(array $keys): array
    {
        $def = [
            'q' => ['q', 'Enter Surname then First Name or Mobile', 'text'], 'anchal' => ['anchal', 'ABMM Anchal', 'lookup'], 'pradesh' => ['pradesh', 'ABMM Pradesh', 'lookup'], 'tehsil' => ['tehsil', 'Tehsil', 'lookup'], 'state' => ['state', 'Govt. State', 'lookup'],
            'blood_group' => ['blood_group', 'Blood Group', 'lookup'], 'business_type' => ['business_type', 'Business Type', 'lookup'], 'education' => ['education', 'Education', 'lookup'],
            'gender' => ['gender', 'Gender', 'lookup'], 'occupation' => ['occupation', 'Occupation', 'lookup'],
            'profession' => ['profession', 'Profession', 'lookup'], 'surname' => ['surname', 'Surname', 'lookup'], 'gotra' => ['gotra', 'Gotra', 'lookup'],
            'marital_status' => ['marital_status', 'Marital Status', 'lookup'],
            'age_group' => ['age_group', 'Age Group', 'select', array_combine(self::AGE_GROUPS, array_map(fn ($a) => "$a Years", self::AGE_GROUPS))],
            'letter' => ['letter', 'Alphabetically', 'select', array_combine(range('A', 'Z'), range('A', 'Z'))],
            'age_from' => ['age_from', 'From Age (Year)', 'select', array_combine(range(1, 100), range(1, 100))], 'age_to' => ['age_to', 'To Age (Year)', 'select', array_combine(range(2, 100), range(2, 100))],
            'income' => ['income', 'Income', 'lookup'], 'resident_type' => ['resident_type', 'Resident Type', 'lookup'],
            'mediclaim' => ['mediclaim', 'Do All Family Have Mediclaim?', 'select', ['yes' => 'Yes', 'no' => 'No']], 'pakshik' => ['pakshik', 'Member Of Maheshwari Pakshik?', 'select', ['yes' => 'Yes', 'no' => 'No']],
            'archive_type' => ['archive_type', 'Archive Type', 'select', array_combine(Member::ARCHIVE_TYPES, Member::ARCHIVE_TYPES)], 'dates' => ['dates', '', 'dates'],
            'show' => ['show', 'Hanging Data view', 'select', ['issues' => 'Needs action', 'archived' => 'Moved out (archive)']],
        ];
        return array_map(fn ($k) => $def[$k], $keys);
    }

    /** JSON rows for the app: the same query, filters and columns as the report page (no action buttons). */
    public function apiRows(string $type, Request $r): array
    {
        abort_unless(isset(self::TYPES[$type]) && ! in_array($type, ['parinay', 'archive-parinay', 'size'], true), 404);
        [$query, $cols] = $this->build($type, $r);
        $cols = array_filter($cols, fn ($k) => $k !== 'Action', ARRAY_FILTER_USE_KEY);
        $per = min(100, max(10, $r->integer('per_page', 50)));
        $page = $query->paginate($per)->withQueryString();
        return [
            'title' => self::TYPES[$type],
            'columns' => array_keys($cols),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'data' => collect($page->items())->map(fn ($row) => array_map(fn ($c) => (string) (is_callable($c) ? $c($row) : $row->{$c}), array_values($cols)))->values(),
        ];
    }

    /** Dropdown options. Non-super users only see places inside their own area (the Family query is area-scoped). */
    private function options(): array
    {
        $opts = Lookup::grouped();
        $u = auth()->user();
        if (! $u || $u->isSuper()) return $opts;
        foreach (['state', 'tehsil', 'pradesh', 'city', 'district'] as $c) {
            $opts[$c] = Family::query()->whereNotNull($c)->where($c, '!=', '')->distinct()->orderBy($c)->pluck($c)->values()->all();
        }
        $opts['anchal'] = array_values(array_filter(array_keys(Lookup::anchalPradesh()), fn ($a) => Lookup::anchalOf($u->pradesh) === $a));
        return $opts;
    }

    /** Hanging Data rules: a member is listed when the head changed, the family address is missing, or a married daughter needs an address check. */
    private function hangingRules($w): void
    {
        $w->where(fn ($x) => $x->where('is_head', true)->whereHas('family', fn ($f) => $f->whereColumn('families.head_name', '!=', 'members.name')))
            ->orWhereHas('family', fn ($f) => $f->where(fn ($a) => $a->whereNull('address')->orWhere('address', '')))
            ->orWhere(fn ($x) => $x->where('gender', 'Female')->where('marital_status', 'Married')->where('relation', 'DAUGHTER'));
    }

    private function hangingIssue($m): string
    {
        $parts = [];
        if ($m->is_head && $m->family && $m->family->head_name !== $m->name) $parts[] = 'Head changed';
        if ($m->family && trim((string) $m->family->address) === '') $parts[] = 'Address missing';
        if ($m->gender === 'Female' && $m->marital_status === 'Married' && $m->relation === 'DAUGHTER') $parts[] = 'Married: check address';
        return implode(', ', $parts) ?: '-';
    }

    private function hangingActions($m, Request $r): HtmlString
    {
        $buttons = [
            $this->pill('View', '🔍', 'ok', route('admin.print', ['member', $m->id])),
            $this->pill('Print', '🖨', 'info', route('admin.print', ['member', $m->id]) . '?print=1'),
        ];
        if ($r->user()->isSuper() || $r->user()->isStateAdmin()) {
            $buttons[] = '<form class="inline" method="post" action="' . e(route('admin.families.members.migrate', [$m->family_id, $m->id])) . '">' . csrf_field()
                . '<input name="target_family_code" required placeholder="Family ID" class="input !w-28 !min-h-[30px] !py-1 text-xs"><button class="act act-ink" title="Migrate to another family">⇄</button></form>';
            $buttons[] = $this->postPill('Died', '✝', 'bad', route('admin.families.members.archive', [$m->family_id, $m->id]), ['archive_type' => 'Died'], 'Mark this member as Died and remove them from the family list?');
        }
        return $this->actions(...$buttons);
    }

    /* ---------------- export ---------------- */
    private function export(string $format, string $type, $query, array $cols)
    {
        $cols = array_filter($cols, fn ($k) => $k !== 'Action', ARRAY_FILTER_USE_KEY);
        if (in_array($type, ['member', 'family'], true)) $query->where('status', 'active'); // inactive rows never go into downloads
        $query->orderBy($query->getModel()->getTable() . '.id');
        // Custom Form Fields (admin-defined) become extra columns in every download.
        if (in_array($type, ['member', 'family'], true)) {
            foreach (\App\Support\CustomForm::fields($type) as $cf) {
                $label = isset($cols[$cf['label']]) ? $cf['label'] . ' (custom)' : $cf['label'];
                $cols[$label] = fn ($row) => $cf['type'] === 'checkbox' ? (($row->extra[$cf['key']] ?? null) ? 'Yes' : 'No') : (string) (is_array($v = $row->extra[$cf['key']] ?? '') ? implode(', ', $v) : $v);
            }
        }
        $rows = (function () use ($query, $cols) {
            foreach ($query->lazy(1000) as $row) {
                yield array_map(fn ($c) => (string) (is_callable($c) ? $c($row) : $row->{$c}), array_values($cols));
            }
        })();
        return Exporter::download($format, "abmm-$type", self::TYPES[$type] . ' Report', array_keys($cols), $rows);
    }

    /* ---------------- row action buttons (same icons/order as the original) ---------------- */
    private function pill(string $title, string $icon, string $cls, string $url): string
    {
        return '<a class="act act-' . $cls . '" title="' . e($title) . '" target="_blank" rel="noopener" href="' . e($url) . '">' . $icon . '</a>';
    }

    private function sameTab(string $title, string $icon, string $cls, string $url): string
    {
        return '<a class="act act-' . $cls . '" title="' . e($title) . '" href="' . e($url) . '">' . $icon . '</a>';
    }

    private function postPill(string $title, string $icon, string $cls, string $url, array $fields = [], ?string $confirm = null, string $method = 'POST'): string
    {
        $h = '<form class="inline" method="post" action="' . e($url) . '"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field();
        if ($method !== 'POST') $h .= method_field($method);
        foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        return $h . '<button class="act act-' . $cls . '" title="' . e($title) . '">' . $icon . '</button></form>';
    }

    private function actions(string ...$parts): HtmlString
    {
        return new HtmlString('<div class="acts">' . implode('', $parts) . '</div>');
    }
}
