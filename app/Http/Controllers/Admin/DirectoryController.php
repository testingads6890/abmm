<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\Setting;
use App\Services\DirectorySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Samaj Directory control room. Stats are area-scoped by the Member global scope (State Sub-Admin: own state, District Operator: own district); Super Admin also gets the switches and a state-wise table. */
class DirectoryController extends Controller
{
    public function index()
    {
        $base = Member::query()->where('members.status', 'active')->where('members.approval', 'approved')->whereNull('members.archived_at');
        $stats = [
            'total' => (clone $base)->count(),
            'hidden' => (clone $base)->where('members.dir_hidden', true)->count(),
            'phone' => (clone $base)->where('members.dir_hidden', false)->where('members.dir_show_phone', true)->count(),
        ];
        $byState = auth()->user()->isSuper()
            ? DB::table('members')->join('families as f', 'f.id', '=', 'members.family_id')->where('members.status', 'active')->where('members.approval', 'approved')->whereNull('members.archived_at')
                ->selectRaw("coalesce(f.pradesh,'—') as pradesh, count(*) total, sum(members.dir_hidden) hidden, sum(members.dir_show_phone and not members.dir_hidden) phone")
                ->groupBy('f.pradesh')->orderBy('f.pradesh')->get()
            : collect();
        return view('admin.directory.index', [
            'stats' => $stats, 'byState' => $byState, 'on' => Setting::get('dir_on', '1') === '1', 'off' => DirectorySettings::offPradesh(),
            'pradeshList' => Lookup::where('type', 'pradesh')->orderBy('name')->pluck('name'),
        ]);
    }

    public function settings(Request $r)
    {
        $d = $r->validate(['off' => 'nullable|array', 'off.*' => 'string|max:200']);
        Setting::put('dir_on', $r->boolean('on') ? '1' : '0');
        Setting::put('dir_off_pradesh', json_encode(array_values($d['off'] ?? [])));
        return back()->with('ok', 'Directory settings saved.');
    }
}
