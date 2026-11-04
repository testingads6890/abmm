<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private TeamService $team) {}

    private static function row(User $u): array
    {
        return AuthController::payload($u) + ['is_active' => $u->is_active, 'last_login_at' => $u->last_login_at, 'created_by' => $u->created_by];
    }

    public function index(Request $r)
    {
        $page = User::managedBy($r->user())
            ->when($r->role, fn ($q, $v) => $q->where('role', $v))
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('mobile', 'like', "%$v%")))
            ->orderBy('id')->cursorPaginate(30);
        return ['data' => collect($page->items())->map(fn ($u) => self::row($u)), 'next_cursor' => $page->nextCursor()?->encode()];
    }

    public function catalogue(Request $r)
    {
        return ['modules' => config('permissions.modules'), 'roles' => config('permissions.roles'), 'defaults' => config('permissions.defaults'),
            'grantable' => $r->user()->grantablePermissions(), 'creatable_roles' => $r->user()->creatableRoles()];
    }

    public function store(Request $r)
    {
        $u = $this->team->create($r->user(), $r->validate($this->team->rules($r->user())));
        return response()->json(['user' => self::row($u)], 201);
    }

    public function update(Request $r, User $user)
    {
        abort_unless($r->user()->canManage($user), 403);
        $d = $r->validate($this->team->rules($r->user(), $user));
        return ['user' => self::row($this->team->update($r->user(), $user, $d))];
    }

    public function toggle(Request $r, User $user) { return ['user' => self::row($this->team->toggle($r->user(), $user))]; }

    public function resetPassword(Request $r, User $user) { return ['temporary_password' => $this->team->resetPassword($r->user(), $user)]; }
}
