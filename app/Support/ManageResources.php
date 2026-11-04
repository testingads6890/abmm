<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Config-driven admin screens (Milestone C): one list + form + row-actions controller (Admin\ManageController) serves all of these.
 * Per resource: title, perm (permission module), table, query (list builder with aliases), cols (label => column), form (field => spec), actions (row buttons that set columns), notify (message sent to a user column).
 */
class ManageResources
{
    private static function sel(string $table, string $col = 'name', string $where = '', string $label = ''): array
    {
        return ['type' => 'select', 'options' => fn () => DB::table($table)->when($where, fn ($q) => $q->whereRaw($where))->orderBy($col)->pluck($label ?: $col, 'id')->all()];
    }

    /** Marriage bureau / event organiser / job recruiter accounts (Partner dashboards). */
    private static function partnerRes(string $kind, string $title): array
    {
        $tbl = \App\Services\PartnerService::KINDS[$kind]['table'];
        return ['title' => $title, 'perm' => 'partners', 'table' => 'partners', 'order' => 'p.id desc', 'export' => true,
            'query' => fn () => DB::table('partners as p')->where('p.kind', $kind)->select('p.*',
                DB::raw("(select max(s.ends_on) from subscriptions s where s.subscriber_type = p.kind and s.subscriber_id = p.id and s.status = 'active') as sub_until"),
                DB::raw("(select count(*) from $tbl r where r.partner_id = p.id) as records"),
                DB::raw("(select count(*) from partner_enquiries e where e.partner_id = p.id) as leads")),
            'cols' => ['Name' => 'name', 'Contact' => 'contact_person', 'Phone' => 'phone', 'State' => 'pradesh', 'City' => 'city', 'Verified' => 'verified', 'Subscription till' => 'sub_until', 'Records' => 'records', 'Leads' => 'leads', 'Status' => 'status'],
            'filters' => ['status', 'pradesh', 'city'],
            'form' => ['name' => ['Name', 'text'], 'contact_person' => ['Contact person', 'text', 'nullable' => true], 'phone' => ['Phone', 'text', 'nullable' => true], 'email' => ['Email', 'text', 'nullable' => true],
                'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'active' => 'Active', 'blocked' => 'Blocked']], 'verified' => ['Verified (trust badge)', 'type' => 'checkbox']],
            'actions' => ['approve' => ['Approve & verify', 'set' => ['status' => 'active', 'verified' => 1], 'when' => ['pending'], 'perm' => 'verify', 'notify' => ['user_id', 'Your ' . $title . ' profile has been approved. Take a subscription to start using your dashboard.']],
                'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active', 'pending'], 'perm' => 'verify', 'notify' => ['user_id', 'Your ' . $title . ' profile has been blocked.']],
                'unblock' => ['Unblock', 'set' => ['status' => 'active'], 'when' => ['blocked'], 'perm' => 'verify']]];
    }

    public static function all(): array
    {
        $yn = ['type' => 'checkbox'];
        return ManualAdd::apply(array_merge(self::milestoneF(), self::milestoneE(), [
            'categories' => ['title' => 'Business categories', 'perm' => 'marketplace', 'table' => 'business_categories', 'order' => 'sort, name', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('business_categories as c')->leftJoin('business_categories as p', 'p.id', '=', 'c.parent_id')->select('c.*', DB::raw('p.name as parent')),
                'cols' => ['Name' => 'name', 'Parent' => 'parent', 'Slug' => 'slug', 'Sort' => 'sort', 'Active' => 'is_active'],
                'form' => ['name' => ['Name', 'text'], 'parent_id' => ['Parent category'] + self::sel('business_categories') + ['nullable' => true], 'sort' => ['Sort', 'number'], 'is_active' => ['Active'] + $yn],
                'before' => function (array $d, ?int $id) { $d['slug'] = Str::slug($d['name']); $i = 2; $base = $d['slug']; while (DB::table('business_categories')->where('slug', $d['slug'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) $d['slug'] = $base . '-' . $i++; return $d; }],

            'adpackages' => ['title' => 'Ad packages', 'perm' => 'adsmgr', 'table' => 'ad_packages', 'order' => 'sort, id', 'create' => true, 'delete' => true,
                'cols' => ['Name' => 'name', 'Type' => 'kind', 'Price' => 'price', 'Cur' => 'currency', 'Days' => 'days', 'Geo target' => 'geo_targeting', 'Active' => 'is_active'],
                'form' => ['name' => ['Name', 'text'], 'kind' => ['Type', 'select', 'options' => ['featured' => 'Featured', 'sponsored' => 'Sponsored', 'banner' => 'Home banner', 'category_top' => 'Category top', 'push' => 'Push notification']], 'price' => ['Price (tax inclusive)', 'number'],
                    'currency' => ['Currency', 'text'], 'days' => ['Days', 'number'], 'impression_cap' => ['Impression cap (optional)', 'number', 'nullable' => true], 'geo_targeting' => ['Allow city/zone targeting'] + $yn, 'description' => ['Description', 'textarea', 'nullable' => true], 'sort' => ['Sort', 'number'], 'is_active' => ['Active'] + $yn]],

            'ads' => ['title' => 'Advertisements (paid)', 'perm' => 'adsmgr', 'table' => 'business_ads', 'area_via' => ['business_id', 'businesses'], 'order' => 'a.id desc', 'readonly' => true,
                'query' => fn () => DB::table('business_ads as a')->join('businesses as b', 'b.id', '=', 'a.business_id')->select('a.*', DB::raw('b.name as business')),
                'cols' => ['#' => 'id', 'Business' => 'business', 'Title' => 'title', 'Type' => 'kind', 'From' => 'starts_on', 'To' => 'ends_on', 'Amount' => 'amount', 'Impr.' => 'impressions', 'Clicks' => 'clicks', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'approved'], 'when' => ['pending', 'paused'], 'perm' => 'approve'], 'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['pending'], 'perm' => 'approve'],
                    'pause' => ['Pause', 'set' => ['status' => 'paused'], 'when' => ['approved'], 'perm' => 'approve']]],

            'membercats' => ['title' => 'Membership categories', 'perm' => 'community', 'table' => 'membership_categories', 'order' => 'sort', 'create' => true,
                'cols' => ['Code' => 'code', 'Name' => 'name', 'Renewal plan #' => 'plan_id', 'Colour' => 'color', 'Active' => 'is_active'],
                'form' => ['code' => ['Code (a-z)', 'text'], 'name' => ['Name', 'text'], 'plan_id' => ['Renewal plan'] + self::sel('plans', 'name', "audience = 'member'") + ['nullable' => true], 'color' => ['Card colour', 'text'], 'perks' => ['Perks (shown on card)', 'textarea', 'nullable' => true], 'sort' => ['Sort', 'number'], 'is_active' => ['Active'] + $yn]],

            'benefits' => ['title' => 'Vouchers & member benefits', 'perm' => 'community', 'table' => 'benefits', 'order' => 'id desc', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('benefits as x')->leftJoin('businesses as b', 'b.id', '=', 'x.business_id')->select('x.*', DB::raw('b.name as partner')),
                'cols' => ['Title' => 'title', 'Partner' => 'partner', 'Offer' => 'discount_text', 'Claimed' => 'claimed_count', 'Limit' => 'total_limit', 'Valid to' => 'valid_to', 'Active' => 'is_active'],
                'form' => ['title' => ['Title', 'text'], 'description' => ['Details', 'textarea', 'nullable' => true], 'business_id' => ['Partner business'] + self::sel('businesses', 'name', "status = 'active'") + ['nullable' => true],
                    'kind' => ['Type', 'select', 'options' => ['voucher' => 'Voucher', 'discount' => 'Discount', 'service' => 'Free service']], 'discount_text' => ['Offer text (e.g. 10% off)', 'text', 'nullable' => true],
                    'elig_categories' => ['Eligible categories (codes, comma; blank = all)', 'text', 'virtual' => true, 'nullable' => true], 'elig_countries' => ['Countries (comma; blank = all)', 'text', 'virtual' => true, 'nullable' => true],
                    'elig_cities' => ['Cities (comma; blank = all)', 'text', 'virtual' => true, 'nullable' => true], 'elig_min_points' => ['Min points needed', 'number', 'virtual' => true, 'nullable' => true],
                    'elig_verified_only' => ['Verified members only'] + $yn + ['virtual' => true], 'elig_active_only' => ['Active (non-expired) members only'] + $yn + ['virtual' => true],
                    'per_member_limit' => ['Per member limit', 'number'], 'total_limit' => ['Total limit', 'number', 'nullable' => true], 'valid_from' => ['Valid from', 'date', 'nullable' => true], 'valid_to' => ['Valid to', 'date', 'nullable' => true], 'is_active' => ['Active'] + $yn],
                'before' => function (array $d, ?int $id) {
                    $csv = fn ($v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))));
                    $d['eligibility'] = json_encode(array_filter(['categories' => $csv($d['elig_categories'] ?? ''), 'countries' => $csv($d['elig_countries'] ?? ''), 'cities' => $csv($d['elig_cities'] ?? ''),
                        'min_points' => (int) ($d['elig_min_points'] ?? 0) ?: null, 'verified_only' => ! empty($d['elig_verified_only']), 'active_only' => ! empty($d['elig_active_only'])]));
                    foreach (['elig_categories', 'elig_countries', 'elig_cities', 'elig_min_points', 'elig_verified_only', 'elig_active_only'] as $k) unset($d[$k]);
                    return $d;
                },
                'fill' => function (object $row) { $e = json_decode($row->eligibility ?: '[]', true) ?: []; return ['elig_categories' => implode(',', $e['categories'] ?? []), 'elig_countries' => implode(',', $e['countries'] ?? []), 'elig_cities' => implode(',', $e['cities'] ?? []),
                    'elig_min_points' => $e['min_points'] ?? null, 'elig_verified_only' => $e['verified_only'] ?? false, 'elig_active_only' => $e['active_only'] ?? false]; }],

            'claims' => ['title' => 'Benefit redemptions (report)', 'perm' => 'community', 'table' => 'benefit_claims', 'order' => 'c.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('benefit_claims as c')->join('benefits as b', 'b.id', '=', 'c.benefit_id')->join('users as u', 'u.id', '=', 'c.user_id')->select('c.*', DB::raw('b.title as benefit'), DB::raw('u.name as member')),
                'cols' => ['Code' => 'code', 'Benefit' => 'benefit', 'Member' => 'member', 'Country' => 'country', 'City' => 'city', 'Status' => 'status', 'Claimed' => 'created_at', 'Redeemed' => 'redeemed_at'], 'filters' => ['status'],
                'actions' => ['void' => ['Void', 'set' => ['status' => 'void'], 'when' => ['claimed'], 'perm' => 'redeem', 'confirm' => 'Void this voucher?']]],

            'places' => ['title' => 'Directory: bhavan, hotel, hospital, clinic, doctor', 'perm' => 'directories', 'table' => 'directory_entries', 'order' => 'id desc', 'create' => true, 'delete' => true, 'export' => true,
                'cols' => ['Type' => 'kind', 'Name' => 'name', 'Country' => 'country', 'City' => 'city', 'Phone' => 'phone', 'Disc %' => 'member_discount_pct', 'Verified' => 'verified', 'Status' => 'status', 'Listing till' => 'listing_until'], 'filters' => ['kind', 'status'],
                'form' => ['kind' => ['Type', 'select', 'options' => ['bhavan' => 'Maheshwari Bhavan', 'dharamshala' => 'Dharamshala', 'hotel' => 'Hotel', 'hospital' => 'Hospital', 'clinic' => 'Clinic', 'doctor' => 'Doctor', 'ambulance' => 'Ambulance service', 'blood_bank' => 'Blood bank']], 'name' => ['Name', 'text'],
                    'parent_id' => ['Parent (doctor → hospital)'] + self::sel('directory_entries', 'name', "kind IN ('hospital','clinic')") + ['nullable' => true], 'speciality' => ['Speciality', 'text', 'nullable' => true],
                    'country' => ['Country', 'text', 'nullable' => true], 'state' => ['State', 'text', 'nullable' => true], 'city' => ['City', 'text', 'nullable' => true], 'zone' => ['Zone', 'text', 'nullable' => true], 'address' => ['Address', 'textarea', 'nullable' => true],
                    'phone' => ['Phone', 'text', 'nullable' => true], 'email' => ['Email', 'text', 'nullable' => true], 'website' => ['Website', 'text', 'nullable' => true], 'description' => ['Description', 'textarea', 'nullable' => true], 'rate_note' => ['Rates (note)', 'text', 'nullable' => true],
                    'member_discount_pct' => ['Member discount %', 'number'], 'verified' => ['Verified'] + $yn, 'verification_note' => ['Verification note', 'text', 'nullable' => true],
                    'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'active' => 'Active', 'blocked' => 'Blocked']], 'listing_until' => ['Listing valid till', 'date', 'nullable' => true]],
                'actions' => ['verify' => ['Verify & publish', 'set' => ['verified' => 1, 'status' => 'active'], 'when' => ['pending'], 'perm' => 'verify', 'notify' => ['owner_user_id', 'Your listing has been verified and is now live.']],
                    'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active', 'pending'], 'perm' => 'verify']]],

            'rooms' => ['title' => 'Halls & rooms (bhavan / hotel)', 'perm' => 'directories', 'table' => 'directory_rooms', 'area_via' => ['entry_id', 'directory_entries'], 'order' => 'r.id desc', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('directory_rooms as r')->join('directory_entries as e', 'e.id', '=', 'r.entry_id')->select('r.*', DB::raw('e.name as property')),
                'cols' => ['Property' => 'property', 'Name' => 'name', 'Type' => 'kind', 'Capacity' => 'capacity', 'Rate' => 'rate', 'Cur' => 'currency', 'Active' => 'is_active'],
                'form' => ['entry_id' => ['Property'] + self::sel('directory_entries', 'name', "kind IN ('bhavan','dharamshala','hotel')"), 'name' => ['Name', 'text'], 'kind' => ['Type', 'select', 'options' => ['hall' => 'Hall', 'room' => 'Room', 'dorm' => 'Dormitory']],
                    'capacity' => ['Capacity', 'number'], 'rate' => ['Rate per day', 'number'], 'currency' => ['Currency', 'text'], 'is_active' => ['Active'] + $yn]],

            'bookings' => ['title' => 'Booking enquiries', 'perm' => 'directories', 'table' => 'directory_bookings', 'order' => 'b.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('directory_bookings as b')->join('directory_entries as e', 'e.id', '=', 'b.entry_id')->join('users as u', 'u.id', '=', 'b.user_id')->select('b.*', DB::raw('e.name as property'), DB::raw('u.name as member'), DB::raw('u.mobile as mobile')),
                'cols' => ['Property' => 'property', 'Member' => 'member', 'Mobile' => 'mobile', 'From' => 'from_date', 'To' => 'to_date', 'Guests' => 'guests', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['confirm' => ['Confirm', 'set' => ['status' => 'confirmed'], 'when' => ['enquiry'], 'perm' => 'edit', 'notify' => ['user_id', 'Your booking has been confirmed.']], 'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['enquiry'], 'perm' => 'edit', 'notify' => ['user_id', 'Your booking enquiry was rejected.']]]],

            'sellers' => ['title' => 'Businesses & organisations (verification)', 'perm' => 'marketplace', 'table' => 'businesses', 'order' => 'b.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('businesses as b')->leftJoin('business_categories as c', 'c.id', '=', 'b.category_id')->select('b.*', DB::raw('c.name as category')),
                'cols' => ['Name' => 'name', 'Kind' => 'org_kind', 'Category' => 'category', 'City' => 'city', 'Reg no' => 'reg_no', '12A' => 'has_12a', '80G' => 'has_80g', 'Seller verified' => 'seller_verified', 'Featured' => 'is_featured', 'Listing till' => 'listing_until', 'Status' => 'status'], 'filters' => ['status', 'org_kind'],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'active'], 'when' => ['pending', 'blocked'], 'perm' => 'verify', 'notify' => ['__family', 'Your business/organisation has been approved.']],
                    'verify' => ['Verify seller', 'set' => ['seller_verified' => 1], 'when' => ['active'], 'perm' => 'verify', 'only' => ['seller_verified' => 0]], 'unverify' => ['Remove verification', 'set' => ['seller_verified' => 0], 'perm' => 'verify', 'only' => ['seller_verified' => 1]],
                    'feature' => ['Feature', 'set' => ['is_featured' => 1], 'perm' => 'verify', 'only' => ['is_featured' => 0]], 'unfeature' => ['Unfeature', 'set' => ['is_featured' => 0], 'perm' => 'verify', 'only' => ['is_featured' => 1]], 'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active'], 'perm' => 'verify']]],

            'products' => ['title' => 'Marketplace products', 'perm' => 'marketplace', 'table' => 'products', 'area_via' => ['business_id', 'businesses'], 'order' => 'p.id desc', 'readonly' => true,
                'query' => fn () => DB::table('products as p')->join('businesses as b', 'b.id', '=', 'p.business_id')->select('p.*', DB::raw('b.name as seller')),
                'cols' => ['Product' => 'title', 'Seller' => 'seller', 'Price' => 'price', 'MOQ' => 'moq', 'Views' => 'views', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'active'], 'when' => ['pending', 'blocked'], 'perm' => 'verify'], 'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active', 'pending'], 'perm' => 'verify']]],

            'quotations' => ['title' => 'Quotations / leads (all)', 'perm' => 'marketplace', 'table' => 'quotations', 'area_via' => ['business_id', 'businesses'], 'order' => 'q.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('quotations as q')->join('businesses as b', 'b.id', '=', 'q.business_id')->join('users as u', 'u.id', '=', 'q.buyer_user_id')->leftJoin('products as p', 'p.id', '=', 'q.product_id')->select('q.*', DB::raw('b.name as seller'), DB::raw('u.name as buyer'), DB::raw('p.title as product')),
                'cols' => ['Seller' => 'seller', 'Buyer' => 'buyer', 'Product' => 'product', 'Qty' => 'qty', 'Quoted' => 'quoted_price', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status']],

            'groups' => ['title' => 'Business networking groups', 'perm' => 'marketplace', 'table' => 'biz_groups', 'area_user' => 'owner_user_id', 'order' => 'g.id desc', 'readonly' => true,
                'query' => fn () => DB::table('biz_groups as g')->select('g.*', DB::raw('(SELECT COUNT(*) FROM biz_group_members m WHERE m.group_id = g.id) as members')),
                'cols' => ['Group' => 'name', 'City' => 'city', 'Country' => 'country', 'Members' => 'members', 'Active' => 'is_active'],
                'actions' => ['off' => ['Disable', 'set' => ['is_active' => 0], 'only' => ['is_active' => 1], 'perm' => 'edit'], 'on' => ['Enable', 'set' => ['is_active' => 1], 'only' => ['is_active' => 0], 'perm' => 'edit']]],

            'referrals' => ['title' => 'Business referrals (tracking)', 'perm' => 'marketplace', 'table' => 'biz_referrals', 'area_user' => 'from_user_id', 'order' => 'x.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('biz_referrals as x')->join('users as f', 'f.id', '=', 'x.from_user_id')->join('users as t', 't.id', '=', 'x.to_user_id')->select('x.*', DB::raw('f.name as giver'), DB::raw('t.name as receiver')),
                'cols' => ['Given by' => 'giver', 'To' => 'receiver', 'Description' => 'description', 'Expected' => 'expected_value', 'Status' => 'status', 'Closed value' => 'closed_value', 'Date' => 'created_at'], 'filters' => ['status']],

            'tickettypes' => ['title' => 'Ticket types', 'perm' => 'ticketing', 'table' => 'ticket_types', 'order' => 't.id desc', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('ticket_types as t')->join('events as e', 'e.id', '=', 't.event_id')->select('t.*', DB::raw('e.title as event')),
                'cols' => ['Event' => 'event', 'Name' => 'name', 'Price' => 'price', 'Cur' => 'currency', 'Quantity' => 'quantity', 'Max/order' => 'max_per_order', 'Sale from' => 'sale_starts', 'Sale to' => 'sale_ends', 'Active' => 'is_active'],
                'form' => ['event_id' => ['Event'] + self::sel('events', 'title'), 'name' => ['Ticket name (e.g. Adult, Child, VIP)', 'text'], 'price' => ['Price (0 = free, tax inclusive)', 'number'], 'currency' => ['Currency', 'text'],
                    'quantity' => ['Total seats (blank = unlimited)', 'number', 'nullable' => true], 'max_per_order' => ['Max per order', 'number'], 'sale_starts' => ['Sale starts', 'datetime', 'nullable' => true], 'sale_ends' => ['Sale ends', 'datetime', 'nullable' => true], 'is_active' => ['Active'] + $yn]],

            'orders' => ['title' => 'Ticket orders', 'perm' => 'ticketing', 'table' => 'ticket_orders', 'order' => 'o.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('ticket_orders as o')->join('events as e', 'e.id', '=', 'o.event_id')->join('users as u', 'u.id', '=', 'o.user_id')->select('o.*', DB::raw('e.title as event'), DB::raw('u.name as buyer'), DB::raw('u.mobile as mobile')),
                'cols' => ['Order' => 'number', 'Event' => 'event', 'Buyer' => 'buyer', 'Mobile' => 'mobile', 'Qty' => 'qty', 'Amount' => 'amount', 'Cur' => 'currency', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status']],

            'bureaus' => self::partnerRes('bureau', 'Marriage bureaus'),
            'organisers' => self::partnerRes('organiser', 'Event organisers'),
            'recruiters' => self::partnerRes('recruiter', 'Job recruiters'),
            'partnerenq' => ['title' => 'Partner enquiries / leads', 'perm' => 'partners', 'table' => 'partner_enquiries', 'order' => 'e.id desc', 'readonly' => true, 'area_via' => ['partner_id', 'partners'],
                'query' => fn () => DB::table('partner_enquiries as e')->join('partners as p', 'p.id', '=', 'e.partner_id')->select('e.*', DB::raw('p.name as partner'), DB::raw('p.kind as kind')),
                'cols' => ['Partner' => 'partner', 'Kind' => 'kind', 'From' => 'name', 'Phone' => 'phone', 'About' => 'subject_type', 'Message' => 'message', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status']],

            'profilereports' => ['title' => 'Profile reports (scam / fake)', 'perm' => 'parichay', 'table' => 'profile_reports', 'order' => 'r.id desc', 'readonly' => true,
                'query' => fn () => DB::table('profile_reports as r')->join('matrimony_profiles as p', 'p.id', '=', 'r.profile_id')->join('users as u', 'u.id', '=', 'r.reporter_id')->select('r.*', DB::raw('p.name as profile'), DB::raw('p.parinay_id as parinay_id'), DB::raw('p.status as profile_status'), DB::raw('u.name as reporter')),
                'cols' => ['Profile' => 'profile', 'Parinay ID' => 'parinay_id', 'Profile status' => 'profile_status', 'Reason' => 'reason', 'Detail' => 'detail', 'Reported by' => 'reporter', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status', 'reason'],
                'actions' => ['remove' => ['Remove profile', 'set' => ['status' => 'actioned'], 'when' => ['open'], 'perm' => 'remove', 'fraud_remove' => true, 'reason' => 'Reported and removed after review'],
                    'remove_block' => ['Remove + block mobile', 'set' => ['status' => 'actioned'], 'when' => ['open'], 'perm' => 'remove', 'fraud_remove' => true, 'block_mobile' => true, 'reason' => 'Scam / fake profile'],
                    'dismiss' => ['Dismiss', 'set' => ['status' => 'dismissed'], 'when' => ['open'], 'perm' => 'remove']]],

            'profileflags' => ['title' => 'Duplicate / fraud flags', 'perm' => 'parichay', 'table' => 'profile_flags', 'order' => 'f.id desc', 'readonly' => true,
                'query' => fn () => DB::table('profile_flags as f')->join('matrimony_profiles as p', 'p.id', '=', 'f.profile_id')->select('f.*', DB::raw('p.name as profile'), DB::raw('p.parinay_id as parinay_id'), DB::raw('p.mobile as mobile'), DB::raw('p.status as profile_status')),
                'cols' => ['Profile' => 'profile', 'Parinay ID' => 'parinay_id', 'Mobile' => 'mobile', 'Flag' => 'type', 'Detail' => 'detail', 'Profile status' => 'profile_status', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status', 'type'],
                'actions' => ['remove' => ['Remove profile', 'set' => ['status' => 'removed'], 'when' => ['open'], 'perm' => 'remove', 'fraud_remove' => true, 'reason' => 'Removed after fraud / duplicate check'],
                    'remove_block' => ['Remove + block mobile', 'set' => ['status' => 'removed'], 'when' => ['open'], 'perm' => 'remove', 'fraud_remove' => true, 'block_mobile' => true, 'reason' => 'Scam / fake profile'],
                    'clear' => ['Looks fine', 'set' => ['status' => 'cleared'], 'when' => ['open'], 'perm' => 'remove']]],

            'blocklist' => ['title' => 'Blocked mobiles / emails / names', 'perm' => 'parichay', 'table' => 'fraud_blocklist', 'order' => 'id desc', 'create' => true, 'delete' => true,
                'cols' => ['Kind' => 'kind', 'Value' => 'value', 'Reason' => 'reason', 'Added' => 'created_at'],
                'form' => ['kind' => ['Kind', 'select', 'options' => ['mobile' => 'Mobile', 'email' => 'E-mail', 'name' => 'Name']], 'value' => ['Value', 'text'], 'reason' => ['Reason', 'text', 'nullable' => true]],
                'before' => function (array $d, ?int $id) { $d['value'] = $d['kind'] === 'mobile' ? substr(preg_replace('/\D/', '', $d['value']), -10) : mb_strtolower(trim($d['value'])); if (! $id) $d['created_by'] = auth()->id(); return $d; }],

            'matchmsgs' => ['title' => 'Match chat messages (moderation)', 'perm' => 'parichay', 'table' => 'match_messages', 'order' => 'm.id desc', 'readonly' => true,
                'query' => fn () => DB::table('match_messages as m')->join('matrimony_profiles as p', 'p.id', '=', 'm.from_profile_id')->select('m.*', DB::raw('p.name as sender'), DB::raw('p.parinay_id as parinay_id')),
                'cols' => ['Sender' => 'sender', 'Parinay ID' => 'parinay_id', 'Message' => 'body', 'Hidden' => 'hidden', 'Date' => 'created_at'],
                'actions' => ['hide' => ['Hide message', 'set' => ['hidden' => 1], 'perm' => 'remove', 'only' => ['hidden' => 0]]]],

            'biodatauploads' => ['title' => 'Biodata uploads', 'perm' => 'parichay', 'table' => 'biodata_uploads', 'order' => 'b.id desc', 'readonly' => true,
                'query' => fn () => DB::table('biodata_uploads as b')->join('users as u', 'u.id', '=', 'b.user_id')->select('b.*', DB::raw('u.name as member'), DB::raw('u.mobile as mobile')),
                'cols' => ['Member' => 'member', 'Mobile' => 'mobile', 'Type' => 'mime', 'Read by' => 'method', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status']],

            'vendors' => ['title' => 'Event vendors', 'perm' => 'vendors', 'table' => 'event_vendors', 'order' => 'id desc', 'export' => true,
                'cols' => ['Name' => 'name', 'Category' => 'category', 'City' => 'city', 'Phone' => 'phone', 'Verified' => 'verified', 'Rating' => 'rating_avg', 'Views' => 'views', 'Listing till' => 'listing_until', 'Top till' => 'top_until', 'Status' => 'status'], 'filters' => ['status', 'category'],
                'form' => ['status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'active' => 'Active', 'blocked' => 'Blocked']], 'verified' => ['Verified'] + $yn, 'top_until' => ['Top placement till', 'date', 'nullable' => true], 'listing_until' => ['Listing till', 'date', 'nullable' => true]],
                'actions' => ['approve' => ['Approve & verify', 'set' => ['status' => 'active', 'verified' => 1], 'when' => ['pending'], 'perm' => 'verify', 'notify' => ['user_id', 'Your vendor profile has been approved.']], 'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active', 'pending'], 'perm' => 'verify']]],

            'vendorquotes' => ['title' => 'Vendor leads (all)', 'perm' => 'vendors', 'table' => 'vendor_quotes', 'area_via' => ['vendor_id', 'event_vendors'], 'order' => 'q.id desc', 'readonly' => true,
                'query' => fn () => DB::table('vendor_quotes as q')->join('event_vendors as v', 'v.id', '=', 'q.vendor_id')->join('users as u', 'u.id', '=', 'q.user_id')->select('q.*', DB::raw('v.name as vendor'), DB::raw('u.name as member')),
                'cols' => ['Vendor' => 'vendor', 'Member' => 'member', 'Event date' => 'event_date', 'Budget' => 'budget', 'Quote' => 'quote_amount', 'Status' => 'status'], 'filters' => ['status']],

            'photos' => ['title' => 'Event photos (moderation)', 'perm' => 'ticketing', 'table' => 'event_photos', 'area_user' => 'user_id', 'order' => 'p.id desc', 'readonly' => true,
                'query' => fn () => DB::table('event_photos as p')->join('events as e', 'e.id', '=', 'p.event_id')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', DB::raw('e.title as event'), DB::raw('u.name as member')),
                'cols' => ['Event' => 'event', 'Member' => 'member', 'Caption' => 'caption', 'Photo' => 'path', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'approved'], 'when' => ['pending', 'rejected'], 'perm' => 'edit'], 'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['pending', 'approved'], 'perm' => 'edit']]],

            'reports' => ['title' => 'Member reports (abuse / spam)', 'perm' => 'community', 'table' => 'member_reports', 'area_user' => 'reporter_id', 'order' => 'r.id desc', 'readonly' => true,
                'query' => fn () => DB::table('member_reports as r')->join('users as a', 'a.id', '=', 'r.reporter_id')->join('users as t', 't.id', '=', 'r.target_id')->select('r.*', DB::raw('a.name as reporter'), DB::raw('t.name as reported')),
                'cols' => ['Reporter' => 'reporter', 'Reported member' => 'reported', 'Reason' => 'reason', 'Detail' => 'detail', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status'],
                'actions' => ['suspend' => ['Suspend member + close', 'set' => ['status' => 'actioned'], 'when' => ['open'], 'perm' => 'moderate', 'confirm' => 'Suspend the reported member account?', 'suspend' => 'target_id'], 'dismiss' => ['Dismiss', 'set' => ['status' => 'dismissed'], 'when' => ['open'], 'perm' => 'moderate']]],

            'msgreports' => ['title' => 'Chat message reports', 'perm' => 'community', 'table' => 'message_reports', 'area_user' => 'reporter_id', 'order' => 'r.id desc', 'readonly' => true,
                'query' => fn () => DB::table('message_reports as r')->join('chat_messages as m', 'm.id', '=', 'r.message_id')->join('users as s', 's.id', '=', 'm.sender_id')->select('r.*', DB::raw('m.body as message'), DB::raw('s.name as sender')),
                'cols' => ['Sender' => 'sender', 'Message' => 'message', 'Reason' => 'reason', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status'],
                'actions' => ['hide' => ['Hide message', 'set' => ['status' => 'actioned'], 'when' => ['open'], 'perm' => 'moderate', 'hide_message' => true], 'dismiss' => ['Dismiss', 'set' => ['status' => 'dismissed'], 'when' => ['open'], 'perm' => 'moderate']]],

            'departments' => ['title' => 'Trust departments & their forms', 'perm' => 'trusts', 'table' => 'departments', 'order' => 'id', 'create' => true, 'delete' => true,
                'cols' => ['Department' => 'name', 'Email' => 'email', 'Fee' => 'fee', 'Active' => 'is_active'],
                'form' => ['name' => ['Department name', 'text'], 'email' => ['Email', 'text'], 'fee' => ['Fee', 'number'], 'fields_text' => ['Department form fields (one per line: label | text/number/date/select/textarea | options,comma | required 1/0)', 'textarea', 'virtual' => true, 'nullable' => true], 'is_active' => ['Active'] + $yn],
                'before' => function (array $d, ?int $id) {
                    $out = [];
                    foreach (preg_split('/\R/', (string) ($d['fields_text'] ?? '')) as $line) {
                        $p = array_map('trim', explode('|', $line)); if (count($p) < 2 || $p[0] === '') continue;
                        $type = in_array($p[1], ['text', 'number', 'date', 'select', 'textarea'], true) ? $p[1] : 'text';
                        $out[] = ['key' => 'f' . (count($out) + 1) . '_' . Str::slug($p[0], '_'), 'label' => $p[0], 'type' => $type, 'options' => $type === 'select' ? array_values(array_filter(array_map('trim', explode(',', $p[2] ?? '')))) : [], 'required' => ($p[3] ?? '0') === '1'];
                    }
                    unset($d['fields_text']); $d['fields'] = json_encode($out); $d['email'] = $d['email'] ?? ''; return $d;
                },
                'fill' => function (object $row) { $t = ''; foreach (json_decode($row->fields ?: '[]', true) ?: [] as $f) $t .= "{$f['label']} | {$f['type']} | " . implode(',', $f['options'] ?? []) . ' | ' . ($f['required'] ? 1 : 0) . "\n"; return ['fields_text' => trim($t)]; }],
        ]));
    }

    /** Fills the admin's own country / state / district / city / zone on rows they create, so they stay inside their area. */
    private static function stampArea(array $d): array
    {
        $u = auth()->user();
        if ($u && ! $u->isSuper() && method_exists($u, 'areaFilter')) foreach ($u->areaFilter() as $k => $v) if (empty($d[$k])) $d[$k] = $v;
        return $d;
    }

    private static function areaFields(): array
    {
        return ['country' => ['Country', 'text', 'nullable' => true], 'pradesh' => ['State / Pradesh', 'text', 'nullable' => true], 'district' => ['District', 'text', 'nullable' => true], 'city' => ['City', 'text', 'nullable' => true], 'zone' => ['Zone', 'text', 'nullable' => true]];
    }

    /** Milestone E screens: careers, blood, giving (donations / 80G / relief), investments. */
    private static function milestoneF(): array
    {
        $yn = ['type' => 'checkbox'];
        $seg = ['school' => 'School', 'college' => 'College', 'business' => 'Business', 'family' => 'Family'];
        $segG = ['general' => 'General (all)'] + $seg;
        return [
            'deletions' => ['title' => 'Account deletion requests', 'perm' => 'users', 'table' => 'account_deletions', 'order' => 'd.id desc', 'export' => true,
                'query' => fn () => DB::table('account_deletions as d')->leftJoin('users as u', 'u.id', '=', 'd.user_id')->select('d.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Member' => 'member', 'Phone' => 'phone', 'Requested' => 'requested_at', 'Erase after' => 'erase_after', 'Erased' => 'erased_at', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['restore' => ['Restore account', 'set' => ['status' => 'cancelled'], 'when' => ['requested'], 'perm' => 'edit', 'call' => [\App\Services\AccountDeletionService::class, 'cancel']],
                    'erase' => ['Erase now', 'set' => ['status' => 'requested'], 'when' => ['requested'], 'perm' => 'edit', 'call' => [\App\Services\AccountDeletionService::class, 'erase']]]],

            'iapproducts' => ['title' => 'iOS In-App Purchase products', 'perm' => 'plans', 'table' => 'iap_products', 'order' => 'id desc', 'create' => true, 'delete' => true,
                'cols' => ['App Store product id' => 'product_id', 'Sells' => 'kind', 'Course / plan id' => 'ref_id', 'Title' => 'title', 'Active' => 'is_active'],
                'form' => ['product_id' => ['App Store Connect product identifier', 'text'], 'kind' => ['Sells', 'select', 'options' => ['course' => 'Course', 'plan' => 'Subscription plan']], 'ref_id' => ['Course ID / Plan ID', 'number'], 'title' => ['Title', 'text', 'nullable' => true], 'is_active' => ['Active', 'type' => 'checkbox']]],

            'iaptx' => ['title' => 'iOS purchases (Apple)', 'perm' => 'plans', 'table' => 'iap_transactions', 'order' => 't.id desc', 'readonly' => true, 'export' => true, 'area_user' => 'user_id',
                'query' => fn () => DB::table('iap_transactions as t')->join('users as u', 'u.id', '=', 't.user_id')->select('t.*', DB::raw('u.name as member')),
                'cols' => ['Member' => 'member', 'Product' => 'product_id', 'Sells' => 'kind', 'Env' => 'environment', 'Purchased' => 'purchased_at', 'Expires' => 'expires_at', 'Status' => 'status'], 'filters' => ['status', 'kind']],

            /* ------------------------------------------------ Academy */
            'courses' => ['title' => 'Academy courses', 'perm' => 'academy', 'table' => 'courses', 'order' => 'id desc', 'create' => true, 'delete' => true, 'export' => true,
                'cols' => ['Title' => 'title', 'Segment' => 'segment', 'Instructor' => 'instructor_name', 'Price' => 'price', 'Free' => 'free', 'In subscription' => 'subscription', 'Share %' => 'instructor_share_pct', 'AI' => 'ai_generated', 'Enrolled' => 'enrolments', 'Status' => 'status'], 'filters' => ['status', 'segment'],
                'form' => ['title' => ['Title', 'text'], 'segment' => ['Segment', 'select', 'options' => $segG], 'category' => ['Category', 'text', 'nullable' => true], 'description' => ['Description', 'textarea', 'nullable' => true], 'thumbnail' => ['Thumbnail URL', 'text', 'nullable' => true],
                    'instructor_name' => ['Instructor name', 'text', 'nullable' => true], 'instructor_user_id' => ['Instructor member ID (for revenue share)', 'number', 'nullable' => true], 'instructor_share_pct' => ['Instructor revenue share %', 'number'],
                    'price' => ['Price (tax incl., 0 = not sold separately)', 'number'], 'currency' => ['Currency', 'text'], 'free' => ['Whole course free'] + $yn, 'subscription' => ['Included in Academy subscription'] + $yn, 'lang' => ['Language', 'text'], 'sort' => ['Sort', 'number'],
                    'status' => ['Status', 'select', 'options' => ['draft' => 'Draft', 'live' => 'Live', 'archived' => 'Archived']]],
                'actions' => ['publish' => ['Publish', 'set' => ['status' => 'live'], 'when' => ['draft', 'archived'], 'perm' => 'edit'], 'archive' => ['Archive', 'set' => ['status' => 'archived'], 'when' => ['live'], 'perm' => 'edit']]],

            'lessons' => ['title' => 'Course lessons / videos', 'perm' => 'academy', 'table' => 'lessons', 'order' => 'l.course_id desc, l.sort, l.id', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('lessons as l')->leftJoin('courses as c', 'c.id', '=', 'l.course_id')->select('l.*', DB::raw('c.title as course')),
                'cols' => ['Course' => 'course', 'Lesson' => 'title', 'Video' => 'provider', 'Sec' => 'duration_sec', 'Free preview' => 'free_preview', 'Sort' => 'sort', 'Status' => 'status'], 'filters' => ['status', 'provider'],
                'form' => ['course_id' => ['Course', 'select', 'options' => fn () => DB::table('courses')->orderByDesc('id')->limit(300)->pluck('title', 'id')->all()], 'title' => ['Title', 'text'],
                    'provider' => ['Video source', 'select', 'options' => ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'mp4' => 'MP4 link', 'ai' => 'AI video (link)']], 'video_url' => ['Video URL', 'text', 'nullable' => true], 'duration_sec' => ['Duration (sec)', 'number'],
                    'free_preview' => ['Free preview (visible to everyone)'] + $yn, 'summary' => ['Summary', 'textarea', 'nullable' => true], 'script' => ['Script / transcript', 'textarea', 'nullable' => true], 'sort' => ['Sort', 'number'],
                    'status' => ['Status', 'select', 'options' => ['live' => 'Live', 'hidden' => 'Hidden']]]],

            'enrolments' => ['title' => 'Course enrolments', 'perm' => 'academy', 'table' => 'course_enrolments', 'order' => 'e.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('course_enrolments as e')->join('courses as c', 'c.id', '=', 'e.course_id')->join('users as u', 'u.id', '=', 'e.user_id')->select('e.*', DB::raw('c.title as course'), DB::raw('u.name as member')),
                'cols' => ['Course' => 'course', 'Member' => 'member', 'Source' => 'source', 'Status' => 'status', 'Completed' => 'completed_at', 'Date' => 'created_at'], 'filters' => ['status', 'source'], 'area_user' => 'user_id'],

            'earnings' => ['title' => 'Instructor earnings (revenue share)', 'perm' => 'academy', 'table' => 'instructor_earnings', 'order' => 'e.id desc', 'export' => true,
                'query' => fn () => DB::table('instructor_earnings as e')->join('courses as c', 'c.id', '=', 'e.course_id')->leftJoin('users as u', 'u.id', '=', 'e.instructor_user_id')->select('e.*', DB::raw('c.title as course'), DB::raw('u.name as instructor')),
                'cols' => ['Course' => 'course', 'Instructor' => 'instructor', 'Gross' => 'gross', 'Share %' => 'share_pct', 'Payable' => 'amount', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status'],
                'form' => ['status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'paid' => 'Paid', 'reversed' => 'Reversed']]],
                'actions' => ['paid' => ['Mark paid', 'set' => ['status' => 'paid'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['instructor_user_id', 'Your Academy revenue share has been paid.']]]],

            'liveclasses' => ['title' => 'Live classes (Zoom / Meet)', 'perm' => 'academy', 'table' => 'live_classes', 'order' => 'starts_at desc', 'create' => true, 'delete' => true,
                'cols' => ['Title' => 'title', 'Segment' => 'segment', 'Host' => 'host', 'Via' => 'provider', 'Starts' => 'starts_at', 'Min' => 'duration_min', 'Subscribers only' => 'subscribers_only', 'Status' => 'status'], 'filters' => ['status', 'provider'],
                'form' => ['title' => ['Title', 'text'], 'course_id' => ['Course ID (optional)', 'number', 'nullable' => true], 'segment' => ['Segment', 'select', 'options' => $segG], 'host' => ['Host', 'text', 'nullable' => true],
                    'provider' => ['Provider', 'select', 'options' => ['zoom' => 'Zoom', 'meet' => 'Google Meet']], 'join_url' => ['Join link', 'text'], 'starts_at' => ['Starts at', 'datetime'], 'duration_min' => ['Duration (min)', 'number'],
                    'subscribers_only' => ['Subscribers only'] + $yn, 'status' => ['Status', 'select', 'options' => ['scheduled' => 'Scheduled', 'done' => 'Done', 'cancelled' => 'Cancelled']]]],

            'library' => ['title' => 'Knowledge library', 'perm' => 'academy', 'table' => 'library_items', 'order' => 'id desc', 'create' => true, 'delete' => true,
                'cols' => ['Title' => 'title', 'Kind' => 'kind', 'Segment' => 'segment', 'Category' => 'category', 'Views' => 'views', 'Status' => 'status'], 'filters' => ['status', 'kind'],
                'form' => ['title' => ['Title', 'text'], 'kind' => ['Kind', 'select', 'options' => ['pdf' => 'PDF', 'article' => 'Article', 'link' => 'Link', 'video' => 'Video']], 'segment' => ['Segment', 'select', 'options' => $segG],
                    'category' => ['Category', 'text', 'nullable' => true], 'url' => ['URL', 'text'], 'description' => ['Description', 'textarea', 'nullable' => true], 'status' => ['Status', 'select', 'options' => ['live' => 'Live', 'hidden' => 'Hidden']]]],

            /* ------------------------------------------------ Exams */
            'examq' => ['title' => 'Exam question bank', 'perm' => 'exams', 'table' => 'exam_questions', 'order' => 'id desc', 'create' => true, 'delete' => true, 'export' => true,
                'cols' => ['Segment' => 'segment', 'Subject' => 'subject', 'Question' => 'question', 'Level' => 'difficulty', 'Active' => 'is_active'], 'filters' => ['segment', 'difficulty'],
                'form' => ['segment' => ['Segment', 'select', 'options' => $seg], 'subject' => ['Subject', 'text', 'nullable' => true], 'question' => ['Question', 'textarea'],
                    'options_text' => ['Options (one per line, 2 to 6)', 'textarea'], 'correct_no' => ['Correct option number (1 = first line)', 'number'], 'explanation' => ['Explanation (shown after submit)', 'textarea', 'nullable' => true],
                    'difficulty' => ['Difficulty', 'select', 'options' => ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard']], 'is_active' => ['Active'] + $yn],
                'before' => function (array $d, ?int $id) {
                    $o = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($d['options_text'] ?? ''))), fn ($x) => $x !== ''));
                    if (count($o) < 2 || count($o) > 6) throw \Illuminate\Validation\ValidationException::withMessages(['options_text' => 'Write at least 2 and at most 6 options.']);
                    $c = (int) ($d['correct_no'] ?? 0);
                    if ($c < 1 || $c > count($o)) throw \Illuminate\Validation\ValidationException::withMessages(['correct_no' => 'Correct option number 1 to ' . count($o) . ' must be between ']);
                    $d['options'] = json_encode($o, JSON_UNESCAPED_UNICODE); $d['correct'] = $c - 1; unset($d['options_text'], $d['correct_no']);
                    return $d;
                },
                'fill' => fn (object $row) => ['options_text' => implode("\n", json_decode($row->options ?: '[]', true) ?: []), 'correct_no' => ((int) $row->correct) + 1]],

            'exams' => ['title' => 'Exams (weekly auto + special)', 'perm' => 'exams', 'table' => 'exams', 'order' => 'id desc', 'create' => true, 'export' => true,
                'cols' => ['Title' => 'title', 'Segment' => 'segment', 'Kind' => 'kind', 'Starts' => 'starts_at', 'Ends' => 'ends_at', 'Min' => 'duration_min', 'Pass %' => 'pass_pct', 'Status' => 'status'], 'filters' => ['status', 'segment', 'kind'],
                'form' => ['title' => ['Title', 'text'], 'segment' => ['Segment', 'select', 'options' => $seg], 'kind' => ['Kind', 'select', 'options' => ['weekly' => 'Weekly', 'special' => 'Special']], 'starts_at' => ['Starts at', 'datetime'], 'ends_at' => ['Ends at', 'datetime'],
                    'duration_min' => ['Duration (min)', 'number'], 'pass_pct' => ['Pass %', 'number'], 'qids' => ['Question IDs (comma separated, from question bank)', 'text'], 'status' => ['Status', 'select', 'options' => ['draft' => 'Draft', 'live' => 'Live']]],
                'before' => function (array $d, ?int $id) {
                    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($d['qids'] ?? ''))))));
                    if (! $ids || DB::table('exam_questions')->whereIn('id', $ids)->count() !== count($ids)) throw \Illuminate\Validation\ValidationException::withMessages(['qids' => 'All question IDs must exist in the bank.']);
                    $d['question_ids'] = json_encode($ids); unset($d['qids']);
                    return $d;
                },
                'fill' => fn (object $row) => ['qids' => implode(',', json_decode($row->question_ids ?: '[]', true) ?: [])],
                'actions' => ['live' => ['Go live', 'set' => ['status' => 'live'], 'when' => ['draft'], 'perm' => 'edit'], 'close' => ['Close & publish results', 'set' => ['status' => 'live'], 'when' => ['live'], 'perm' => 'approve', 'call' => [\App\Services\ExamService::class, 'forceClose']]]],

            'examattempts' => ['title' => 'Exam attempts & ranks', 'perm' => 'exams', 'table' => 'exam_attempts', 'order' => 'a.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('exam_attempts as a')->join('exams as e', 'e.id', '=', 'a.exam_id')->join('users as u', 'u.id', '=', 'a.user_id')->select('a.*', DB::raw('e.title as exam'), DB::raw('u.name as member')),
                'cols' => ['Exam' => 'exam', 'Member' => 'member', 'Score' => 'score', 'Out of' => 'total', '%' => 'percent', 'Rank' => 'rank', 'Sec' => 'seconds', 'Submitted' => 'submitted_at']],

            'learners' => ['title' => 'Learner profiles (segments)', 'perm' => 'exams', 'table' => 'learner_profiles', 'order' => 'p.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('learner_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', DB::raw('u.name as member')),
                'cols' => ['Member' => 'member', 'Segment' => 'segment', 'Class' => 'class_name', 'Institute' => 'institute', 'City' => 'city'], 'filters' => ['segment']],

            /* ------------------------------------------------ Housie */
            'housiegames' => ['title' => 'Housie games', 'perm' => 'housie', 'table' => 'housie_games', 'order' => 'id desc', 'create' => true, 'export' => true,
                'cols' => ['Title' => 'title', 'Starts' => 'starts_at', 'Ticket' => 'ticket_price', 'Max/member' => 'max_tickets', 'Every (sec)' => 'interval_sec', 'Status' => 'status'], 'filters' => ['status'],
                'form' => ['title' => ['Title', 'text'], 'starts_at' => ['Starts at', 'datetime'], 'ticket_price' => ['Ticket price (tax incl., 0 = free)', 'number'], 'currency' => ['Currency', 'text'], 'max_tickets' => ['Max tickets per member', 'number'], 'interval_sec' => ['Seconds between numbers', 'number'],
                    'p_early5' => ['Prize: Early five', 'text', 'nullable' => true], 'p_line1' => ['Prize: Top line', 'text', 'nullable' => true], 'p_line2' => ['Prize: Middle line', 'text', 'nullable' => true], 'p_line3' => ['Prize: Bottom line', 'text', 'nullable' => true], 'p_full' => ['Prize: Full house', 'text', 'nullable' => true],
                    'sponsor_id' => ['Sponsor', 'select', 'nullable' => true, 'options' => fn () => DB::table('housie_sponsors')->where('status', 'active')->pluck('name', 'id')->all()]],
                'before' => function (array $d, ?int $id) {
                    $d['prizes'] = json_encode(['early5' => $d['p_early5'] ?? '', 'line1' => $d['p_line1'] ?? '', 'line2' => $d['p_line2'] ?? '', 'line3' => $d['p_line3'] ?? '', 'full' => $d['p_full'] ?? ''], JSON_UNESCAPED_UNICODE);
                    unset($d['p_early5'], $d['p_line1'], $d['p_line2'], $d['p_line3'], $d['p_full']);
                    return $d;
                },
                'fill' => function (object $row) { $p = json_decode($row->prizes ?: '[]', true) ?: []; return ['p_early5' => $p['early5'] ?? '', 'p_line1' => $p['line1'] ?? '', 'p_line2' => $p['line2'] ?? '', 'p_line3' => $p['line3'] ?? '', 'p_full' => $p['full'] ?? '']; },
                'actions' => ['start' => ['Start now', 'set' => ['status' => 'scheduled'], 'when' => ['scheduled'], 'perm' => 'edit', 'call' => [\App\Services\HousieService::class, 'start']],
                    'cancel' => ['Cancel game', 'set' => ['status' => 'cancelled'], 'when' => ['scheduled'], 'perm' => 'approve', 'call' => [\App\Services\HousieService::class, 'cancel']]]],

            'sponsors' => ['title' => 'Housie sponsors & banners', 'perm' => 'housie', 'table' => 'housie_sponsors', 'order' => 'id desc', 'create' => true, 'delete' => true,
                'cols' => ['Sponsor' => 'name', 'Banner' => 'banner_url', 'Link' => 'link_url', 'Status' => 'status'],
                'form' => ['name' => ['Sponsor name', 'text'], 'banner_url' => ['Banner image URL', 'text', 'nullable' => true], 'link_url' => ['Click-through link', 'text', 'nullable' => true], 'status' => ['Status', 'select', 'options' => ['active' => 'Active', 'paused' => 'Paused']]]],

            'housietickets' => ['title' => 'Housie tickets', 'perm' => 'housie', 'table' => 'housie_tickets', 'order' => 't.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('housie_tickets as t')->join('housie_games as g', 'g.id', '=', 't.game_id')->join('users as u', 'u.id', '=', 't.user_id')->select('t.*', DB::raw('g.title as game'), DB::raw('u.name as member')),
                'cols' => ['Game' => 'game', 'Member' => 'member', 'Status' => 'status', 'Bought' => 'created_at'], 'filters' => ['status'], 'area_user' => 'user_id'],

            'housieclaims' => ['title' => 'Housie winners & claims', 'perm' => 'housie', 'table' => 'housie_claims', 'order' => 'c.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('housie_claims as c')->join('housie_games as g', 'g.id', '=', 'c.game_id')->join('users as u', 'u.id', '=', 'c.user_id')->select('c.*', DB::raw('g.title as game'), DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Game' => 'game', 'Member' => 'member', 'Phone' => 'phone', 'Prize' => 'prize', 'Result' => 'status', 'Time' => 'created_at'], 'filters' => ['status', 'prize'], 'area_user' => 'user_id'],
        ];
    }

    private static function milestoneE(): array
    {
        $yn = ['type' => 'checkbox'];
        $stampBefore = fn (array $d, ?int $id) => $id ? $d : self::stampArea($d);
        return [
            /* ------------------------------------------------ Careers */
            'jobpacks' => ['title' => 'Job packs (paid / featured jobs)', 'perm' => 'careers', 'table' => 'job_packs', 'order' => 'sort, id', 'create' => true, 'delete' => true,
                'cols' => ['Name' => 'name', 'Kind' => 'kind', 'Price (tax incl.)' => 'price', 'Cur' => 'currency', 'Days' => 'days', 'Active' => 'is_active'],
                'form' => ['name' => ['Name', 'text'], 'kind' => ['Kind', 'select', 'options' => ['post' => 'Paid job post', 'featured' => 'Featured job (top of list)']], 'price' => ['Price', 'number'], 'currency' => ['Currency', 'text'], 'days' => ['Days', 'number'],
                    'sort' => ['Sort', 'number'], 'description' => ['Description', 'text', 'nullable' => true], 'is_active' => ['Active'] + $yn]],

            'joborders' => ['title' => 'Job pack orders', 'perm' => 'careers', 'table' => 'job_orders', 'area_user' => 'user_id', 'order' => 'o.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('job_orders as o')->join('jobs_board as j', 'j.id', '=', 'o.job_id')->join('job_packs as k', 'k.id', '=', 'o.pack_id')->select('o.*', DB::raw('j.title as job'), DB::raw('k.name as pack'), DB::raw('k.kind as pack_kind')),
                'cols' => ['#' => 'id', 'Job' => 'job', 'Pack' => 'pack', 'Kind' => 'pack_kind', 'Amount' => 'amount', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status']],

            'drives' => ['title' => 'Campus placement & internship drives', 'perm' => 'careers', 'table' => 'placement_drives', 'order' => 'id desc', 'export' => true,
                'cols' => ['Type' => 'drive_type', 'Title' => 'title', 'Institution' => 'institution', 'Company' => 'company', 'City' => 'city', 'Drive date' => 'drive_date', 'Last date' => 'last_date', 'Positions' => 'positions', 'Status' => 'status'], 'filters' => ['status', 'drive_type', 'city'],
                'form' => ['status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'live' => 'Live', 'rejected' => 'Rejected', 'closed' => 'Closed']], 'admin_note' => ['Admin note', 'text', 'nullable' => true]],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'live'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your placement/internship drive is now live.']],
                    'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your drive was not approved.']],
                    'close' => ['Close', 'set' => ['status' => 'closed'], 'when' => ['live'], 'perm' => 'approve']]],

            'driveregs' => ['title' => 'Drive registrations', 'perm' => 'careers', 'table' => 'drive_registrations', 'area_via' => ['drive_id', 'placement_drives'], 'order' => 'g.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('drive_registrations as g')->join('placement_drives as d', 'd.id', '=', 'g.drive_id')->join('users as u', 'u.id', '=', 'g.user_id')->select('g.*', DB::raw('d.title as drive'), DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Drive' => 'drive', 'Member' => 'member', 'Phone' => 'phone', 'College' => 'college', 'Course' => 'course', 'Status' => 'status'], 'filters' => ['status'],
                'actions' => ['shortlist' => ['Shortlist', 'set' => ['status' => 'shortlisted'], 'when' => ['registered'], 'perm' => 'edit', 'notify' => ['user_id', 'You have been shortlisted for the drive.']],
                    'select' => ['Select', 'set' => ['status' => 'selected'], 'when' => ['registered', 'shortlisted'], 'perm' => 'edit', 'notify' => ['user_id', 'Congratulations! You have been selected for the drive.']],
                    'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['registered', 'shortlisted'], 'perm' => 'edit']]],

            'mentors' => ['title' => 'Mentors & career counsellors', 'perm' => 'careers', 'table' => 'mentor_profiles', 'order' => 'm.id desc', 'export' => true,
                'query' => fn () => DB::table('mentor_profiles as m')->join('users as u', 'u.id', '=', 'm.user_id')->select('m.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Mentor' => 'member', 'Phone' => 'phone', 'Headline' => 'headline', 'Industry' => 'industry', 'Exp (yrs)' => 'experience_years', 'Counselling' => 'offers_counselling', 'City' => 'city', 'Verified' => 'verified', 'Status' => 'status'], 'filters' => ['status', 'city'],
                'form' => ['headline' => ['Headline', 'text'], 'industry' => ['Industry', 'text', 'nullable' => true], 'expertise' => ['Expertise', 'text'], 'max_mentees' => ['Max mentees', 'number'], 'offers_counselling' => ['Offers career counselling'] + $yn,
                    'verified' => ['Verified'] + $yn, 'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'paused' => 'Paused', 'blocked' => 'Blocked']]],
                'actions' => ['approve' => ['Approve & verify', 'set' => ['status' => 'approved', 'verified' => 1], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your mentor profile has been approved. Mentees can now connect with you.']],
                    'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['approved', 'pending', 'paused'], 'perm' => 'approve', 'notify' => ['user_id', 'Your mentor profile has been blocked.']],
                    'unblock' => ['Unblock', 'set' => ['status' => 'approved'], 'when' => ['blocked'], 'perm' => 'approve']]],

            'mentorships' => ['title' => 'Mentorships & counselling requests', 'perm' => 'careers', 'table' => 'mentorships', 'area_user' => 'mentee_user_id', 'order' => 's.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('mentorships as s')->join('users as a', 'a.id', '=', 's.mentor_user_id')->join('users as b', 'b.id', '=', 's.mentee_user_id')->select('s.*', DB::raw('a.name as mentor'), DB::raw('b.name as mentee'),
                    DB::raw('(select count(*) from mentor_sessions x where x.mentorship_id = s.id and x.status = \'done\') as sessions_done')),
                'cols' => ['Mentor' => 'mentor', 'Mentee' => 'mentee', 'Kind' => 'kind', 'Topic' => 'topic', 'Sessions done' => 'sessions_done', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status', 'kind']],

            'mentorsessions' => ['title' => 'Mentoring sessions', 'perm' => 'careers', 'table' => 'mentor_sessions', 'area_user' => 'mentee_user_id', 'order' => 'm.id desc', 'readonly' => true,
                'query' => fn () => DB::table('mentor_sessions as s')->join('mentorships as m', 'm.id', '=', 's.mentorship_id')->select('s.*', 'm.topic', 'm.mentee_user_id'),
                'cols' => ['Topic' => 'topic', 'When' => 'scheduled_at', 'Mins' => 'duration_min', 'Mode' => 'mode', 'Status' => 'status', 'Rating' => 'rating'], 'filters' => ['status']],

            /* ------------------------------------------------ Blood */
            'blooddonors' => ['title' => 'Blood donors', 'perm' => 'blood', 'table' => 'blood_donors', 'order' => 'd.id desc', 'export' => true,
                'query' => fn () => DB::table('blood_donors as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Donor' => 'member', 'Phone' => 'phone', 'Group' => 'blood_group', 'City' => 'city', 'State' => 'pradesh', 'Last donated' => 'last_donated_on', 'Donations' => 'donations_count', 'Available' => 'available', 'Status' => 'status'], 'filters' => ['blood_group', 'city', 'pradesh', 'status'],
                'actions' => ['block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['active'], 'perm' => 'approve', 'notify' => ['user_id', 'Your blood donor profile has been blocked.']], 'unblock' => ['Unblock', 'set' => ['status' => 'active'], 'when' => ['blocked'], 'perm' => 'approve']]],

            'bloodrequests' => ['title' => 'Blood requests', 'perm' => 'blood', 'table' => 'blood_requests', 'order' => 'q.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('blood_requests as q')->join('users as u', 'u.id', '=', 'q.user_id')->select('q.*', DB::raw('u.name as requester')),
                'cols' => ['Requester' => 'requester', 'Patient' => 'patient_name', 'Group' => 'blood_group', 'Units' => 'units', 'Hospital' => 'hospital', 'City' => 'city', 'Urgency' => 'urgency', 'Donors alerted' => 'notified', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status', 'urgency', 'blood_group', 'city'],
                'actions' => ['fulfil' => ['Mark fulfilled', 'set' => ['status' => 'fulfilled'], 'when' => ['open'], 'perm' => 'edit'], 'close' => ['Close', 'set' => ['status' => 'closed'], 'when' => ['open'], 'perm' => 'edit']]],

            'blooddrives' => ['title' => 'Blood donation camps', 'perm' => 'blood', 'table' => 'blood_drives', 'order' => 'id desc', 'create' => true, 'delete' => true, 'export' => true,
                'cols' => ['Title' => 'title', 'Organiser' => 'organiser', 'Venue' => 'venue', 'City' => 'city', 'Date' => 'drive_date', 'Target' => 'target_units', 'Collected' => 'collected_units', 'Status' => 'status'], 'filters' => ['status', 'city'],
                'form' => ['title' => ['Title', 'text'], 'organiser' => ['Organiser', 'text', 'nullable' => true], 'venue' => ['Venue', 'text'], 'drive_date' => ['Date', 'date'], 'timing' => ['Timing', 'text', 'nullable' => true], 'description' => ['Description', 'textarea', 'nullable' => true],
                    'contact_phone' => ['Contact phone', 'text', 'nullable' => true], 'target_units' => ['Target units', 'number'], 'collected_units' => ['Collected units', 'number']] + self::areaFields(),
                'before' => function (array $d, ?int $id) use ($stampBefore) { $d = $stampBefore($d, $id); if (! $id) $d['status'] = 'live'; return $d; },
                'after' => function (int $id, array $d, bool $isNew) { if ($isNew) app(\App\Services\BloodService::class)->driveLive($id); },
                'actions' => ['approve' => ['Approve & notify donors', 'set' => ['status' => 'live'], 'when' => ['pending'], 'perm' => 'approve', 'call' => [\App\Services\BloodService::class, 'driveLive']],
                    'complete' => ['Mark completed', 'set' => ['status' => 'live'], 'when' => ['live'], 'perm' => 'edit', 'call' => [\App\Services\BloodService::class, 'driveComplete']],
                    'cancel' => ['Cancel', 'set' => ['status' => 'cancelled'], 'when' => ['pending', 'live'], 'perm' => 'approve']]],

            'bloodregs' => ['title' => 'Camp registrations & attendance', 'perm' => 'blood', 'table' => 'blood_drive_regs', 'area_via' => ['drive_id', 'blood_drives'], 'order' => 'g.id desc', 'readonly' => true,
                'query' => fn () => DB::table('blood_drive_regs as g')->join('blood_drives as d', 'd.id', '=', 'g.drive_id')->join('users as u', 'u.id', '=', 'g.user_id')->select('g.*', DB::raw('d.title as camp'), DB::raw('d.status as status'), DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Camp' => 'camp', 'Donor' => 'member', 'Phone' => 'phone', 'Attended' => 'attended', 'Donated' => 'donated'],
                'actions' => ['donated' => ['Mark donated', 'set' => ['attended' => 1, 'donated' => 1], 'perm' => 'edit'], 'attended' => ['Mark attended only', 'set' => ['attended' => 1], 'perm' => 'edit']]],

            /* ------------------------------------------------ Giving */
            'entities' => ['title' => 'Donation entities (trust / NGO, 80G)', 'perm' => 'giving', 'table' => 'donation_entities', 'order' => 'id', 'create' => true, 'delete' => true,
                'cols' => ['Name' => 'name', 'Kind' => 'kind', 'PAN' => 'pan', '80G' => 'has_80g', '80G no.' => 'g80_number', 'Valid till' => 'g80_valid_to', 'Active' => 'is_active'],
                'form' => ['name' => ['Name', 'text'], 'kind' => ['Kind', 'select', 'options' => ['trust' => 'Trust', 'ngo' => 'NGO', 'mahasabha' => 'Mahasabha', 'unit' => 'Unit / Mandal']], 'pan' => ['PAN of entity', 'text', 'nullable' => true], 'reg_no' => ['Registration no.', 'text', 'nullable' => true],
                    'has_80g' => ['Has valid 80G approval'] + $yn, 'g80_number' => ['80G approval no.', 'text', 'nullable' => true], 'g80_valid_from' => ['80G valid from', 'date', 'nullable' => true], 'g80_valid_to' => ['80G valid to', 'date', 'nullable' => true],
                    'address' => ['Address (printed on certificate)', 'text', 'nullable' => true], 'signatory' => ['Authorised signatory', 'text', 'nullable' => true], 'country' => ['Country', 'text', 'nullable' => true], 'is_active' => ['Active'] + $yn]],

            'campaigns' => ['title' => 'Donation campaigns', 'perm' => 'giving', 'table' => 'campaigns', 'order' => 'c.id desc', 'create' => true, 'delete' => true, 'export' => true,
                'query' => fn () => DB::table('campaigns as c')->join('donation_entities as e', 'e.id', '=', 'c.entity_id')->select('c.*', DB::raw('e.name as entity')),
                'cols' => ['Title' => 'title', 'Entity' => 'entity', 'Category' => 'category', 'Target' => 'target_amount', 'Raised' => 'raised_amount', 'Donors' => 'donors_count', 'Ends' => 'ends_on', 'Featured' => 'featured', 'Status' => 'status'], 'filters' => ['status', 'category'],
                'form' => ['entity_id' => ['Entity'] + self::sel('donation_entities'), 'title' => ['Title', 'text'], 'category' => ['Category', 'select', 'options' => ['education' => 'Education', 'medical' => 'Medical', 'relief' => 'Relief', 'temple' => 'Temple / religious', 'other' => 'Other']],
                    'story' => ['Story', 'textarea', 'nullable' => true], 'beneficiary' => ['Beneficiary', 'text', 'nullable' => true], 'banner' => ['Banner URL', 'text', 'nullable' => true], 'target_amount' => ['Target amount', 'number'], 'currency' => ['Currency', 'text'],
                    'starts_on' => ['Starts on', 'date', 'nullable' => true], 'ends_on' => ['Ends on', 'date', 'nullable' => true], 'featured' => ['Featured'] + $yn,
                    'status' => ['Status', 'select', 'options' => ['draft' => 'Draft', 'live' => 'Live', 'completed' => 'Completed', 'closed' => 'Closed']]] + self::areaFields(),
                'before' => $stampBefore,
                'actions' => ['publish' => ['Publish', 'set' => ['status' => 'live'], 'when' => ['draft'], 'perm' => 'approve'], 'complete' => ['Mark completed', 'set' => ['status' => 'completed'], 'when' => ['live'], 'perm' => 'approve'],
                    'close' => ['Close', 'set' => ['status' => 'closed'], 'when' => ['live', 'completed'], 'perm' => 'approve']]],

            'campdonations' => ['title' => 'Campaign donations (80G)', 'perm' => 'giving', 'table' => 'campaign_donations', 'order' => 'd.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('campaign_donations as d')->join('campaigns as c', 'c.id', '=', 'd.campaign_id')->select('d.*', DB::raw('c.title as campaign')),
                'cols' => ['#' => 'id', 'Campaign' => 'campaign', 'Donor' => 'donor_name', 'Mobile' => 'donor_mobile', 'Amount' => 'amount', 'Cur' => 'currency', '80G no.' => 'g80_no', 'Anonymous' => 'anonymous', 'Status' => 'status', 'Paid at' => 'paid_at'], 'filters' => ['status', 'campaign_id']],

            'utilreports' => ['title' => 'Utilisation reports', 'perm' => 'giving', 'table' => 'utilisation_reports', 'area_via' => ['campaign_id', 'campaigns'], 'order' => 'r.id desc', 'create' => true, 'delete' => true,
                'query' => fn () => DB::table('utilisation_reports as r')->join('campaigns as c', 'c.id', '=', 'r.campaign_id')->select('r.*', DB::raw('c.title as campaign')),
                'cols' => ['Campaign' => 'campaign', 'Title' => 'title', 'Spent' => 'amount_spent', 'Date' => 'report_date', 'Published' => 'published'],
                'form' => ['campaign_id' => ['Campaign'] + self::sel('campaigns', 'title'), 'title' => ['Title', 'text'], 'amount_spent' => ['Amount spent', 'number'], 'report_date' => ['Report date', 'date', 'nullable' => true], 'details' => ['Details', 'textarea', 'nullable' => true],
                    'document_url' => ['Document / photos link', 'text', 'nullable' => true], 'published' => ['Published (notifies donors)'] + $yn],
                'after' => function (int $id, array $d, bool $isNew) { if ($isNew && ! empty($d['published'])) app(\App\Services\GivingService::class)->reportPublished($id); }],

            'assistance' => ['title' => 'Relief & assistance requests', 'perm' => 'giving', 'table' => 'assistance_requests', 'order' => 'a.id desc', 'export' => true,
                'query' => fn () => DB::table('assistance_requests as a')->join('users as u', 'u.id', '=', 'a.user_id')->select('a.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Member' => 'member', 'Phone' => 'phone', 'Kind' => 'kind', 'Title' => 'title', 'Needed' => 'amount_needed', 'Approved' => 'amount_approved', 'City' => 'city', 'Status' => 'status', 'Campaign #' => 'campaign_id'], 'filters' => ['status', 'kind', 'city'],
                'form' => ['amount_approved' => ['Amount approved', 'number', 'nullable' => true], 'admin_note' => ['Note to applicant', 'text', 'nullable' => true], 'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'verified' => 'Verified', 'approved' => 'Approved', 'rejected' => 'Rejected', 'disbursed' => 'Disbursed']]],
                'actions' => ['verify' => ['Mark verified', 'set' => ['status' => 'verified'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your assistance request has been verified.']],
                    'campaign' => ['Approve & start campaign', 'set' => ['status' => 'approved'], 'when' => ['pending', 'verified'], 'perm' => 'approve', 'call' => [\App\Services\GivingService::class, 'makeCampaign']],
                    'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['pending', 'verified'], 'perm' => 'approve', 'notify' => ['user_id', 'Your assistance request could not be approved.']],
                    'disburse' => ['Mark disbursed', 'set' => ['status' => 'disbursed'], 'when' => ['approved'], 'perm' => 'approve', 'notify' => ['user_id', 'The assistance amount has been sent to you.']]]],

            'volunteers' => ['title' => 'Volunteers', 'perm' => 'giving', 'table' => 'volunteers', 'order' => 'v.id desc', 'export' => true,
                'query' => fn () => DB::table('volunteers as v')->join('users as u', 'u.id', '=', 'v.user_id')->leftJoin('campaigns as c', 'c.id', '=', 'v.campaign_id')->select('v.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone'), DB::raw('c.title as campaign')),
                'cols' => ['Volunteer' => 'member', 'Phone' => 'phone', 'Campaign' => 'campaign', 'Skills' => 'skills', 'Availability' => 'availability', 'Hours' => 'hours_logged', 'City' => 'city', 'Status' => 'status'], 'filters' => ['status', 'city'],
                'form' => ['hours_logged' => ['Hours logged', 'number'], 'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive']]],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'active'], 'when' => ['pending', 'inactive'], 'perm' => 'approve', 'notify' => ['user_id', 'Your volunteer registration has been approved. Thank you!']], 'deactivate' => ['Deactivate', 'set' => ['status' => 'inactive'], 'when' => ['active'], 'perm' => 'approve']]],

            /* ------------------------------------------------ Investments */
            'opportunities' => ['title' => 'Investment opportunities (curated)', 'perm' => 'invest', 'table' => 'investment_opportunities', 'order' => 'id desc', 'create' => true, 'delete' => true, 'export' => true,
                'cols' => ['Title' => 'title', 'Kind' => 'kind', 'Sector' => 'sector', 'Min' => 'ticket_min', 'Max' => 'ticket_max', 'Return' => 'expected_return', 'Interests' => 'interests', 'Featured' => 'featured', 'Status' => 'status'], 'filters' => ['status', 'kind'],
                'form' => ['title' => ['Title', 'text'], 'kind' => ['Kind', 'select', 'options' => ['startup' => 'Startup', 'real_estate' => 'Real estate', 'bond' => 'Bonds', 'fund' => 'Funds', 'franchise' => 'Franchise', 'partnership' => 'Business partnership']],
                    'sector' => ['Sector', 'text', 'nullable' => true], 'stage' => ['Stage', 'text', 'nullable' => true], 'ticket_min' => ['Min ticket', 'number', 'nullable' => true], 'ticket_max' => ['Max ticket', 'number', 'nullable' => true], 'currency' => ['Currency', 'text'],
                    'expected_return' => ['Expected return (as stated by issuer)', 'text', 'nullable' => true], 'tenure' => ['Tenure', 'text', 'nullable' => true], 'summary' => ['Summary', 'textarea', 'nullable' => true], 'details' => ['Details', 'textarea', 'nullable' => true],
                    'risk_note' => ['Risk note', 'textarea', 'nullable' => true], 'pitch_deck_url' => ['Pitch deck link', 'text', 'nullable' => true], 'featured' => ['Featured'] + $yn,
                    'status' => ['Status', 'select', 'options' => ['draft' => 'Draft', 'live' => 'Live', 'closed' => 'Closed']]] + self::areaFields(),
                'before' => $stampBefore,
                'actions' => ['publish' => ['Publish', 'set' => ['status' => 'live'], 'when' => ['draft'], 'perm' => 'approve'], 'close' => ['Close', 'set' => ['status' => 'closed'], 'when' => ['live'], 'perm' => 'approve']]],

            'investors' => ['title' => 'Investor & entrepreneur profiles', 'perm' => 'invest', 'table' => 'investor_profiles', 'order' => 'p.id desc', 'export' => true,
                'query' => fn () => DB::table('investor_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone')),
                'cols' => ['Member' => 'member', 'Phone' => 'phone', 'Role' => 'role', 'Company' => 'company', 'Sectors' => 'sectors', 'Ticket min' => 'ticket_min', 'Ticket max' => 'ticket_max', 'Verified' => 'verified', 'Status' => 'status'], 'filters' => ['status', 'role'],
                'form' => ['verified' => ['Verified'] + $yn, 'visible' => ['Visible in directory'] + $yn, 'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'blocked' => 'Blocked']]],
                'actions' => ['approve' => ['Approve & verify', 'set' => ['status' => 'approved', 'verified' => 1], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your investor/entrepreneur profile has been approved.']],
                    'block' => ['Block', 'set' => ['status' => 'blocked'], 'when' => ['approved', 'pending'], 'perm' => 'approve'], 'unblock' => ['Unblock', 'set' => ['status' => 'approved'], 'when' => ['blocked'], 'perm' => 'approve']]],

            'pitches' => ['title' => 'Pitches & business showcase', 'perm' => 'invest', 'table' => 'pitches', 'order' => 'p.id desc', 'export' => true,
                'query' => fn () => DB::table('pitches as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', DB::raw('u.name as founder'), DB::raw('u.mobile as phone')),
                'cols' => ['Founder' => 'founder', 'Phone' => 'phone', 'Title' => 'title', 'Company' => 'company', 'Sector' => 'sector', 'Ask' => 'ask_amount', 'Equity %' => 'equity_pct', 'Interests' => 'interests', 'Showcase' => 'showcase', 'Status' => 'status'], 'filters' => ['status', 'sector'],
                'form' => ['showcase' => ['Show in business showcase'] + $yn, 'admin_note' => ['Note to founder', 'text', 'nullable' => true], 'status' => ['Status', 'select', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'closed' => 'Closed']]],
                'actions' => ['approve' => ['Approve', 'set' => ['status' => 'approved'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your pitch has been approved and is visible to investors.']],
                    'showcase' => ['Approve + showcase', 'set' => ['status' => 'approved', 'showcase' => 1], 'when' => ['pending', 'approved'], 'perm' => 'approve', 'notify' => ['user_id', 'Your pitch has been featured in the Business Showcase.']],
                    'reject' => ['Reject', 'set' => ['status' => 'rejected'], 'when' => ['pending'], 'perm' => 'approve', 'notify' => ['user_id', 'Your pitch was not approved. See the admin note for details.']]]],

            'interests' => ['title' => 'Investment interests (leads)', 'perm' => 'invest', 'table' => 'investment_interests', 'area_user' => 'user_id', 'order' => 'i.id desc', 'readonly' => true, 'export' => true,
                'query' => fn () => DB::table('investment_interests as i')->join('users as u', 'u.id', '=', 'i.user_id')->leftJoin('investment_opportunities as o', 'o.id', '=', 'i.opportunity_id')->leftJoin('pitches as p', 'p.id', '=', 'i.pitch_id')
                    ->select('i.*', DB::raw('u.name as member'), DB::raw('u.mobile as phone'), DB::raw('coalesce(o.title, p.title) as target')),
                'cols' => ['Member' => 'member', 'Phone' => 'phone', 'For' => 'target', 'Amount' => 'amount', 'Message' => 'message', 'Status' => 'status', 'Date' => 'created_at'], 'filters' => ['status'],
                'actions' => ['contacted' => ['Mark contacted', 'set' => ['status' => 'contacted'], 'when' => ['new'], 'perm' => 'edit'], 'closed' => ['Close', 'set' => ['status' => 'closed'], 'when' => ['new', 'contacted'], 'perm' => 'edit']]],
        ];
    }
}
