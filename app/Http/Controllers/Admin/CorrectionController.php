<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Family, Member, UserNotification};
use App\Services\CorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Formal data-correction requests: member asks -> admin of that area approves/rejects -> value changed with a full audit trail. */
class CorrectionController extends Controller
{
    public function index(Request $r)
    {
        $ids = $r->user()->areaFamilyIds();
        $rows = DB::table('data_corrections as c')->join('families as f', 'f.id', '=', 'c.family_id')->join('users as u', 'u.id', '=', 'c.user_id')
            ->when($ids, fn ($q) => $q->whereIn('c.family_id', (clone $ids)->getQuery()))
            ->when($r->status ?: 'pending', fn ($q, $v) => $q->where('c.status', $v))
            ->orderByDesc('c.id')->select('c.*', 'f.head_name', 'f.city', 'u.name as requester')->paginate(30)->withQueryString();
        return view('admin.engage.corrections', ['rows' => $rows, 'status' => $r->status ?: 'pending']);
    }

    public function decide(Request $r, int $id, CorrectionService $svc)
    {
        $d = $r->validate(['decision' => 'required|in:approved,rejected', 'remark' => 'nullable|string|max:250']);
        $c = DB::table('data_corrections')->where('id', $id)->first() ?? abort(404);
        $ids = $r->user()->areaFamilyIds();
        abort_unless(! $ids || (clone $ids)->where('id', $c->family_id)->exists(), 404);
        abort_unless($c->status === 'pending', 422, 'Already decided.');
        $svc->decide($c, $d['decision'], $d['remark'] ?? null, $r->user()->id);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'DATA_CORRECTION', 'detail' => substr("#$id {$c->target}.{$c->field} {$d['decision']}", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', 'Decision saved, the member has been notified.');
    }
}
