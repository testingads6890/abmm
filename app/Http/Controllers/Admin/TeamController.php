<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Lookup, User};
use App\Services\TeamService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private TeamService $team) {}

    public function index(Request $r)
    {
        $me = $r->user();
        $rows = User::managedBy($me)->with('creator:id,name')
            ->when($r->role, fn ($q, $v) => $q->where('role', $v))
            ->when($r->pradesh, fn ($q, $v) => $q->where('pradesh', $v))
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('mobile', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->orderByRaw("CASE role WHEN 'country_admin' THEN 0 WHEN 'state_admin' THEN 1 WHEN 'district_operator' THEN 2 WHEN 'city_admin' THEN 3 WHEN 'zone_coordinator' THEN 4 ELSE 5 END")->orderBy('name')->paginate(25)->withQueryString();
        return view('admin.team.index', ['rows' => $rows, 'me' => $me, 'pradeshList' => Lookup::ofType('pradesh')->active()->orderBy('name')->pluck('name')]);
    }

    private function formData(Request $r, ?User $target = null): array
    {
        return [
            'me' => $r->user(), 'u' => $target, 'modules' => config('permissions.modules'), 'roles' => config('permissions.roles'),
            'pradeshList' => Lookup::ofType('pradesh')->active()->orderBy('name')->pluck('name'),
            'districtList' => Lookup::ofType('district')->active()->orderBy('name')->pluck('name'),
            'tehsilList' => Lookup::ofType('tehsil')->active()->orderBy('name')->pluck('name'),
            'grantable' => $r->user()->grantablePermissions(), 'defaults' => config('permissions.defaults'),
        ];
    }

    public function create(Request $r)
    {
        abort_if(! $r->user()->creatableRoles(), 403);
        return view('admin.team.form', $this->formData($r));
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->team->rules($r->user()));
        $u = $this->team->create($r->user(), $d);
        return redirect()->route('admin.team.index')->with('ok', $u->roleLabel() . ' "' . $u->name . '" created.');
    }

    public function edit(Request $r, User $user)
    {
        abort_unless($r->user()->canManage($user), 403);
        return view('admin.team.form', $this->formData($r, $user));
    }

    public function update(Request $r, User $user)
    {
        abort_unless($r->user()->canManage($user), 403);
        $d = $r->validate($this->team->rules($r->user(), $user));
        $d['permissions'] = $d['permissions'] ?? [];      // all boxes unticked => empty array
        $this->team->update($r->user(), $user, $d);
        return redirect()->route('admin.team.index')->with('ok', 'Updated.');
    }

    public function toggle(Request $r, User $user)
    {
        $this->team->toggle($r->user(), $user);
        return back()->with('ok', 'Status changed.');
    }

    public function resetPassword(Request $r, User $user)
    {
        $pw = $this->team->resetPassword($r->user(), $user);
        return back()->with('ok', "New temporary password: $pw  (shown only now, tell the user)");
    }
}
