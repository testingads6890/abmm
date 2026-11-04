<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Business, BusinessAd, Family, MatrimonyProfile, Member, NotificationLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The original opens these in a pop-up window: View = read-only sheet, Print = same sheet with the print dialog. */
class PrintController extends Controller
{
    public function show(Request $r, string $kind, int $id)
    {
        $data = match ($kind) {
            'member' => ['title' => 'Member Details', 'm' => Member::with('family')->findOrFail($id)],
            'head' => ['title' => 'Member Details', 'f' => ($f = Family::with('activeMembers')->findOrFail($id)), 'm' => $f->head],
            'family' => ['title' => 'Family Details', 'f' => ($f = Family::with('activeMembers')->findOrFail($id)), 'tree' => $this->tree($f)],
            'parinay' => ['title' => 'Mangal Parinay Details', 'p' => MatrimonyProfile::with('family')->findOrFail($id)],
            'business' => ['title' => 'Business Details', 'b' => Business::with(['family', 'reviews'])->findOrFail($id)],
            'ad' => ['title' => 'Business Advertise', 'a' => BusinessAd::with('business')->findOrFail($id)],
            'message' => ['title' => 'Notification Details', 'n' => NotificationLog::with('sender')->findOrFail($id)],
            'message-status' => ['title' => 'Notification Seen Status', 'n' => ($n = NotificationLog::findOrFail($id)),
                'rows' => Family::select('families.family_code', 'families.surname', 'families.head_name', 'families.mobile', 'nr.seen_at')
                    ->join('notification_recipients as nr', 'nr.family_id', '=', 'families.id')->where('nr.notification_id', $n->id)->orderByRaw('nr.seen_at is null')->limit(5000)->get()],
            default => abort(404),
        };
        return view('admin.print.doc', $data + ['kind' => $kind, 'autoprint' => $r->boolean('print')]);
    }

    /** Groups members into generations for the Family Tree drawing. */
    private function tree(Family $f): array
    {
        $parents = $mid = $kids = $others = [];
        foreach ($f->activeMembers as $m) {
            $rel = mb_strtolower($m->relation);
            if ($m->is_head) continue;
            if (preg_match('/father|mother|papa|mummy|पिता|माता|mother-in-law|father-in-law/u', $rel)) $parents[] = $m;
            elseif (preg_match('/wife|husband|spouse|पत्नी|पति/u', $rel)) $mid[] = $m;
            elseif (preg_match('/son|daughter|child|बेटा|बेटी|पुत्र|पुत्री|grand|pot[ai]/u', $rel)) $kids[] = $m;
            else $others[] = $m;
        }
        return compact('parents', 'mid', 'kids', 'others');
    }
}
