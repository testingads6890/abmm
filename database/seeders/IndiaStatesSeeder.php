<?php

namespace Database\Seeders;

use App\Models\{Lookup, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One State Sub-Admin per Indian state / union territory (28 states + 9 UTs = 37), all created by the Super Admin.
 *
 *  - Password for all of them comes from .env  SEED_STATE_ADMIN_PASSWORD  (falls back to ChangeMe@12345).
 *    Every account is flagged must_change_password, so each person has to set their own on first login.
 *  - Login e-mail:  <state-slug>@<SEED_ADMIN_DOMAIN>  (default abmm.test), e.g. rajasthan@abmm.test. Mobile numbers are
 *    placeholders (9700000001..) — edit them from Team.
 *  - ABMM "Pradesh" is the area a sub-admin is limited to. Real ABMM pradesh sabhas do not map 1:1 to states, so each
 *    state gets a PLACEHOLDER pradesh named "<STATE> PRADESH MAHESHWARI SABHA" (Rajasthan reuses the live one).
 *    Rename / merge them in Super Admin > Master Data > ABMM Pradesh; the rename cascades to users and families.
 *  Safe to re-run (updateOrCreate by e-mail).
 */
class IndiaStatesSeeder extends Seeder
{
    public const STATES = [
        'ANDHRA PRADESH', 'ARUNACHAL PRADESH', 'ASSAM', 'BIHAR', 'CHATTISGARH', 'GOA', 'GUJARAT', 'HARYANA', 'HIMACHAL PRADESH', 'JHARKHAND',
        'KARNATAKA', 'KERALA', 'MADHYA PRADESH', 'MAHARASHTRA', 'MANIPUR', 'MEGHALAYA', 'MIZORAM', 'NAGALAND', 'ODISHA', 'PUNJAB',
        'RAJASTHAN', 'SIKKIM', 'TAMIL NADU', 'TELANGANA', 'TRIPURA', 'UTTAR PRADESH', 'UTTARAKHAND', 'WEST BENGAL',
        // union territories — spelled exactly as in the live "Govt. State" list (which still has D&NH and Daman & Diu separate, and no Ladakh)
        'ANDAMAN AND NICOBAR ISLANDS', 'CHANDIGARH', 'DADRA AND NAGAR HAVELI', 'DAMAN AND DIU', 'DELHI', 'JAMMU AND KASHMIR', 'LAKSHADWEEP', 'PONDICHERRY',
        'LADAKH',   // not in the live list; added because it is a UT today
    ];

    private const RAJASTHAN_PRADESH = 'PURVOTTAR RAJASTHAN PRADESH MAHESHWARI SABHA';

    public function run(): void
    {
        $password = env('SEED_STATE_ADMIN_PASSWORD', 'ChangeMe@12345');
        $domain = env('SEED_ADMIN_DOMAIN', 'abmm.test');
        $super = User::where('role', 'super_admin')->orderBy('id')->value('id');
        $perms = config('permissions.defaults.state_admin');

        foreach (self::STATES as $i => $state) {
            $pradesh = $state === 'RAJASTHAN' ? self::RAJASTHAN_PRADESH : "$state PRADESH MAHESHWARI SABHA";
            // reuse the Pradesh whatever its parent (it may already sit under an ABMM Anchal)
            $p = Lookup::where(['type' => 'pradesh', 'name' => $pradesh])->orderBy('id')->first() ?? Lookup::create(['type' => 'pradesh', 'name' => $pradesh, 'parent_id' => null, 'sort' => $i, 'is_active' => true]);
            // the live 'Govt. State' list is seeded flat (no parent) by LookupSeeder; attach it to its pradesh instead of duplicating
            if (Lookup::where(['type' => 'state', 'name' => $state, 'parent_id' => $p->id])->exists()) {
                // already attached (LookupSeeder adds RAJASTHAN under its pradesh): drop the flat duplicate instead of re-parenting it (unique index)
                Lookup::where(['type' => 'state', 'name' => $state])->whereNull('parent_id')->delete();
            } else {
                $st = Lookup::where(['type' => 'state', 'name' => $state])->whereNull('parent_id')->orderBy('id')->first();
                $st ? $st->update(['parent_id' => $p->id]) : Lookup::create(['type' => 'state', 'name' => $state, 'parent_id' => $p->id, 'sort' => $i, 'is_active' => true]);
            }

            User::updateOrCreate(['email' => Str::slug($state) . '@' . $domain], [
                'name' => Str::title($state) . ' State Sub-Admin', 'mobile' => '97' . str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT), 'role' => 'state_admin',
                'password' => $password, 'must_change_password' => true, 'is_active' => true, 'pradesh' => $pradesh, 'created_by' => $super, 'permissions' => $perms,
            ]);
        }
    }
}
