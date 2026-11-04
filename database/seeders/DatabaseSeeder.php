<?php

namespace Database\Seeders;

use App\Models\{Business, BusinessReview, CommitteeMember, Event, Family, Lookup, MatrimonyProfile, Setting, User};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Change this password immediately after first login.
        User::updateOrCreate(['email' => 'admin@abmm.test'], [
            'name' => 'Super Admin', 'mobile' => '9999999999', 'role' => 'super_admin', 'password' => 'ChangeMe@12345', 'is_active' => true,
        ]);

        // Demo hierarchy (change/delete before go-live): State Sub-Admin -> District Operator
        $sa = User::updateOrCreate(['email' => 'state@abmm.test'], [
            'name' => 'Demo State Sub-Admin', 'mobile' => '9888888801', 'role' => 'state_admin', 'password' => 'ChangeMe@12345', 'is_active' => true,
            'pradesh' => 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA', 'created_by' => User::where('email', 'admin@abmm.test')->value('id'),
            'permissions' => config('permissions.defaults.state_admin'),
        ]);
        User::updateOrCreate(['email' => 'operator@abmm.test'], [
            'name' => 'Demo District Operator', 'mobile' => '9888888802', 'role' => 'district_operator', 'password' => 'ChangeMe@12345', 'is_active' => true,
            'pradesh' => 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA', 'district' => 'JAIPUR', 'tehsil' => 'JHOTWARA', 'created_by' => $sa->id,
            'permissions' => config('permissions.defaults.district_operator'),
        ]);

        foreach ([
            'org_name' => 'Akhil Bharatvarshiya Maheshwari Mahasabha', 'sabhapati' => 'Sabhapati Name', 'operational_year' => '2023-2026',
            'contact_mobile' => '0712 273 4205', 'contact_email' => 'info@example.org',
            'contact_address' => 'Maheshwari Mahasabha Bhawan, Ganeshpeth, Nagpur, Maharashtra 440018', 'copyright' => 'Akhil Bharatvarshiya Maheshwari Mahasabha',
            'sms_balance' => '318122', 'sms_daily_use' => '2500',
            'page_privacy-policy' => 'Privacy policy yahan likhein.', 'page_terms-and-conditions' => 'Terms yahan likhein.', 'page_refund-policy' => 'Donations non-refundable hain.',
        ] as $k => $v) Setting::put($k, $v);

        $this->call(LookupSeeder::class);
        $this->call(IndiaStatesSeeder::class);      // 37 state / UT sub-admins (common initial password from .env)   // exact dropdown values from the live site (surnames, qualification, tehsil, city ...)
        $this->call(AnchalSeeder::class);           // ABMM Anchal -> Pradesh links (also run by migration 2026_11_01_000036)
        $lists = [
            'surname' => ['MAHESHWARI', 'BAHETI', 'LADHA', 'KANKANI', 'PARWAL', 'BHANDARI', 'HOLANI', 'MUNDHRA', 'KABRA', 'RATHI', 'SABOO', 'LAKHOTIA', 'PHALOD', 'GANDHI'],
            'pradesh' => ['PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA'], 'district' => ['JAIPUR'], 'tehsil' => ['JHOTWARA', 'SANGANER', 'BAGRU', 'CHOMU'],
            'blood_group' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], 'occupation' => ['Business', 'Salaried', 'Student', 'Home Maker', 'Retired', 'Professional'],
            'income' => ['Below 1 lakh', '1 Lakh+ to 3 Lakh', '3 Lakh+ To 6 Lakh', '6 Lakh+ To 10 Lakh', 'Above 10 Lakh'],
        ];

        \App\Models\Office::firstOrCreate(['title' => 'MAHASABHA OFFICE'], ['address' => 'Maheshwari Mahasabha Bhawan, Ganeshpeth, Nagpur 440018', 'phones' => '0712 273 4205']);
        \App\Models\Office::firstOrCreate(['title' => 'SABHAPATI OFFICE'], ['person' => 'Sabhapati Name', 'address' => 'Address yahan', 'sort' => 1]);

        if (Family::exists()) return; // demo data only once
        $names = ['Nilesh', 'Pradeep', 'Urmila', 'Bhagwan Sahai', 'Kishor', 'Sunil', 'Anil Kumar', 'Natwar', 'Rajesh', 'Meena', 'Suresh', 'Kavita'];
        foreach ($names as $i => $n) {
            $s = $lists['surname'][$i % count($lists['surname'])];
            $d = 'JAIPUR';
            Family::create([
                'family_code' => Family::nextCode(), 'surname' => $s, 'head_name' => "$n $s", 'father_husband_name' => 'Late ' . $lists['surname'][($i + 3) % 14],
                'mobile' => '98' . random_int(10000000, 99999999), 'gender' => in_array($n, ['Urmila', 'Meena', 'Kavita']) ? 'Female' : 'Male',
                'member_count' => random_int(2, 6), 'address' => random_int(1, 400) . ', Demo Colony', 'city' => ucfirst(strtolower($d)), 'state' => 'RAJASTHAN', 'tehsil' => $lists['tehsil'][$i % 4],
                'district' => $d, 'pradesh' => $lists['pradesh'][0], 'pincode' => '3020' . random_int(10, 99), 'blood_group' => $lists['blood_group'][$i % 8],
                'occupation' => $lists['occupation'][$i % 6], 'income_range' => $lists['income'][$i % 5], 'created_at' => now()->subDays($i * 9),
            ])->syncHead();
        }
        foreach (['Sandeep Kabra' => 'Sabhapati', 'Amit Mundhra' => 'Mahamantri', 'Arun Laddha' => 'Arthmantri', 'Ashish Phalod' => 'Up Sabhapati'] as $n => $des) {
            CommitteeMember::create(['name' => $n, 'designation' => $des, 'level' => 'country', 'mobile' => '98' . random_int(10000000, 99999999), 'term_start' => '2023-06-18', 'term_end' => '2026-06-17']);
        }
        $b = Business::create(['name' => 'New Shriphal Medical', 'type' => 'Medical Store', 'address' => 'Laxmi Narayan Complex, Parbhani', 'mobile' => '9326628882', 'status' => 'active']);
        BusinessReview::create(['business_id' => $b->id, 'reviewer_name' => 'Demo User', 'rating' => 5, 'comment' => 'Sahi daam, achchi seva.', 'status' => 'approved']);
        Business::create(['name' => 'PRN Techtex Pvt Ltd', 'type' => 'Textile', 'address' => 'Wardha Rd, Nagpur', 'mobile' => '9890153766', 'status' => 'active']);
        Event::create(['title' => 'Maheshwari Navami Mahotsav', 'venue' => 'Mahasabha Bhawan, Nagpur', 'event_date' => now()->addDays(40), 'kind' => 'event']);
        MatrimonyProfile::create(['parinay_id' => '9524429705', 'name' => 'Demo Profile', 'gender' => 'Female', 'dob' => now()->subYears(26), 'qualification' => 'B.Com', 'mobile' => '9000000000', 'state' => 'RAJASTHAN', 'district' => 'JAIPUR', 'registered_at' => now()]);
    }
}
