<?php

namespace App\Support;

use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/**
 * "+ Add" for admin screens that normally fill up from the member app (mentors, drives, donors, relief requests, volunteers, investors, pitches,
 * partners, vendors, businesses, products, groups, ads, bookings, course enrolments).
 * The admin types the member's registered mobile number; the family, state / district / city / zone are copied from that member, so a sub-admin
 * who adds a record automatically keeps it inside his own area. Screens that are system logs (payments, claims, reports, tickets, chats, results)
 * deliberately have no manual add.
 */
class ManualAdd
{
    private static function t(string $l, bool $n = true): array { return [$l, 'text', 'nullable' => $n]; }
    private static function ta(string $l, bool $n = true): array { return [$l, 'textarea', 'nullable' => $n]; }
    private static function num(string $l, bool $n = true): array { return [$l, 'number', 'nullable' => $n]; }
    private static function dt(string $l, bool $n = true): array { return [$l, 'date', 'nullable' => $n]; }
    private static function yn(string $l): array { return [$l, 'type' => 'checkbox']; }
    private static function sel(string $l, array $o, bool $n = false): array { return [$l, 'select', 'options' => $o, 'nullable' => $n]; }

    private static function area(string $table): array
    {
        $f = ['country' => self::t('Country'), 'pradesh' => self::t('State'), 'district' => self::t('District'), 'city' => self::t('City'), 'zone' => self::t('Zone')];
        return array_filter($f, fn ($k) => Schema::hasColumn($table, $k), ARRAY_FILTER_USE_KEY);
    }

    private static function config(): array
    {
        $statusBiz = ['pending' => 'Pending', 'active' => 'Active', 'blocked' => 'Blocked'];
        return [
            'drives' => ['link' => 'member', 'fields' => ['drive_type' => self::sel('Type', ['campus' => 'Campus placement', 'internship' => 'Internship', 'walkin' => 'Walk-in']), 'title' => self::t('Title', false), 'institution' => self::t('Institution'), 'company' => self::t('Company'),
                'drive_date' => self::dt('Drive date'), 'last_date' => self::dt('Last date to register'), 'positions' => self::num('Positions'), 'stipend' => self::t('Stipend / package'), 'eligibility' => self::t('Eligibility'), 'description' => self::ta('Description'), 'venue' => self::t('Venue'), 'contact_phone' => self::t('Contact phone')]],
            'mentors' => ['link' => 'member', 'unique' => ['user_id'], 'fields' => ['experience_years' => self::num('Experience (years)'), 'languages' => self::t('Languages'), 'mode' => self::sel('Mode', ['online' => 'Online', 'offline' => 'Offline', 'both' => 'Both'], true), 'bio' => self::ta('Bio'), 'linkedin' => self::t('LinkedIn URL')]],
            'blooddonors' => ['link' => 'member', 'unique' => ['user_id'], 'fields' => ['blood_group' => self::sel('Blood group', array_combine(\App\Services\BloodService::GROUPS, \App\Services\BloodService::GROUPS)), 'last_donated_on' => self::dt('Last donated on'), 'available' => self::yn('Available to donate'), 'emergency_ok' => self::yn('Available for emergencies'), 'pincode' => self::t('Pincode'), 'note' => self::t('Note'),
                'status' => self::sel('Status', ['active' => 'Active', 'blocked' => 'Blocked'])], 'defaults' => ['available' => 1, 'status' => 'active']],
            'assistance' => ['link' => 'member', 'fields' => ['kind' => self::sel('Kind', ['medical' => 'Medical', 'education' => 'Education', 'relief' => 'Relief', 'other' => 'Other']), 'title' => self::t('Title', false), 'beneficiary' => self::t('Beneficiary'), 'details' => self::ta('Details', false), 'amount_needed' => self::num('Amount needed (₹)')]],
            'volunteers' => ['link' => 'member', 'fields' => ['skills' => self::t('Skills'), 'availability' => self::t('Availability'), 'note' => self::t('Note')]],
            'investors' => ['link' => 'member', 'unique' => ['user_id'], 'fields' => ['role' => self::sel('Role', ['investor' => 'Investor', 'entrepreneur' => 'Entrepreneur', 'both' => 'Both']), 'company' => self::t('Company'), 'sectors' => self::t('Sectors'), 'stage_pref' => self::t('Preferred stage'), 'ticket_min' => self::num('Ticket size from'), 'ticket_max' => self::num('Ticket size to'), 'bio' => self::ta('Bio'), 'website' => self::t('Website'), 'linkedin' => self::t('LinkedIn URL')]],
            'pitches' => ['link' => 'member', 'fields' => ['title' => self::t('Title', false), 'company' => self::t('Company'), 'sector' => self::t('Sector'), 'stage' => self::t('Stage'), 'ask_amount' => self::num('Ask amount'), 'equity_pct' => self::num('Equity offered %'), 'currency' => self::t('Currency'), 'summary' => self::ta('Summary', false), 'deck_url' => self::t('Deck URL'), 'website' => self::t('Website')], 'defaults' => ['currency' => 'INR']],
            'bureaus' => ['link' => 'member', 'fields' => ['description' => self::ta('Description'), 'registration_no' => self::t('Registration no.')], 'defaults' => ['kind' => 'bureau'], 'unique' => ['user_id', 'kind']],
            'organisers' => ['link' => 'member', 'fields' => ['description' => self::ta('Description'), 'registration_no' => self::t('Registration no.')], 'defaults' => ['kind' => 'organiser'], 'unique' => ['user_id', 'kind']],
            'recruiters' => ['link' => 'member', 'fields' => ['description' => self::ta('Description'), 'registration_no' => self::t('Registration no.')], 'defaults' => ['kind' => 'recruiter'], 'unique' => ['user_id', 'kind']],
            'vendors' => ['link' => 'optional', 'fields' => ['category' => self::t('Category', false), 'name' => self::t('Name', false), 'contact_person' => self::t('Contact person'), 'phone' => self::t('Phone'), 'email' => self::t('Email'), 'description' => self::ta('Description'), 'services' => self::ta('Services'), 'price_from' => self::num('Price from'), 'currency' => self::t('Currency')], 'defaults' => ['currency' => 'INR']],
            'sellers' => ['link' => 'member', 'fields' => ['name' => self::t('Name', false), 'type' => self::t('Type / trade', false), 'org_kind' => self::sel('Kind', ['business' => 'Business', 'profit' => 'For-profit organisation', 'nonprofit' => 'Non-profit'], true), 'category_id' => self::catSel(), 'address' => self::ta('Address', false), 'mobile' => self::t('Mobile', false), 'email' => self::t('Email'), 'contact_person' => self::t('Contact person'),
                'description' => self::ta('Description'), 'reg_no' => self::t('Registration no.'), 'has_12a' => self::yn('Has 12A'), 'has_80g' => self::yn('Has 80G'), 'listing_until' => self::dt('Listing until'), 'status' => self::sel('Status', $statusBiz)], 'defaults' => ['status' => 'active']],
            'products' => ['link' => 'none', 'fields' => ['business_id' => ['Business', 'select', 'options' => fn () => self::businessOptions()], 'title' => self::t('Title', false), 'description' => self::ta('Description'), 'category_id' => self::catSel(), 'price' => self::num('Price'), 'currency' => self::t('Currency'), 'unit' => self::t('Unit'), 'moq' => self::num('Minimum order qty'), 'status' => self::sel('Status', ['active' => 'Active', 'pending' => 'Pending', 'blocked' => 'Blocked'])], 'defaults' => ['currency' => 'INR', 'status' => 'active'], 'noarea' => true],
            'groups' => ['link' => 'member', 'user' => 'owner_user_id', 'fields' => ['name' => self::t('Group name', false), 'description' => self::ta('Description'), 'country' => self::t('Country'), 'city' => self::t('City'), 'category_id' => self::catSel(), 'is_active' => self::yn('Active')], 'defaults' => ['is_active' => 1], 'noarea' => true],
            'ads' => ['link' => 'none', 'fields' => ['business_id' => ['Business', 'select', 'options' => fn () => self::businessOptions()], 'title' => self::t('Title', false), 'starts_on' => self::dt('Starts on', false), 'ends_on' => self::dt('Ends on', false), 'amount' => self::num('Amount (₹)'), 'link_url' => self::t('Link URL'), 'status' => self::sel('Status', ['pending' => 'Pending', 'approved' => 'Approved', 'paused' => 'Paused', 'rejected' => 'Rejected'])], 'defaults' => ['status' => 'approved'], 'noarea' => true],
            'bookings' => ['link' => 'member', 'fields' => ['entry_id' => ['Place (bhavan / hotel / hospital)', 'select', 'options' => fn () => DB::table('directory_entries')->orderBy('name')->pluck('name', 'id')->all()], 'from_date' => self::dt('From', false), 'to_date' => self::dt('To', false), 'guests' => self::num('Guests'), 'purpose' => self::t('Purpose'), 'status' => self::sel('Status', ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'])], 'defaults' => ['status' => 'confirmed'], 'noarea' => true],
            'enrolments' => ['link' => 'member', 'fields' => ['course_id' => ['Course', 'select', 'options' => fn () => DB::table('courses')->orderBy('title')->pluck('title', 'id')->all()]], 'defaults' => ['source' => 'admin', 'status' => 'active'], 'noarea' => true, 'unique' => ['course_id', 'user_id']],
        ];
    }

    private static function catSel(): array
    {
        return ['Category', 'select', 'options' => fn () => DB::table('business_categories')->orderBy('name')->pluck('name', 'id')->all(), 'nullable' => true];
    }

    private static function businessOptions(): array
    {
        return \App\Http\Controllers\Admin\ManageController::scopeArea(DB::table('businesses'), 'businesses')->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function apply(array $all): array
    {
        foreach (self::config() as $key => $c) {
            if (! isset($all[$key])) continue;
            $s = &$all[$key];
            $table = $s['table'];
            $extra = $c['fields'] + (($c['noarea'] ?? false) ? [] : self::area($table));
            if (in_array($c['link'], ['member', 'optional'], true)) $extra = ['member_mobile' => [$c['link'] === 'member' ? 'Member mobile number (must be registered)' : 'Member mobile number (optional for Super Admin)', 'text', 'virtual' => true, 'nullable' => true]] + $extra;
            $s['form'] = $extra + ($s['form'] ?? []);
            $s['create'] = true; $s['readonly'] = false;
            $orig = $s['before'] ?? null;
            $s['before'] = function (array $d, ?int $id) use ($c, $table, $orig) {
                if (! $id) {
                    $d = self::link($d, $c, $table);
                    foreach ($c['defaults'] ?? [] as $k => $v) if (! isset($d[$k]) || $d[$k] === '') $d[$k] = $v;
                    $d = self::stamp($d, $table);
                    if (! empty($c['unique'])) {
                        $w = array_intersect_key($d, array_flip($c['unique']));
                        if (count($w) === count($c['unique']) && DB::table($table)->where($w)->exists()) throw ValidationException::withMessages([($c['unique'][0] === 'user_id' ? 'member_mobile' : $c['unique'][0]) => 'This member already has this record - edit the existing one instead.']);
                    }
                }
                unset($d['member_mobile']);
                return $orig ? $orig($d, $id) : $d;
            };
            unset($s);
        }
        return $all;
    }

    /** Finds the member by mobile and copies user / family / area onto the new row. */
    private static function link(array $d, array $c, string $table): array
    {
        $mode = $c['link'] ?? 'none';
        if ($mode === 'none') return $d;
        $actor = auth()->user();
        $mobile = substr(preg_replace('/\D/', '', (string) ($d['member_mobile'] ?? '')), -10);
        if ($mobile === '') {
            if ($mode === 'optional' && $actor?->isSuper()) return $d;
            throw ValidationException::withMessages(['member_mobile' => 'Enter the mobile number of the member this record belongs to.']);
        }
        $user = DB::table('users')->where('mobile', 'like', "%$mobile")->where('role', 'member')->first()
            ?? DB::table('users')->where('mobile', 'like', "%$mobile")->first();
        $fam = $user && $user->family_id ? DB::table('families')->where('id', $user->family_id)->first() : DB::table('families')->where('mobile', 'like', "%$mobile")->first();
        if (! $user && $fam) $user = DB::table('users')->where('family_id', $fam->id)->orderBy('id')->first();
        if (! $user || ! $fam) throw ValidationException::withMessages(['member_mobile' => 'No app member found with this mobile number. The family must register in the app first (or ask them to).']);
        if ($actor && ! $actor->isSuper() && ! $actor->areaFamilyIds()->where('id', $fam->id)->exists())
            throw ValidationException::withMessages(['member_mobile' => 'This member is outside your area.']);
        $cols = Schema::getColumnListing($table);
        if (in_array($c['user'] ?? 'user_id', $cols, true)) $d[$c['user'] ?? 'user_id'] = $user->id;
        if (in_array('family_id', $cols, true)) $d['family_id'] = $fam->id;
        $from = ['country' => $fam->country ?? null, 'pradesh' => $fam->pradesh ?: ($fam->state ?? null), 'district' => $fam->district ?? null, 'city' => $fam->city ?? null, 'zone' => $fam->zone ?? null];
        foreach ($from as $k => $v) if ($v && in_array($k, $cols, true) && empty($d[$k])) $d[$k] = $v;
        if (in_array('state', $cols, true) && empty($d['state']) && $from['pradesh']) $d['state'] = $from['pradesh'];
        return $d;
    }

    /** A sub-admin's own area fills whatever is still blank. */
    private static function stamp(array $d, string $table): array
    {
        $u = auth()->user();
        if (! $u || $u->isSuper()) return $d;
        $cols = Schema::getColumnListing($table);
        foreach ($u->areaFilter() as $k => $v) if (in_array($k, $cols, true) && empty($d[$k])) $d[$k] = $v;
        return $d;
    }
}
