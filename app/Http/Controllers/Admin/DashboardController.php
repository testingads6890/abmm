<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Business, CommitteeMember, ContactMessage, Donation, Family, MatrimonyProfile, Setting};
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $byState = Family::select('state', DB::raw('count(*) c'))->whereNotNull('state')->groupBy('state')->orderByDesc('c')->limit(8)->get();
        $monthly = Family::select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') m"), DB::raw('count(*) c'))
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())->groupBy('m')->orderBy('m')->get();
        // Families per ABMM Anchal (via the Pradesh -> Anchal link in Master Data); the Family area scope still applies.
        $perPradesh = Family::select('pradesh', DB::raw('count(*) c'))->whereNotNull('pradesh')->groupBy('pradesh')->pluck('c', 'pradesh');
        $byAnchal = [];
        foreach (\App\Models\Lookup::anchalPradesh() as $anchal => $list) {
            $byAnchal[$anchal] = 0;
            foreach ($perPradesh as $p => $c) if (in_array(mb_strtoupper(trim((string) $p)), array_map('mb_strtoupper', $list), true)) $byAnchal[$anchal] += $c;
        }
        return view('admin.dashboard', [
            'byAnchal' => $byAnchal,
            'families' => Family::count(),
            'members' => (int) Family::sum('member_count'),
            'committee' => CommitteeMember::where('is_active', true)->count(),
            'levels' => CommitteeMember::where('is_active', true)->select('level', DB::raw('count(*) c'))->groupBy('level')->pluck('c', 'level'),
            'matrimony' => MatrimonyProfile::count(),
            'businesses' => Business::count(),
            'donations' => Donation::where('status', 'success')->sum('amount'),
            'unread' => ContactMessage::where('is_read', false)->count(),
            'recent' => Family::latest()->limit(8)->get(),
            'byState' => $byState,
            'monthly' => $monthly,
            'org' => Setting::get('org_name'),
            'fcmReady' => app(\App\Services\FcmService::class)->configured(),
            'activity' => ActivityLog::with('user')->latest('created_at')->limit(8)->get(),
            'operationalYear' => Setting::get('operational_year', '2023-2026'),
            'sabhapati' => Setting::get('sabhapati', '-'),
        ]);
    }
}
