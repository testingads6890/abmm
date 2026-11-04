<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Minimal endpoints the Flutter/Android/iOS app calls. Guarded by a shared key in .env (APP_API_KEY)
 * plus the family's registered mobile. Replace with Sanctum when real app-login is built.
 */
class AppController extends Controller
{
    private function family(Request $r): Family
    {
        abort_unless(hash_equals((string) config('abmm.app_api_key'), (string) $r->header('X-App-Key')), 401);
        $d = $r->validate(['family_code' => 'required|string|max:12', 'mobile' => 'required|digits:10']);
        return Family::withoutGlobalScopes()->where('family_code', $d['family_code'])->where('mobile', $d['mobile'])->firstOrFail();
    }

    /** App calls this on login: returns the FCM topics to subscribe to. */
    public function topics(Request $r)
    {
        $f = $this->family($r);
        $slug = fn ($v) => \Illuminate\Support\Str::slug((string) $v);
        return ['topics' => array_values(array_filter([
            'all', $f->pradesh ? 'pradesh_' . $slug($f->pradesh) : null, $f->state ? 'state_' . $slug($f->state) : null,
            $f->district ? 'district_' . $slug($f->district) : null, $f->tehsil ? 'tehsil_' . $slug($f->tehsil) : null,
        ]))];
    }

    /** App calls this when a notification is opened → feeds Seen / Not Seen. */
    public function seen(Request $r, int $id)
    {
        $f = $this->family($r);
        DB::table('notification_recipients')->where('notification_id', $id)->where('family_id', $f->id)->whereNull('seen_at')->update(['seen_at' => now()]);
        DB::table('notifications_log')->where('id', $id)->update(['seen_count' => DB::table('notification_recipients')->where('notification_id', $id)->whereNotNull('seen_at')->count()]);
        return response()->noContent();
    }
}
