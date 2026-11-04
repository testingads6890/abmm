<?php

namespace App\Console\Commands;

use App\Models\{Family, Plan};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Hash, Schema};

/**
 * php artisan abmm:demo-data            - seeds 5-10 demo records in every module for 6 states (so every State Sub-Admin sees data of his own state)
 *                                         plus the Marriage Bureau / Event Organiser / Job Recruiter partner dashboards with 10-20 entries each,
 *                                         and Milestone E (careers & mentoring, blood donors, donation campaigns, investments).
 * php artisan abmm:demo-data --purge    - removes everything this command created (tracked in demo_records). Safe to re-run: seeding twice does nothing.
 */
class DemoData extends Command
{
    protected $signature = 'abmm:demo-data {--purge : remove all demo records} {--password=Demo@12345 : password of the demo admin logins}';
    protected $description = 'Seed (or purge) state-wise demo data for every module and the partner dashboards';

    private const STATES = [
        ['Rajasthan', 'RJ', ['Jaipur', 'Jodhpur']], ['Maharashtra', 'MH', ['Nagpur', 'Pune']], ['Madhya Pradesh', 'MP', ['Indore', 'Bhopal']],
        ['Gujarat', 'GJ', ['Ahmedabad', 'Surat']], ['Karnataka', 'KA', ['Bengaluru', 'Hubballi']], ['Telangana', 'TG', ['Hyderabad', 'Nizamabad']],
    ];
    private const SURNAMES = ['Bajaj', 'Rathi', 'Mohta', 'Somani', 'Kalani', 'Toshniwal', 'Bhutra', 'Heda', 'Mundra', 'Jaju', 'Laddha', 'Sarda', 'Dhoot', 'Biyani', 'Maloo', 'Bang', 'Zawar', 'Kabra', 'Inani', 'Ladha', 'Nawal', 'Daga', 'Rathod', 'Lohiya', 'Karwa', 'Mantri', 'Chandak', 'Gilda', 'Bihani', 'Mall'];
    private const MALE = ['Rahul', 'Amit', 'Vikas', 'Sandeep', 'Nitin', 'Ankit', 'Manish', 'Rajesh', 'Gaurav', 'Pankaj', 'Sachin', 'Mayank'];
    private const FEMALE = ['Priya', 'Neha', 'Anjali', 'Pooja', 'Kavita', 'Sneha', 'Ritu', 'Shweta', 'Komal', 'Nisha', 'Radhika', 'Meena'];
    private const GOTRA = ['Parikh', 'Kaushik', 'Sodani', 'Gadiya', 'Rathi', 'Bhutada', 'Mundada', 'Kasat'];

    private array $cols = [];
    private int $seq = 0;

    private function ins(string $t, array $row, bool $track = true): int
    {
        $this->cols[$t] ??= Schema::getColumnListing($t);
        foreach (['created_at', 'updated_at'] as $c) if (in_array($c, $this->cols[$t], true) && ! array_key_exists($c, $row)) $row[$c] = now();
        $id = DB::table($t)->insertGetId(array_intersect_key($row, array_flip($this->cols[$t])));
        if ($track) DB::table('demo_records')->insert(['tbl' => $t, 'rec_id' => $id]);
        return $id;
    }

    private function n(array $a, int $i) { return $a[$i % count($a)]; }

    public function handle(): int
    {
        if ($this->option('purge')) return $this->purge();
        if (DB::table('demo_records')->exists()) { $this->warn('Demo data already present. Run with --purge first to rebuild.'); return self::SUCCESS; }
        DB::transaction(fn () => $this->seed());
        return self::SUCCESS;
    }

    private function purge(): int
    {
        // member rows created through Family::syncHead are not tracked one by one: remove by family
        $fam = DB::table('demo_records')->where('tbl', 'families')->pluck('rec_id');
        DB::table('members')->whereIn('family_id', $fam)->delete();
        $n = 0;
        foreach (DB::table('demo_records')->orderByDesc('id')->get() as $r) { $n += DB::table($r->tbl)->where('id', $r->rec_id)->delete(); }
        DB::table('demo_records')->delete();
        $this->info("Demo data removed ($n rows).");
        return self::SUCCESS;
    }

    private function seed(): void
    {
        $pw = Hash::make($this->option('password')); $out = [];
        $plan = fn (string $code) => Plan::where('code', $code)->first();
        $users = []; $families = []; $admins = [];

        foreach (self::STATES as $si => [$S, $code, $cities]) {
            // ---- state sub-admin login for this state
            $aid = $this->ins('users', ['name' => "Demo State Admin $S", 'email' => 'demo.' . strtolower($code) . '.admin@demo.abmm.test', 'password' => $pw, 'role' => 'state_admin', 'pradesh' => $S, 'country' => 'India', 'is_active' => true, 'must_change_password' => false,
                'permissions' => json_encode(config('permissions.defaults.state_admin'))]);
            $admins[$S] = $aid; $out[] = ['State Sub-Admin', $S, 'demo.' . strtolower($code) . '.admin@demo.abmm.test'];

            // ---- the rest of the chain for the first city: District Admin > City Admin > Zone Admin (and the Locations master rows they sit on)
            $node = function (?int $parent, string $level, string $name): int {
                $q = DB::table('geo_nodes')->where(['level' => $level, 'name' => $name]); $parent === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parent);
                return $q->value('id') ?: DB::table('geo_nodes')->insertGetId(['parent_id' => $parent, 'level' => $level, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            };
            $city = $cities[0]; $lc = strtolower($code);
            $g = $node($node($node($node(null, 'country', 'INDIA'), 'state', $S), 'district', $city), 'city', $city);
            $zn = $node($g, 'zone', 'Zone 1'); $node($zn, 'pincode', '30' . str_pad((string) ($si + 1), 4, '0', STR_PAD_LEFT));
            DB::table('zones')->updateOrInsert(['country' => 'INDIA', 'city' => $city, 'name' => 'Zone 1'], ['state' => $S, 'district' => $city, 'sort' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['district_operator', 'district', "Demo District Admin $city", 'District Admin', ['pradesh' => $S, 'district' => $city, 'country' => 'INDIA']],
                      ['city_admin', 'city', "Demo City Admin $city", 'City Admin', ['pradesh' => $S, 'district' => $city, 'country' => 'INDIA', 'city' => $city]],
                      ['zone_coordinator', 'zone', "Demo Zone Admin $city Zone 1", 'Zone Admin', ['pradesh' => $S, 'district' => $city, 'country' => 'INDIA', 'city' => $city, 'zone' => 'Zone 1']]] as [$role, $tag, $nm, $label, $area]) {
                $em = "demo.$lc.$tag@demo.abmm.test";
                $this->ins('users', ['name' => $nm, 'email' => $em, 'password' => $pw, 'role' => $role, 'is_active' => true, 'must_change_password' => false, 'permissions' => json_encode(config("permissions.defaults.$role")), 'created_by' => $aid] + $area);
                $out[] = [$label, $S, $em];
            }

            // ---- 6 families (head + one more member) with a member-app login each
            for ($i = 1; $i <= 6; $i++) {
                $city = $this->n($cities, $i); $sur = $this->n(self::SURNAMES, $si * 6 + $i); $mobile = '9' . ($si + 1) . $i . str_pad((string) (1000 + $si * 10 + $i), 7, '0');
                $f = new Family;
                $f->forceFill(['family_code' => sprintf('DM%s%02d', $code, $i), 'surname' => $sur, 'head_name' => $this->n(self::MALE, $si + $i) . ' ' . $sur, 'father_husband_name' => 'Late ' . $this->n(self::MALE, $i + 3) . " $sur", 'mobile' => $mobile,
                    'gender' => 'Male', 'address' => "Plot $i, Maheshwari Bhawan Road, $city", 'city' => $city, 'country' => 'India', 'state' => $code, 'pradesh' => $S, 'district' => $city, 'tehsil' => $city, 'pincode' => '4' . str_pad((string) ($si * 10 + $i), 5, '0'),
                    'country_code' => '+91', 'zone' => 'Zone ' . (($i % 3) + 1), 'email' => strtolower("demo.$code$i@demo.abmm.test"), 'dob' => (1965 + $i * 3) . '-0' . (($i % 9) + 1) . '-15', 'marital_status' => 'Married', 'status' => 'active', 'member_category' => 'general',
                    'profession' => 'Business', 'blood_group' => $this->n(['O+', 'A+', 'B+', 'AB+'], $i), 'caste' => 'Maheshwari', 'gotra' => $this->n(self::GOTRA, $i), 'source' => 'demo'])->save();
                $f->syncHead();
                DB::table('demo_records')->insert(['tbl' => 'families', 'rec_id' => $f->id]);
                $head = DB::table('members')->where(['family_id' => $f->id, 'is_head' => 1])->first();
                $this->ins('members', ['family_id' => $f->id, 'name' => $this->n(self::FEMALE, $si + $i) . ' ' . $sur, 'relation' => 'Wife', 'gender' => 'Female', 'dob' => (1968 + $i * 3) . '-05-20', 'status' => 'active', 'approval' => 'approved', 'surname' => $sur, 'city' => $city, 'caste' => 'Maheshwari', 'gotra' => $this->n(self::GOTRA, $i)]);
                $uid = $this->ins('users', ['name' => $f->head_name, 'email' => "m$mobile@demo.abmm.test", 'mobile' => $mobile, 'password' => Hash::make(uniqid('', true)), 'role' => 'member', 'family_id' => $f->id, 'member_id' => $head?->id, 'is_active' => true, 'country' => 'India', 'pradesh' => $S, 'city' => $city, 'must_change_password' => false]);
                $families[$S][$i] = $f->fresh(); $users[$S][$i] = $uid;
            }
            $fam = fn (int $i) => $families[$S][$i];
            $uidOf = fn (int $i) => $users[$S][$i];

            // ---- committee (state level)
            foreach (['President', 'Vice President', 'Secretary', 'Treasurer', 'Joint Secretary', 'Member'] as $k => $des) {
                $this->ins('committee_members', ['name' => $this->n(self::MALE, $k + $si) . ' ' . $this->n(self::SURNAMES, $k + $si + 7), 'designation' => $des, 'level' => 'pradesh', 'mobile' => '98' . ($si + 1) . '0' . $k . '00' . $k . $k . '1', 'pradesh' => $S, 'district' => $this->n($cities, $k), 'is_active' => true, 'term_start' => '2025-04-01', 'term_end' => '2028-03-31', 'surname' => $this->n(self::SURNAMES, $k)]);
            }
            // ---- matrimony (6 per state, family-owned)
            for ($i = 1; $i <= 6; $i++) {
                $f = $fam($i); $g = $i % 2 ? 'Male' : 'Female';
                $this->ins('matrimony_profiles', ['parinay_id' => (string) random_int(1000000000, 9999999999), 'family_id' => $f->id, 'name' => ($g === 'Male' ? $this->n(self::MALE, $i + $si) : $this->n(self::FEMALE, $i + $si)) . ' ' . $f->surname, 'gender' => $g, 'dob' => (1994 - $i) . '-0' . $i . '-12',
                    'qualification' => $this->n(['B.Com', 'MBA', 'B.Tech', 'CA', 'M.Com', 'MBBS'], $i), 'occupation_type' => 'Private', 'occupation' => $this->n(['Chartered Accountant', 'Software Engineer', 'Business', 'Doctor', 'Teacher', 'Banker'], $i), 'height_cm' => 150 + $i * 4, 'mobile' => $f->mobile, 'address' => $f->address,
                    'state' => $S, 'pradesh' => $S, 'district' => $f->district, 'city' => $f->city, 'country' => 'India', 'status' => $i <= 4 ? 'paid' : 'registered', 'paid_until' => $i <= 4 ? now()->addMonths(6)->toDateString() : null, 'marital_status' => 'Never Married', 'gotra' => $f->gotra,
                    'annual_income' => 400000 + $i * 150000, 'diet' => 'Vegetarian', 'manglik' => $i % 4 === 0, 'visible' => true, 'about' => 'Demo profile for testing.', 'registered_at' => now()->subDays($i * 5)]);
            }
            // ---- businesses (6), reviews, ads, products, quotations, benefits claims
            $biz = [];
            foreach (['Rathi Textiles', 'Mohta Sweets', 'Somani Traders', 'Kalani Electronics', 'Bajaj Jewellers', 'Heda Pharma'] as $k => $bn) {
                $f = $fam($k + 1);
                $biz[$k] = $this->ins('businesses', ['family_id' => $f->id, 'name' => "$bn " . $f->city, 'type' => $this->n(['Textile', 'Food', 'Trading', 'Electronics', 'Jewellery', 'Pharma'], $k), 'address' => $f->address, 'mobile' => $f->mobile, 'email' => $f->email, 'description' => 'Demo business listing.',
                    'status' => $k < 5 ? 'active' : 'pending', 'contact_person' => $f->head_name, 'pradesh' => $S, 'district' => $f->district, 'country' => 'India', 'city' => $f->city, 'zone' => $f->zone, 'org_kind' => 'business', 'listing_until' => now()->addMonths(5)->toDateString(), 'seller_verified' => $k < 3, 'views' => 20 + $k * 7]);
                if ($k < 5) $this->ins('business_reviews', ['business_id' => $biz[$k], 'reviewer_name' => $this->n(self::MALE, $k) . ' ' . $this->n(self::SURNAMES, $k + 3), 'rating' => 3 + ($k % 3), 'comment' => 'Good service (demo).', 'status' => 'approved']);
                if ($k < 5) $this->ins('business_ads', ['business_id' => $biz[$k], 'title' => "$bn - Festive offer", 'starts_on' => now()->subDays(3)->toDateString(), 'ends_on' => now()->addDays(27)->toDateString(), 'amount' => 500 + $k * 100, 'status' => $k < 4 ? 'approved' : 'pending', 'location' => $S, 'contact_person' => $fam($k + 1)->head_name,
                    'contact_mobile' => $fam($k + 1)->mobile, 'kind' => 'banner', 'impressions' => 120 * ($k + 1), 'clicks' => 9 * ($k + 1), 'user_id' => $uidOf($k + 1), 'target' => json_encode(['pradesh' => $S])]);
                $pid = $this->ins('products', ['business_id' => $biz[$k], 'title' => $this->n(['Cotton fabric (m)', 'Mawa kachori box', 'Wholesale grains (qtl)', 'LED bulbs (pack of 10)', 'Silver coin 10g', 'Paracetamol strip'], $k), 'description' => 'Demo product', 'price' => 100 + $k * 40, 'currency' => 'INR', 'unit' => 'pc', 'moq' => 10, 'status' => 'active', 'views' => 10 + $k]);
                if ($k < 4) $this->ins('quotations', ['product_id' => $pid, 'business_id' => $biz[$k], 'buyer_user_id' => $uidOf(6 - $k), 'buyer_family_id' => $fam(6 - $k)->id, 'qty' => 50, 'message' => 'Need bulk price (demo)', 'status' => $k % 2 ? 'quoted' : 'new', 'quoted_price' => $k % 2 ? 95 + $k : null]);
            }
            // ---- events (district audience + state audience) + RSVPs
            foreach (['Maheshwari Mahasabha State Meet', 'Mahesh Navami Utsav', 'Blood Donation Camp', 'Youth Business Summit', 'Parichay Sammelan'] as $k => $et) {
                $eid = $this->ins('events', ['title' => "$et - $S", 'description' => 'Demo event', 'venue' => $this->n($cities, $k) . ' Maheshwari Bhawan', 'event_date' => now()->addDays(10 + $k * 12)->toDateString(), 'event_time' => '17:00:00', 'kind' => 'event', 'audience' => 'pradesh', 'audience_value' => $S,
                    'is_public' => true, 'created_by' => $aid, 'rsvp_enabled' => true, 'capacity' => 200, 'max_guests' => 3, 'attend_points' => 10, 'state' => $S, 'country' => 'India', 'city' => $this->n($cities, $k)]);
                if ($k < 3) for ($i = 1; $i <= 4; $i++) $this->ins('event_rsvps', ['event_id' => $eid, 'user_id' => $uidOf($i), 'family_id' => $fam($i)->id, 'guests' => $i % 3, 'status' => 'going', 'pass_code' => strtoupper(substr(md5("$eid-$i-" . $this->seq++), 0, 10))]);
            }
            // ---- polls (target = this state) with a question and responses
            for ($k = 0; $k < 5; $k++) {
                $pl = $this->ins('polls', ['kind' => $k % 2 ? 'survey' : 'poll', 'title' => $this->n(['Preferred day for state meet', 'Need for a hostel in the city?', 'Annual function venue', 'Interest in group mediclaim', 'Youth wing activities'], $k) . " ($S)", 'description' => 'Demo poll',
                    'target' => json_encode(['pradesh' => $S]), 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(20), 'show_results' => true, 'is_active' => true, 'created_by' => $aid]);
                $this->ins('poll_questions', ['poll_id' => $pl, 'sort' => 1, 'text' => 'Your opinion?', 'type' => 'single', 'options' => json_encode(['Yes', 'No', 'Not sure']), 'required' => true]);
            }
            // ---- donations (6), help requests (5), feed posts (5), trusts (5), notifications (5)
            for ($i = 1; $i <= 6; $i++) {
                $f = $fam($i);
                $this->ins('donations', ['name' => $f->head_name, 'family_code' => $f->family_code, 'amount' => 501 * $i, 'email' => $f->email, 'mobile' => $f->mobile, 'city' => $f->city, 'status' => $i <= 4 ? 'success' : 'pending', 'paid_at' => $i <= 4 ? now()->subDays($i) : null, 'payment_ref' => "DEMO-DON-$code-$i"]);
            }
            for ($i = 1; $i <= 5; $i++) {
                $f = $fam($i);
                $this->ins('help_requests', ['user_id' => $uidOf($i), 'family_id' => $f->id, 'pradesh' => $S, 'state' => $S, 'district' => $f->district, 'type' => $this->n(['medical', 'education', 'scholarship', 'other', 'medical'], $i), 'title' => $this->n(['Surgery support', 'College fees', 'Scholarship for CA', 'Marriage support', 'Hospital bill'], $i),
                    'details' => 'Demo help request for testing.', 'amount' => 10000 * $i, 'status' => $this->n(['pending', 'approved', 'pending', 'rejected', 'pending'], $i), 'country' => 'India', 'city' => $f->city, 'zone' => $f->zone]);
                $this->ins('feed_posts', ['type' => $this->n(['achievement', 'news', 'achievement', 'condolence', 'news'], $i), 'title' => $this->n(['CA topper from our samaj', 'New Bhawan inauguration', 'State rank in 12th', 'Shraddhanjali', 'Blood camp success'], $i) . " - $S", 'body' => 'Demo feed post.', 'user_id' => $uidOf($i), 'family_id' => $f->id,
                    'pradesh' => $S, 'state' => $S, 'district' => $f->district, 'status' => $i <= 3 ? 'approved' : 'pending', 'country' => 'India', 'city' => $f->city, 'zone' => $f->zone, 'likes_count' => $i * 3, 'publish_at' => now()->subHours($i)]);
                $this->ins('trusts', ['name' => $f->head_name, 'email' => $f->email, 'contact' => $f->mobile, 'trust_category' => $this->n(['Education', 'Medical', 'Scholarship', 'Other', 'Education'], $i), 'trust_name' => $this->n(['Mahesh Education Trust', 'Mahesh Medical Trust', 'Scholarship Trust', 'Samaj Seva Trust', 'Mahesh Education Trust'], $i),
                    'city' => $f->city, 'country' => 'INDIA', 'state' => $S, 'district' => $f->district, 'family_id' => $f->id, 'user_id' => $uidOf($i), 'source' => 'demo']);
                $this->ins('notifications_log', ['sent_by' => $aid, 'audience' => 'pradesh', 'audience_value' => $S, 'channel' => 'push', 'message' => 'Demo: ' . $this->n(['Meeting kal shaam 6 baje', 'Blood camp Sunday', 'Mahesh Navami greetings', 'New bhawan opened', 'Vote in the poll'], $i), 'title' => 'ABMM ' . $S, 'member_count' => 6, 'delivery_status' => 'sent']);
            }
            // ---- jobs board (5, area stamped), job seekers (5), applications
            for ($i = 1; $i <= 5; $i++) {
                $f = $fam($i);
                $j = $this->ins('jobs_board', ['user_id' => $uidOf($i), 'family_id' => $f->id, 'pradesh' => $S, 'state' => $S, 'district' => $f->district, 'job_type' => 'full_time', 'title' => $this->n(['Accountant', 'Sales Executive', 'Software Developer', 'Store Manager', 'CA Article Assistant'], $i), 'company' => $this->n(['Rathi & Co', 'Mohta Group', 'Somani Tech', 'Kalani Retail', 'Bajaj CA'], $i),
                    'city' => $f->city, 'salary' => (15 + $i * 3) . 'k/month', 'experience' => $i . '-' . ($i + 2) . ' yrs', 'vacancies' => $i, 'description' => 'Demo job posting.', 'contact_name' => $f->head_name, 'contact_phone' => $f->mobile, 'status' => 'live', 'expires_at' => now()->addDays(30), 'country' => 'India', 'zone' => $f->zone, 'category' => 'General', 'views' => $i * 11]);
                $this->ins('job_profiles', ['user_id' => $uidOf(6 - $i), 'pradesh' => $S, 'district' => $f->district, 'name' => $fam(6 - $i)->head_name, 'headline' => $this->n(['Fresher B.Com', 'Experienced accountant', 'MBA Marketing', 'Java developer', 'Data entry operator'], $i), 'education' => 'Graduate', 'experience_years' => $i, 'skills' => 'Tally, Excel', 'city' => $fam(6 - $i)->city,
                    'phone' => $fam(6 - $i)->mobile, 'open_to_work' => true, 'country' => 'India', 'zone' => $fam(6 - $i)->zone]);
                if ($i <= 3) $this->ins('job_applications', ['job_id' => $j, 'user_id' => $uidOf(6 - $i), 'note' => 'Demo application', 'status' => $this->n(['applied', 'shortlisted', 'applied'], $i)]);
            }
            // ---- vendors (5), directory entries (5: bhawan/hotel/hospital/clinic/org), connections, benefits
            foreach (['caterer', 'decorator', 'photographer', 'pandit', 'dj'] as $k => $vc) {
                $f = $fam($k + 1);
                $this->ins('event_vendors', ['user_id' => $uidOf($k + 1), 'family_id' => $f->id, 'category' => $vc, 'name' => ucfirst($vc) . ' Services ' . $f->city, 'contact_person' => $f->head_name, 'phone' => $f->mobile, 'email' => $f->email, 'country' => 'India', 'city' => $f->city, 'zone' => $f->zone,
                    'description' => 'Demo vendor', 'services' => json_encode(['Wedding', 'Reception']), 'price_from' => 15000 + $k * 5000, 'currency' => 'INR', 'verified' => $k < 3, 'status' => 'active', 'listing_until' => now()->addMonths(4)->toDateString(), 'views' => 30 + $k, 'rating_avg' => 4.2, 'rating_count' => 5]);
                $this->ins('directory_entries', ['kind' => $this->n(['bhawan', 'hotel', 'hospital', 'clinic', 'organisation'], $k), 'name' => $this->n(['Maheshwari Bhawan', 'Hotel Mahesh Residency', 'Mahesh Hospital', 'Dr. Rathi Clinic', 'Maheshwari Sabha'], $k) . ' ' . $this->n($cities, $k), 'speciality' => $k === 2 ? 'Multi-speciality' : null,
                    'country' => 'India', 'state' => $S, 'city' => $this->n($cities, $k), 'zone' => 'Zone 1', 'address' => 'Main Road, ' . $this->n($cities, $k), 'phone' => $f->mobile, 'description' => 'Demo directory entry', 'member_discount_pct' => 10, 'verified' => $k < 4, 'owner_user_id' => $uidOf($k + 1), 'status' => 'active', 'listing_until' => now()->addMonths(6)->toDateString(), 'views' => 15 + $k]);
                if ($k < 4) $this->ins('connections', ['user_id' => $uidOf($k + 1), 'target_id' => $uidOf($k + 2), 'status' => 'accepted']);
            }
        }
        $this->partners($users, $families, $out);
        foreach (self::STATES as $si => [$S, $code, $cities]) { $this->milestoneE($si, $S, $cities, $users[$S], $families[$S], $admins[$S]); $this->milestoneF($si, $S, $cities, $users[$S], $families[$S], $admins[$S]); }

        $this->info('Demo data created. State Sub-Admin logins (password: ' . $this->option('password') . '):');
        $this->table(['Role', 'State', 'Email'], $out);
        $this->line('Member-app logins use mobile OTP. Set TEST_OTP=123456 in .env on a TEST server only, then log in with the mobiles shown above.');
    }

    private array $fx = [];

    /** Milestone F demo rows: academy, live classes, library, exam bank + exams + attempts, learner segments, Housie. Global content once; per-state learners, enrolments, attempts, tickets, claims. */
    private function milestoneF(int $si, string $S, array $cities, array $uids, array $fams, int $aid): void
    {
        $geo = fn (int $i) => ['country' => 'India', 'pradesh' => $S, 'district' => $fams[$i]->district, 'city' => $fams[$i]->city, 'zone' => $fams[$i]->zone];
        $fid = fn (int $i) => $fams[$i]->id;
        $segs = ['school', 'college', 'business', 'family'];

        if ($si === 0) {
            // ---- courses + lessons
            $courses = [['Basics of GST for small traders', 'business', 499, 20, false, 'Taxation'], ['Spoken English in 30 days', 'general', 0, 0, true, 'Language'], ['Class 10 Maths - quick revision', 'school', 199, 0, false, 'School'], ['Resume and interview skills', 'college', 299, 25, false, 'Career'],
                ['Family finance and insurance basics', 'family', 0, 0, false, 'Money'], ['Digital marketing for shop owners', 'business', 599, 15, false, 'Marketing'], ['Vedic maths tricks', 'school', 0, 0, true, 'School']];
            foreach ($courses as $k => [$t, $sg, $pr, $sh, $free, $cat]) {
                $cid = $this->ins('courses', ['title' => $t, 'segment' => $sg, 'category' => $cat, 'description' => 'Demo course: short Hinglish videos with summary notes.', 'instructor_user_id' => $sh ? $this->fx['instr'] ??= array_values($uids)[1] : null, 'instructor_name' => $sh ? 'Demo Instructor' : 'ABMM Academy',
                    'instructor_share_pct' => $sh, 'price' => $pr, 'currency' => 'INR', 'free' => $free, 'subscription' => true, 'lang' => 'Hinglish', 'ai_generated' => $k % 3 === 0, 'sort' => $k, 'status' => $k === 6 ? 'draft' : 'live']);
                $this->fx['courses'][] = [$cid, $pr, $sh];
                foreach (['Introduction', 'Core idea explained', 'Worked example', 'Common mistakes', 'Summary and next steps'] as $li => $lt) $this->ins('lessons', ['course_id' => $cid, 'title' => $lt, 'provider' => $li % 2 ? 'vimeo' : 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=demo' . $cid . $li, 'duration_sec' => 240 + $li * 60,
                    'free_preview' => $li === 0, 'summary' => 'Demo summary for ' . $lt . '.', 'script' => 'Demo narration script.', 'sort' => $li, 'status' => 'live']);
            }
            // ---- live classes, library
            foreach ([[1, 'Q&A: GST filing doubts', 'business', 'zoom', false], [2, 'Spoken English practice circle', 'general', 'meet', false], [3, 'Board exam strategy', 'school', 'meet', true], [5, 'Career talk: first job', 'college', 'zoom', true], [8, 'Family money planning', 'family', 'zoom', false]] as $k => [$d, $t, $sg, $pv, $so])
                $this->ins('live_classes', ['course_id' => $this->fx['courses'][$k][0], 'title' => $t, 'segment' => $sg, 'host' => 'Demo Host', 'provider' => $pv, 'join_url' => 'https://' . ($pv === 'zoom' ? 'zoom.us/j/' : 'meet.google.com/') . 'demo' . $k, 'starts_at' => now()->addDays($d)->setTime(18, 0), 'duration_min' => 60, 'subscribers_only' => $so, 'status' => 'scheduled']);
            foreach ([['GST quick guide (PDF)', 'pdf', 'business', 'Taxation'], ['Scholarships for students', 'article', 'college', 'Education'], ['Healthy family budget sheet', 'pdf', 'family', 'Money'], ['NCERT Maths revision notes', 'link', 'school', 'School'], ['Resume templates', 'pdf', 'college', 'Career'], ['Startup basics video', 'video', 'business', 'Business']] as $k => [$t, $kd, $sg, $cat])
                $this->ins('library_items', ['title' => $t, 'kind' => $kd, 'segment' => $sg, 'category' => $cat, 'url' => 'https://example.com/library/' . $k, 'description' => 'Demo library item.', 'views' => 10 * $k, 'status' => 'live']);

            // ---- question bank (6 per segment) + exams (one closed last week, one live this week per segment)
            $bank = [
                'school' => [['GK', 'What is the capital of India?', ['Mumbai', 'New Delhi', 'Jaipur', 'Kolkata'], 1, 'The capital is New Delhi.'], ['Maths', 'What is 12 x 12?', ['124', '134', '144', '154'], 2, '12 x 12 = 144.'], ['Science', 'What is the chemical formula of water?', ['CO2', 'H2O', 'O2', 'NaCl'], 1, 'Paani = H2O.'],
                    ['GK', 'In which city is the Taj Mahal?', ['Agra', 'Delhi', 'Jaipur', 'Lucknow'], 0, 'The Taj Mahal is in Agra.'], ['Maths', 'What is 25% of 100?', ['20', '25', '30', '35'], 1, '100 ka 25% = 25.'], ['Science', 'In which direction does the sun rise?', ['Paschim', 'Uttar', 'Purab', 'Dakshin'], 2, 'The sun rises in the east.']],
                'college' => [['Aptitude', 'What is 20% of 15?', ['2', '3', '4', '5'], 1, '15 x 0.2 = 3.'], ['Current affairs', 'What is the full form of RBI?', ['Reserve Bank of India', 'Regional Bank of India', 'Rural Bank of India', 'Royal Bank of India'], 0, 'RBI = Reserve Bank of India.'], ['Aptitude', 'If 5 pens cost Rs 50, how much do 8 pens cost?', ['70', '80', '90', '100'], 1, '1 pen = 10, 8 pen = 80.'],
                    ['Career', 'What matters most in a CV?', ['Photo', 'Clear skills & experience', 'Long essay', 'Hobbies only'], 1, 'Skills and experience should be clear.'], ['English', 'Synonym of "Rapid"?', ['Slow', 'Quick', 'Heavy', 'Weak'], 1, 'Rapid = Quick.'], ['Reasoning', '2, 4, 8, 16, ? agla number?', ['24', '30', '32', '36'], 2, 'Har baar double: 32.']],
                'business' => [['GST', 'What is the full form of GST?', ['Goods and Services Tax', 'General Sales Tax', 'Goods Sale Tax', 'Government Service Tax'], 0, 'GST = Goods and Services Tax.'], ['Accounts', 'Profit = ?', ['Sale - Cost', 'Sale + Cost', 'Cost - Sale', 'Sale x Cost'], 0, 'Profit = Sale minus Cost.'], ['Marketing', 'How do you make a customer repeat?', ['Bura service', 'Good service and follow-up', 'Daam badhana', 'Dukaan band'], 1, 'Through service and follow-up.'],
                    ['Finance', 'What is working capital?', ['Current assets - current liabilities', 'Total assets', 'Only cash', 'Profit'], 0, 'CA - CL.'], ['Accounts', 'What is an invoice?', ['Bill of sale', 'Bank passbook', 'Salary slip', 'ID card'], 0, 'The bill of sale.'], ['Law', 'What is PAN used for?', ['Income tax', 'Driving', 'Voting', 'Ration'], 0, 'PAN is for income tax.']],
                'family' => [['Health', 'How much water should one drink in a day at minimum?', ['1 glass', '2-3 litre', '10 litre', 'Not needed'], 1, '2-3 litres is right.'], ['Money', 'How many months of expenses should an emergency fund cover?', ['1 hafta', '3-6 months', '10 saal', 'Not needed'], 1, '3-6 months of expenses.'], ['Culture', 'Who is Mahesh, the kul devta of the Maheshwari samaj?', ['Vishnu', 'Shiv', 'Brahma', 'Ganesh'], 1, 'Maheshwari = the followers of Mahesh (Shiv).'],
                    ['Safety', 'What is the police number in an emergency?', ['100 / 112', '200', '300', '500'], 0, '112 ya 100.'], ['Insurance', 'What is term insurance for?', ['Jeevan suraksha', 'Savings only', 'Car', 'Mobile'], 0, 'Protection of the family.'], ['Health', 'How long is a good daily walk?', ['5 min', '30 min', '8 ghante', 'Never'], 1, '30 min walk.']],
            ];
            foreach ($bank as $sg => $rows) {
                foreach ($rows as [$sub, $q, $opts, $c, $ex]) $this->fx['q'][$sg][] = $this->ins('exam_questions', ['segment' => $sg, 'subject' => $sub, 'question' => $q, 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE), 'correct' => $c, 'explanation' => $ex, 'difficulty' => 'easy', 'is_active' => true]);
                $ids = json_encode($this->fx['q'][$sg]);
                $this->fx['exam'][$sg]['closed'] = $this->ins('exams', ['title' => 'Weekly quiz - ' . ucfirst($sg) . ' (last week)', 'segment' => $sg, 'kind' => 'weekly', 'starts_at' => now()->subWeek()->startOfWeek(), 'ends_at' => now()->subWeek()->endOfWeek(), 'duration_min' => 10, 'pass_pct' => 40, 'question_ids' => $ids, 'status' => 'closed', 'results_sent' => true]);
                $this->fx['exam'][$sg]['live'] = $this->ins('exams', ['title' => 'Weekly quiz - ' . ucfirst($sg) . ' (this week)', 'segment' => $sg, 'kind' => 'weekly', 'starts_at' => now()->startOfWeek(), 'ends_at' => now()->endOfWeek(), 'duration_min' => 10, 'pass_pct' => 40, 'question_ids' => $ids, 'status' => 'live']);
            }

            // ---- housie
            $sp = [$this->ins('housie_sponsors', ['name' => 'Demo Sweets & Namkeen', 'banner_url' => 'https://example.com/banner1.png', 'link_url' => 'https://example.com', 'status' => 'active']), $this->ins('housie_sponsors', ['name' => 'Demo Jewellers', 'banner_url' => 'https://example.com/banner2.png', 'link_url' => 'https://example.com', 'status' => 'active'])];
            $pz = json_encode(['early5' => 'Gift voucher Rs 200', 'line1' => 'Gift voucher Rs 300', 'line2' => 'Gift voucher Rs 300', 'line3' => 'Gift voucher Rs 300', 'full' => 'Gold coin / Rs 2000 voucher'], JSON_UNESCAPED_UNICODE);
            $h = app(\App\Services\HousieService::class);
            $this->fx['hg'] = [
                $this->ins('housie_games', ['title' => 'Sunday Housie (finished)', 'starts_at' => now()->subDays(3), 'ticket_price' => 0, 'currency' => 'INR', 'max_tickets' => 4, 'interval_sec' => 20, 'prizes' => $pz, 'sponsor_id' => $sp[0], 'sequence' => json_encode(range(1, 90)), 'called' => json_encode(range(1, 90)), 'last_called_at' => now()->subDays(3), 'status' => 'finished']),
                $this->ins('housie_games', ['title' => 'Next Sunday Housie', 'starts_at' => now()->addDays(4)->setTime(20, 0), 'ticket_price' => 59, 'currency' => 'INR', 'max_tickets' => 4, 'interval_sec' => 20, 'prizes' => $pz, 'sponsor_id' => $sp[1], 'status' => 'scheduled']),
                $this->ins('housie_games', ['title' => 'Family special Housie', 'starts_at' => now()->addDays(11)->setTime(20, 0), 'ticket_price' => 0, 'currency' => 'INR', 'max_tickets' => 2, 'interval_sec' => 25, 'prizes' => $pz, 'sponsor_id' => $sp[0], 'status' => 'scheduled']),
            ];
            $this->fx['h'] = $h;
        }

        // ---- per state: learner profiles, enrolments, progress, earnings, attempts, tickets, claims
        $inst = $this->fx['instr'] ?? null;
        for ($k = 1; $k <= 6; $k++) $this->ins('learner_profiles', ['user_id' => $uids[$k], 'family_id' => $fid($k), 'segment' => $segs[$k % 4], 'class_name' => $this->n(['Class 10', 'B.Com 2nd year', 'Shop owner', 'Family'], $k), 'institute' => $this->n(['Demo School', 'Demo College', 'Own business', null], $k)] + $geo($k));
        foreach ([[1, 0], [2, 1], [3, 2], [4, 3], [5, 4], [6, 5], [1, 1]] as $k => [$u, $ci]) {
            [$cid, $pr, $sh] = $this->fx['courses'][$ci];
            $this->ins('course_enrolments', ['course_id' => $cid, 'user_id' => $uids[$u], 'source' => $pr > 0 ? 'purchase' : 'free', 'status' => 'active', 'completed_at' => $k % 4 === 0 ? now()->subDays(2) : null]);
            if ($pr > 0 && $sh && $inst) $this->ins('instructor_earnings', ['course_id' => $cid, 'instructor_user_id' => $inst, 'payment_id' => 900000 + $si * 100 + $k, 'gross' => $pr, 'share_pct' => $sh, 'amount' => round($pr * $sh / 100, 2), 'status' => $k % 2 ? 'paid' : 'pending']);
            $lid = DB::table('lessons')->where('course_id', $cid)->orderBy('sort')->value('id');
            if ($lid) $this->ins('lesson_progress', ['user_id' => $uids[$u], 'lesson_id' => $lid, 'course_id' => $cid, 'percent' => $k % 3 === 0 ? 100 : 40 + $k * 5, 'completed_at' => $k % 3 === 0 ? now()->subDay() : null]);
        }
        // exam attempts (last week closed exam + a few on this week's exam)
        foreach ([1, 2, 3, 4, 5, 6] as $k => $u) {
            $sg = $segs[$u % 4]; $qs = $this->fx['q'][$sg]; $correct = [3, 5, 6, 4, 2, 5][$k]; $pc = round($correct / 6 * 100, 2);
            $this->ins('exam_attempts', ['exam_id' => $this->fx['exam'][$sg]['closed'], 'user_id' => $uids[$u], 'family_id' => $fid($u), 'started_at' => now()->subDays(6), 'submitted_at' => now()->subDays(6)->addMinutes(4 + $k), 'answers' => json_encode([]), 'score' => $correct, 'total' => 6, 'percent' => $pc, 'seconds' => 240 + $k * 40, 'rank' => $k + 1] + $geo($u));
            if ($k < 3) $this->ins('exam_attempts', ['exam_id' => $this->fx['exam'][$sg]['live'], 'user_id' => $uids[$u], 'family_id' => $fid($u), 'started_at' => now()->subHours(3), 'submitted_at' => now()->subHours(3)->addMinutes(5), 'answers' => json_encode([]), 'score' => 4 + $k % 2, 'total' => 6, 'percent' => round((4 + $k % 2) / 6 * 100, 2), 'seconds' => 280, 'rank' => null] + $geo($u));
        }
        // housie tickets (finished free game + next game) and two claims on the finished game
        $h = $this->fx['h'];
        foreach ([1, 2, 3, 4, 5] as $k => $u) {
            $t1 = $this->ins('housie_tickets', ['game_id' => $this->fx['hg'][0], 'user_id' => $uids[$u], 'grid' => json_encode($h->generateTicket()), 'status' => 'paid']);
            $this->ins('housie_tickets', ['game_id' => $this->fx['hg'][1], 'user_id' => $uids[$u], 'grid' => json_encode($h->generateTicket()), 'status' => $k < 4 ? 'paid' : 'pending']);
            if ($k < 2) $this->ins('housie_claims', ['game_id' => $this->fx['hg'][0], 'ticket_id' => $t1, 'user_id' => $uids[$u], 'prize' => $k === 0 ? 'early5' : 'line1', 'status' => 'won']);
        }
    }

    /** Milestone E demo rows for one state: careers & mentoring, blood donors, donation campaigns (80G), investments. 5-10 records per module. */
    private function milestoneE(int $si, string $S, array $cities, array $uids, array $fams, int $aid): void
    {
        $city = fn (int $i) => $this->n($cities, $i);
        $geo = fn (int $i) => ['country' => 'India', 'pradesh' => $S, 'district' => $fams[$i]->district, 'city' => $fams[$i]->city, 'zone' => $fams[$i]->zone];
        $fid = fn (int $i) => $fams[$i]->id;

        // ---- careers: job packs (once), featured job, alerts, drives, mentors
        if ($si === 0) {
            $this->ins('job_packs', ['name' => 'Featured job - 7 days', 'kind' => 'featured', 'price' => 236, 'currency' => 'INR', 'days' => 7, 'sort' => 1, 'is_active' => true, 'description' => 'Shown at the top of the job list']);
            $this->ins('job_packs', ['name' => 'Paid job post - 30 days', 'kind' => 'post', 'price' => 118, 'currency' => 'INR', 'days' => 30, 'sort' => 2, 'is_active' => true, 'description' => 'Used when paid posting is switched on']);
        }
        if ($j = DB::table('jobs_board')->where('pradesh', $S)->where('status', 'live')->value('id')) DB::table('jobs_board')->where('id', $j)->update(['featured_until' => now()->addDays(7)]);
        foreach ([['accountant', 'instant'], ['developer', 'daily'], ['sales', 'instant']] as $k => [$kw, $fr]) $this->ins('job_alerts', ['user_id' => $uids[$k + 1], 'keywords' => $kw, 'city' => $city($k), 'frequency' => $fr, 'active' => true]);
        $drv = [];
        foreach ([['campus', 'Campus placement - ' . $city(0) . ' Commerce College', 'B.Com / M.Com'], ['internship', 'Summer internship - accounts', 'Final year'], ['walkin', 'Walk-in interview - sales', 'Any graduate'], ['campus', 'Engineering campus drive', 'B.Tech CS/IT'], ['internship', 'Marketing internship', 'MBA']] as $k => [$ty, $ti, $el]) {
            $drv[$k] = $this->ins('placement_drives', ['user_id' => $uids[$k + 1], 'family_id' => $fid($k + 1), 'drive_type' => $ty, 'title' => $ti, 'institution' => $ty === 'campus' ? $city($k) . ' College' : null, 'company' => 'Demo ' . $this->n(self::SURNAMES, $si + $k) . ' & Co', 'drive_date' => now()->addDays(14 + $k * 3)->toDateString(),
                'last_date' => now()->addDays(10 + $k * 3)->toDateString(), 'positions' => 3 + $k, 'stipend' => $ty === 'internship' ? '8000/month' : '3-5 LPA', 'eligibility' => $el, 'description' => 'Demo drive for testing.', 'venue' => $city($k), 'contact_phone' => $fams[$k + 1]->mobile, 'status' => $k < 4 ? 'live' : 'pending'] + $geo($k + 1));
        }
        foreach ([[0, 6], [0, 5], [1, 4], [2, 3], [3, 2], [3, 6]] as $k => [$d, $u]) $this->ins('drive_registrations', ['drive_id' => $drv[$d], 'user_id' => $uids[$u], 'college' => $city($k) . ' College', 'course' => $this->n(['B.Com', 'MBA', 'B.Tech'], $k), 'status' => $this->n(['registered', 'shortlisted', 'selected', 'registered'], $k)]);
        $mp = [];
        foreach ([['CA with 18 years in taxation and audit', 'Finance', 'accounting, taxation, gst, audit', 18], ['Software architect, ex-product company', 'IT', 'java, python, cloud, interviews', 12], ['Marketing head - FMCG', 'Marketing', 'branding, sales, marketing, digital', 15],
            ['Doctor and healthcare consultant', 'Healthcare', 'medicine, neet, healthcare careers', 20], ['Startup founder and angel investor', 'Business', 'startups, fundraising, business planning', 10]] as $k => [$h, $in, $ex, $yrs]) {
            $mp[$k] = $this->ins('mentor_profiles', ['user_id' => $uids[$k + 1], 'family_id' => $fid($k + 1), 'headline' => $h, 'industry' => $in, 'expertise' => $ex, 'experience_years' => $yrs, 'languages' => 'Hindi, English', 'mode' => 'both', 'bio' => 'Demo mentor profile.', 'max_mentees' => 3,
                'offers_counselling' => $k % 2 === 0, 'verified' => $k < 4, 'status' => $k < 4 ? 'approved' : 'pending'] + $geo($k + 1));
        }
        foreach ([4, 5, 6] as $k => $u) $this->ins('mentee_profiles', ['user_id' => $uids[$u], 'family_id' => $fid($u), 'stage' => $this->n(['student', 'graduate', 'working'], $k), 'education' => 'B.Com', 'interests' => 'accounting, taxation', 'goals' => 'Build a career in finance', 'mode' => 'online'] + $geo($u));
        foreach ([[0, 6, 'active', 'mentoring', 'CA career path'], [1, 5, 'requested', 'mentoring', 'Moving into IT'], [2, 4, 'completed', 'counselling', 'Career counselling after 12th']] as $k => [$m, $mentee, $st, $kind, $topic]) {
            $ms = $this->ins('mentorships', ['mentor_id' => $mp[$m], 'mentee_user_id' => $uids[$mentee], 'mentor_user_id' => $uids[$m + 1], 'kind' => $kind, 'topic' => $topic, 'message' => 'Demo request', 'status' => $st]);
            if ($st !== 'requested') $this->ins('mentor_sessions', ['mentorship_id' => $ms, 'scheduled_at' => $st === 'completed' ? now()->subDays(5) : now()->addDays(3), 'duration_min' => 45, 'mode' => 'online', 'meeting_link' => 'https://meet.example.com/demo', 'agenda' => 'Roadmap', 'status' => $st === 'completed' ? 'done' : 'scheduled', 'rating' => $st === 'completed' ? 5 : null]);
        }

        // ---- blood donors, requests, camps, emergency directory
        foreach (range(1, 6) as $i) $this->ins('blood_donors', ['user_id' => $uids[$i], 'family_id' => $fid($i), 'blood_group' => $this->n(['O+', 'B+', 'A+', 'AB+', 'O-', 'A-'], $i + $si), 'last_donated_on' => $i === 3 ? now()->subDays(20)->toDateString() : ($i > 4 ? now()->subDays(200)->toDateString() : null),
            'available' => true, 'emergency_ok' => true, 'donations_count' => $i % 4, 'status' => 'active'] + $geo($i));
        $rq = [];
        foreach ([['Urgent surgery', 'B+', 'emergency', 'open'], ['Thalassemia patient', 'O+', 'normal', 'open'], ['Accident case', 'A+', 'emergency', 'open'], ['Delivery', 'O-', 'normal', 'fulfilled'], ['Planned operation', 'AB+', 'normal', 'closed']] as $k => [$pt, $bg, $urg, $st]) {
            $rq[$k] = $this->ins('blood_requests', ['user_id' => $uids[$k + 1], 'family_id' => $fid($k + 1), 'patient_name' => $pt, 'blood_group' => $bg, 'units' => 1 + $k % 3, 'hospital' => $city($k) . ' Mahesh Hospital', 'needed_by' => now()->addDays($k)->toDateString(), 'urgency' => $urg,
                'contact_name' => $fams[$k + 1]->head_name, 'contact_phone' => $fams[$k + 1]->mobile, 'note' => 'Demo request', 'status' => $st, 'notified' => 3] + $geo($k + 1));
        }
        foreach ([[0, 2, 'approved'], [0, 3, 'pending'], [2, 4, 'pending'], [3, 5, 'donated']] as [$r, $d, $st]) $this->ins('blood_links', ['donor_user_id' => $uids[$d], 'requester_user_id' => $uids[$r + 1], 'request_id' => $rq[$r], 'status' => $st]);
        $bd = [];
        foreach (['Raktdaan Shivir', 'Mahesh Navami Blood Camp', 'Youth Wing Camp', 'Thalassemia Support Camp', 'Annual Camp'] as $k => $t) {
            $bd[$k] = $this->ins('blood_drives', ['user_id' => $uids[$k + 1], 'title' => "$t - " . $city($k), 'organiser' => 'Maheshwari Yuva Mandal', 'venue' => $city($k) . ' Maheshwari Bhawan', 'drive_date' => now()->addDays(6 + $k * 7)->toDateString(), 'timing' => '9am - 2pm', 'description' => 'Demo blood camp.',
                'contact_phone' => $fams[$k + 1]->mobile, 'target_units' => 50 + $k * 10, 'collected_units' => 0, 'status' => $k < 4 ? 'live' : 'pending'] + $geo($k + 1));
        }
        foreach ([[0, 1], [0, 2], [1, 3], [2, 4], [3, 5]] as [$d, $u]) $this->ins('blood_drive_regs', ['drive_id' => $bd[$d], 'user_id' => $uids[$u], 'attended' => false, 'donated' => false]);
        foreach ([['ambulance', 'Mahesh Seva Ambulance'], ['blood_bank', 'Maheshwari Blood Bank']] as $k => [$kind, $nm]) $this->ins('directory_entries', ['kind' => $kind, 'name' => "$nm " . $city($k), 'country' => 'India', 'state' => $S, 'city' => $city($k), 'zone' => 'Zone 1', 'address' => 'Main Road, ' . $city($k),
            'phone' => $fams[$k + 1]->mobile, 'description' => 'Demo 24x7 emergency service', 'member_discount_pct' => 0, 'verified' => true, 'status' => 'active', 'listing_until' => now()->addMonths(6)->toDateString()]);

        // ---- donation campaigns, utilisation reports, relief requests, volunteers
        if ($si === 0) {
            $this->ins('donation_entities', ['name' => 'Maheshwari Seva Trust (demo)', 'kind' => 'trust', 'pan' => 'AAATM1234C', 'has_80g' => true, 'g80_number' => 'AAATM1234CF20214', 'g80_valid_from' => '2021-04-01', 'g80_valid_to' => now()->addYears(3)->toDateString(),
                'address' => 'Maheshwari Bhawan, Indore', 'signatory' => 'Managing Trustee', 'country' => 'India', 'is_active' => true]);
            $this->ins('donation_entities', ['name' => 'Community Welfare Fund (demo)', 'kind' => 'unit', 'has_80g' => false, 'country' => 'India', 'is_active' => true]);
        }
        $e80 = DB::table('donation_entities')->where('name', 'like', 'Maheshwari Seva Trust%')->value('id'); $eno = DB::table('donation_entities')->where('name', 'like', 'Community Welfare%')->value('id');
        $cp = [];
        foreach ([['Scholarship fund - meritorious students', 'education', 100000, 'live'], ['Medical aid for needy families', 'medical', 200000, 'live'], ['Flood relief', 'relief', 150000, 'live'], ['Temple renovation', 'temple', 500000, 'live'], ['Girls hostel (draft)', 'education', 300000, 'draft']] as $k => [$t, $cat, $tgt, $st]) {
            $cp[$k] = $this->ins('campaigns', ['entity_id' => $k % 3 === 2 ? $eno : $e80, 'user_id' => $aid, 'title' => "$t - $S", 'category' => $cat, 'story' => 'Demo campaign story for testing.', 'beneficiary' => 'Needy students / families', 'target_amount' => $tgt, 'raised_amount' => 0, 'currency' => 'INR',
                'starts_on' => now()->subDays(10)->toDateString(), 'ends_on' => now()->addDays(60)->toDateString(), 'featured' => $k === 0, 'status' => $st] + $geo(1 + $k % 6));
        }
        foreach (range(1, 6) as $i) {
            $c = $cp[$i % 4]; $amt = 501 * $i;
            $this->ins('campaign_donations', ['campaign_id' => $c, 'user_id' => $uids[$i], 'family_id' => $fid($i), 'donor_name' => $fams[$i]->head_name, 'donor_mobile' => $fams[$i]->mobile, 'donor_email' => $fams[$i]->email, 'amount' => $amt, 'currency' => 'INR', 'anonymous' => $i === 5, 'status' => 'paid', 'paid_at' => now()->subDays($i)]);
            DB::table('campaigns')->where('id', $c)->increment('raised_amount', $amt); DB::table('campaigns')->where('id', $c)->increment('donors_count');
        }
        foreach ([0, 1] as $k) $this->ins('utilisation_reports', ['campaign_id' => $cp[$k], 'title' => 'Utilisation report ' . ($k + 1), 'amount_spent' => 20000 + $k * 15000, 'details' => 'Funds used as per plan (demo).', 'report_date' => now()->subDays(2 + $k)->toDateString(), 'published' => true]);
        foreach ([['medical', 'Heart surgery help', 'pending'], ['education', 'College fee support', 'verified'], ['relief', 'Fire damage relief', 'approved'], ['medical', 'Dialysis support', 'pending'], ['education', 'Coaching fee', 'rejected']] as $k => [$kind, $t, $st]) {
            $this->ins('assistance_requests', ['user_id' => $uids[$k + 1], 'family_id' => $fid($k + 1), 'kind' => $kind, 'title' => $t, 'beneficiary' => $fams[$k + 1]->head_name, 'details' => 'Demo assistance request.', 'amount_needed' => 25000 + $k * 10000, 'status' => $st, 'amount_approved' => $st === 'approved' ? 25000 : null] + $geo($k + 1));
        }
        foreach (range(1, 5) as $i) $this->ins('volunteers', ['user_id' => $uids[$i], 'family_id' => $fid($i), 'campaign_id' => $i % 2 ? $cp[0] : null, 'skills' => $this->n(['driving', 'first aid', 'teaching', 'accounting', 'event management'], $i), 'availability' => 'Weekends', 'hours_logged' => $i * 4, 'status' => $i <= 3 ? 'active' : 'pending'] + $geo($i));

        // ---- investments & business growth
        $op = [];
        foreach ([['Agri processing unit - partnership', 'partnership', 'Agri'], ['Commercial shop in prime market', 'real_estate', 'Real estate'], ['Seed round: D2C food brand', 'startup', 'Food'], ['Tax-free infrastructure bonds', 'bond', 'Infra'], ['Franchise: pharmacy chain', 'franchise', 'Pharma']] as $k => [$t, $kind, $sec]) {
            $op[$k] = $this->ins('investment_opportunities', ['title' => "$t - $S", 'kind' => $kind, 'sector' => $sec, 'stage' => 'Growth', 'ticket_min' => 100000 * ($k + 1), 'ticket_max' => 500000 * ($k + 1), 'currency' => 'INR', 'expected_return' => 'Illustrative, as stated by issuer', 'tenure' => '3-5 years',
                'summary' => 'Demo opportunity for testing.', 'details' => 'Details shared with serious investors.', 'risk_note' => 'Capital at risk. Not investment advice.', 'featured' => $k === 0, 'status' => $k < 4 ? 'live' : 'draft'] + $geo(1 + $k % 6));
        }
        foreach (range(1, 5) as $i) $this->ins('investor_profiles', ['user_id' => $uids[$i], 'family_id' => $fid($i), 'role' => $this->n(['investor', 'entrepreneur', 'both', 'investor', 'entrepreneur'], $i), 'company' => 'Demo ' . $this->n(self::SURNAMES, $si + $i) . ' Ventures', 'sectors' => $this->n(['agri, food', 'fintech, saas', 'real estate', 'pharma, healthcare', 'retail, d2c'], $i),
            'stage_pref' => 'seed', 'ticket_min' => 200000, 'ticket_max' => 2000000, 'bio' => 'Demo profile.', 'visible' => true, 'verified' => $i <= 3, 'status' => $i <= 4 ? 'approved' : 'pending'] + $geo($i));
        $pt = [];
        foreach ([['KrishiHub marketplace', 'agri'], ['Mawa Foods D2C', 'food'], ['PayEasy for traders', 'fintech'], ['MediQuick pharmacy app', 'healthcare'], ['ShaadiSetu events', 'events']] as $k => [$t, $sec]) {
            $pt[$k] = $this->ins('pitches', ['user_id' => $uids[$k + 1], 'family_id' => $fid($k + 1), 'title' => $t, 'company' => $t, 'sector' => $sec, 'stage' => 'seed', 'ask_amount' => 1000000 * ($k + 1), 'equity_pct' => 8 + $k, 'currency' => 'INR', 'summary' => 'Demo pitch for testing.', 'deck_url' => 'https://example.com/deck.pdf',
                'showcase' => $k < 2, 'interests' => 0, 'status' => $k < 4 ? 'approved' : 'pending'] + $geo($k + 1));
        }
        foreach ([[6, 'o', 0], [6, 'p', 0], [5, 'o', 1], [4, 'p', 1]] as [$u, $ty, $k]) $this->ins('investment_interests', ['user_id' => $uids[$u], 'opportunity_id' => $ty === 'o' ? $op[$k] : null, 'pitch_id' => $ty === 'p' ? $pt[$k] : null, 'amount' => 300000, 'message' => 'Interested (demo)', 'status' => 'new']);
        $this->ins('events', ['title' => "Financial Literacy Webinar: Mutual Funds & Insurance - $S", 'description' => 'Demo webinar', 'venue' => 'Online (Zoom)', 'event_date' => now()->addDays(9 + $si)->toDateString(), 'event_time' => '19:00:00', 'kind' => 'event', 'audience' => 'pradesh', 'audience_value' => $S,
            'is_public' => true, 'created_by' => $aid, 'rsvp_enabled' => true, 'capacity' => 500, 'series' => 'financial_literacy', 'state' => $S, 'country' => 'India', 'city' => $city(0)]);
    }

    /** Marriage Bureau / Event Organiser / Job Recruiter: one partner per kind per state; the first state is the fully filled showcase (12 entries, live subscription, leads). */
    private function partners(array $users, array $families, array &$out): void
    {
        $kinds = ['bureau' => [1, 'Marriage Bureau', 'partner-bureau-yearly'], 'organiser' => [2, 'Event Organisers', 'partner-organiser-yearly'], 'recruiter' => [3, 'Job Recruiters', 'partner-recruiter-yearly']];
        // (status, subscription state, records) per state index
        $profile = [0 => ['active', 'live', 12], 1 => ['active', 'live', 3], 2 => ['active', 'live', 2], 3 => ['active', 'expired', 2], 4 => ['pending', 'none', 0], 5 => ['active', 'none', 1]];
        foreach ($kinds as $kind => [$famNo, $label, $planCode]) {
            $plan = Plan::where('code', $planCode)->first();
            foreach (self::STATES as $si => [$S, $code, $cities]) {
                [$status, $subState, $count] = $profile[$si]; $f = $families[$S][$famNo]; $uid = $users[$S][$famNo];
                $pid = $this->ins('partners', ['kind' => $kind, 'user_id' => $uid, 'family_id' => $f->id, 'name' => $this->n(self::SURNAMES, $si + $famNo * 3) . " $label", 'contact_person' => $f->head_name, 'phone' => $f->mobile, 'email' => $f->email, 'country' => 'India', 'pradesh' => $S, 'district' => $f->district, 'city' => $f->city, 'zone' => $f->zone,
                    'description' => "Demo $label in $S", 'registration_no' => "DEMO/$code/" . strtoupper(substr($kind, 0, 3)) . '/01', 'status' => $status, 'verified' => $status === 'active', 'views' => 40 + $si * 5]);
                $end = null;
                if ($subState !== 'none' && $plan) {
                    $end = $subState === 'live' ? now()->addMonths(9)->toDateString() : now()->subDays(20)->toDateString();
                    $sid = $this->ins('subscriptions', ['user_id' => $uid, 'family_id' => $f->id, 'subscriber_type' => $kind, 'subscriber_id' => $pid, 'plan_id' => $plan->id, 'status' => $subState === 'live' ? 'active' : 'expired',
                        'starts_on' => ($subState === 'live' ? now()->subMonths(3) : now()->subMonths(13))->toDateString(), 'ends_on' => $end, 'auto_renew' => false]);
                    $this->ins('payments', ['number' => 'DEMO-' . strtoupper($kind[0]) . "-$code-" . $sid, 'purpose' => 'subscription', 'purpose_ref' => (string) $sid, 'user_id' => $uid, 'family_id' => $f->id, 'country' => 'India', 'city' => $f->city, 'payer_name' => $f->head_name, 'currency' => 'INR',
                        'amount' => $plan->price, 'tax_amount' => round($plan->price - $plan->price / 1.18, 2), 'discount' => 0, 'gateway' => 'manual', 'status' => 'paid', 'paid_at' => now()->subMonths($subState === 'live' ? 3 : 13), 'meta' => json_encode(['title' => $plan->name, 'plan' => $plan->code, 'demo' => true])]);
                }
                $until = $end ?: now()->subDays(40)->toDateString();
                for ($r = 0; $r < $count; $r++) $this->partnerRecord($kind, $pid, $f, $uid, $S, $r, $until, $si === 0);
                if ($si === 0) $this->showcase($kind, $pid, $users, $families);
                $out[] = [$label . ($si === 0 ? ' (showcase)' : ''), $S . " - $status/$subState", 'login mobile ' . $f->mobile];
            }
        }
    }

    private function partnerRecord(string $kind, int $pid, $f, int $uid, string $S, int $r, string $until, bool $showcase): void
    {
        $city = $f->city; $past = $until < today()->toDateString();
        if ($kind === 'bureau') {
            $g = $r % 2 ? 'Female' : 'Male';
            $this->ins('matrimony_profiles', ['parinay_id' => (string) random_int(1000000000, 9999999999), 'partner_id' => $pid, 'name' => ($g === 'Male' ? $this->n(self::MALE, $r + 2) : $this->n(self::FEMALE, $r + 2)) . ' ' . $this->n(self::SURNAMES, $r + 9), 'gender' => $g, 'dob' => (1990 + ($r % 7)) . '-0' . (($r % 9) + 1) . '-1' . ($r % 9),
                'qualification' => $this->n(['B.Com', 'MBA', 'B.Tech', 'CA', 'M.Com', 'MBBS'], $r), 'occupation' => $this->n(['Chartered Accountant', 'Software Engineer', 'Business', 'Doctor', 'Teacher', 'Banker'], $r), 'height_cm' => 152 + $r * 3, 'mobile' => '9' . random_int(100000000, 999999999),
                'state' => $S, 'pradesh' => $S, 'city' => $this->n([$city, $city], $r), 'country' => 'India', 'status' => 'paid', 'paid_until' => $until, 'visible' => ! $past, 'marital_status' => 'Never Married', 'gotra' => $this->n(self::GOTRA, $r), 'annual_income' => 500000 + $r * 100000,
                'diet' => 'Vegetarian', 'about' => 'Profile listed by marriage bureau (demo).', 'registered_at' => now()->subDays($r * 3)]);
        } elseif ($kind === 'recruiter') {
            $this->ins('jobs_board', ['user_id' => $uid, 'family_id' => $f->id, 'partner_id' => $pid, 'pradesh' => $S, 'state' => $S, 'district' => $f->district, 'job_type' => $this->n(['full_time', 'part_time', 'internship'], $r), 'title' => $this->n(['Accounts Executive', 'Sales Manager', 'PHP Developer', 'Store Supervisor', 'CA Intern', 'HR Executive', 'Digital Marketer', 'Warehouse Incharge', 'Customer Support', 'Branch Manager', 'Tally Operator', 'Business Analyst'], $r),
                'company' => $this->n(self::SURNAMES, 3) . ' Staffing', 'city' => $city, 'salary' => (18 + $r * 2) . 'k-' . (25 + $r * 2) . 'k/month', 'experience' => $r % 4 . '-' . ($r % 4 + 3) . ' yrs', 'vacancies' => 1 + $r % 4, 'description' => 'Demo job posted by a recruiter.', 'contact_name' => $f->head_name, 'contact_phone' => $f->mobile,
                'status' => 'live', 'expires_at' => $until . ' 23:59:59', 'country' => 'India', 'zone' => $f->zone, 'category' => 'General', 'views' => 12 + $r * 9]);
        } else {
            $eid = $this->ins('events', ['partner_id' => $pid, 'title' => $this->n(['Wedding Expo', 'Garba Night', 'Business Networking Meet', 'Sangeet Evening', 'Navratri Mahotsav', 'Youth Fest', 'Holi Milan', 'Charity Gala', 'Bhajan Sandhya', 'Career Fair', 'Family Picnic', 'Diwali Mela'], $r) . " - $city", 'description' => 'Demo event by an organiser.',
                'venue' => $city . ' Convention Hall', 'event_date' => ($past ? now()->subDays(10 + $r) : now()->addDays(5 + $r * 9))->toDateString(), 'event_time' => '18:30:00', 'kind' => 'event', 'audience' => 'country', 'is_public' => true, 'created_by' => $uid, 'rsvp_enabled' => $r % 3 !== 0, 'ticketing' => $r % 3 === 0,
                'capacity' => 150 + $r * 10, 'max_guests' => 2, 'listing_until' => $until, 'state' => $S, 'city' => $city, 'country' => 'India']);
            if ($r % 3 === 0) { $tt = $this->ins('ticket_types', ['event_id' => $eid, 'name' => 'General', 'price' => 299 + $r * 50, 'currency' => 'INR', 'quantity' => 200, 'max_per_order' => 10, 'is_active' => true]); $this->tickets($eid, $tt, $r); }
        }
    }

    private array $buyers = [];

    private function tickets(int $eid, int $tt, int $r): void
    {
        foreach (DB::table('demo_records')->where('tbl', 'users')->limit(40)->pluck('rec_id') as $k => $uid) {
            if ($k >= 3 + $r % 3) break;
            $u = DB::table('users')->find($uid); if ($u->role !== 'member') continue;
            $oid = $this->ins('ticket_orders', ['number' => 'DEMO-T' . $eid . '-' . $uid, 'event_id' => $eid, 'user_id' => $uid, 'family_id' => $u->family_id, 'ticket_type_id' => $tt, 'qty' => 2, 'amount' => 598, 'currency' => 'INR', 'status' => 'paid', 'attendees' => json_encode([$u->name, 'Guest'])]);
            foreach ([$u->name, 'Guest'] as $h) $this->ins('tickets', ['order_id' => $oid, 'event_id' => $eid, 'holder_name' => $h, 'code' => strtoupper(substr(md5("$oid-$h"), 0, 12)), 'void' => false]);
        }
    }

    /** leads / applicants / RSVPs for the showcase partner so every tab of the dashboard has content */
    private function showcase(string $kind, int $pid, array $users, array $families): void
    {
        $uids = collect($users)->flatten()->values(); $i = 0;
        foreach (self::STATES as [$S]) foreach ([2, 4] as $no) {
            $uid = $users[$S][$no]; $i++;
            $this->ins('partner_enquiries', ['partner_id' => $pid, 'from_user_id' => $uid, 'subject_type' => ['bureau' => 'profile', 'organiser' => 'event', 'recruiter' => 'job'][$kind], 'name' => DB::table('users')->where('id', $uid)->value('name'), 'phone' => $families[$S][$no]->mobile,
                'message' => $this->n(['Please share details', 'Interested, call me', 'Need pricing / info', 'Is this still open?'], $i), 'status' => $this->n(['new', 'contacted', 'new', 'closed'], $i)]);
        }
        if ($kind === 'recruiter') {
            foreach (DB::table('jobs_board')->where('partner_id', $pid)->limit(6)->pluck('id') as $k => $jid) foreach ([0, 1] as $n) {
                $uid = $uids[($k * 2 + $n + 5) % $uids->count()];
                $this->ins('job_applications', ['job_id' => $jid, 'user_id' => $uid, 'note' => 'Demo applicant', 'status' => $this->n(['applied', 'shortlisted', 'hired', 'applied', 'rejected'], $k + $n)]);
            }
        }
        if ($kind === 'organiser') {
            foreach (DB::table('events')->where('partner_id', $pid)->where('rsvp_enabled', 1)->limit(5)->pluck('id') as $k => $eid) foreach ([0, 1, 2] as $n) {
                $uid = $uids[($k * 3 + $n + 7) % $uids->count()];
                $this->ins('event_rsvps', ['event_id' => $eid, 'user_id' => $uid, 'family_id' => DB::table('users')->where('id', $uid)->value('family_id'), 'guests' => $n, 'status' => 'going', 'pass_code' => strtoupper(substr(md5("$eid-$uid-" . $this->seq++), 0, 10))]);
            }
        }
    }
}
