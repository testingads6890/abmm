<?php

namespace App\Services;

use App\Models\{ActivityLog, GeoNode, User};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Single place for Super Admin -> State Sub-Admin -> District Admin -> City Admin -> Zone Admin management (used by web panel AND mobile API). */
class TeamService
{
    public function rules(User $actor, ?User $target = null): array
    {
        $roles = $target ? [$target->role] : $actor->creatableRoles();
        return [
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($target?->id)],
            'mobile' => ['required', 'regex:/^\d{6,15}$/', Rule::unique('users', 'mobile')->ignore($target?->id)],
            'role' => ['required', Rule::in($roles)],
            'pradesh' => 'nullable|string|max:150',
            'district' => 'nullable|string|max:100',
            'tehsil' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:150',
            'zone' => 'nullable|string|max:100',
            'password' => [$target ? 'nullable' : 'required', 'string', 'min:10'],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(User::allPermissionKeys())],
        ];
    }

    private function area(User $actor, array $d, string $role): array
    {
        if (in_array($role, config('permissions.global_roles', []), true)) return $this->globalArea($actor, $d, $role);
        // A State Sub-Admin can only create inside his own pradesh.
        if (! $actor->isSuper()) { $d['pradesh'] = $actor->pradesh; }
        $err = [];
        if ($role === 'state_admin' && empty($d['pradesh'])) $err['pradesh'] = 'State/Pradesh is required for a State Sub-Admin.';
        if ($role === 'district_operator') {
            if (empty($d['pradesh'])) $err['pradesh'] = 'State is required.';
            if (empty($d['district'])) $err['district'] = 'District is required.';
        }
        if ($err) throw ValidationException::withMessages($err);
        return [
            'pradesh' => $d['pradesh'] ?? null,
            'district' => $role === 'state_admin' ? null : ($d['district'] ?? null),
            'tehsil' => $role === 'state_admin' ? null : ($d['tehsil'] ?? null),
        ];
    }

    /** Country Admin / City Admin / Zone Coordinator: area is country (+city, +zone). Chain: State > District > City Admin > Zone, so state + district are stored too.
     *  A non-super creator can only stay inside his own area. */
    private function globalArea(User $actor, array $d, string $role): array
    {
        if (! $actor->isSuper()) {
            foreach ($actor->areaFilter() as $col => $val) if (in_array($col, ['country', 'city', 'zone', 'pradesh', 'district'], true)) $d[$col] = $val;
            foreach (['pradesh', 'district'] as $c) if (empty($d[$c]) && $actor->{$c}) $d[$c] = $actor->{$c};
            if (empty($d['country'])) $d['country'] = $actor->country ?: 'INDIA';
        }
        $err = [];
        if (empty($d['country'])) $err['country'] = 'Country is required.';
        if (in_array($role, ['city_admin', 'zone_coordinator'], true) && empty($d['city'])) $err['city'] = 'City is required.';
        if ($role === 'zone_coordinator' && empty($d['zone'])) $err['zone'] = 'Zone is required.';
        if ($err) throw ValidationException::withMessages($err);
        $country = strtoupper(trim($d['country']));
        $pradesh = ($d['pradesh'] ?? null) ?: null; $district = ($d['district'] ?? null) ?: null;
        if (in_array($role, ['city_admin', 'zone_coordinator'], true)) [$pradesh, $district] = $this->checkPlace($actor, $country, $pradesh, $district, trim($d['city']), $role === 'zone_coordinator' ? trim($d['zone']) : null);
        elseif ($role === 'country_admin') { $pradesh = null; $district = null; }
        return [
            'pradesh' => $pradesh, 'district' => $district, 'tehsil' => null,
            'country' => $country,
            'city' => $role === 'country_admin' ? null : trim($d['city']),
            'zone' => $role === 'zone_coordinator' ? trim($d['zone']) : null,
        ];
    }

    /** City (and zone) must exist in the Locations master under the given state / district. Super Admin may still use places not yet in the master. */
    private function checkPlace(User $actor, string $country, ?string $pradesh, ?string $district, string $city, ?string $zone): array
    {
        $hit = GeoNode::where(['level' => 'city', 'name' => $city])->get()->first(function ($n) use ($country, $pradesh, $district) {
            $p = $n->path();
            return strcasecmp($p['country'] ?? '', $country) === 0 && (! $pradesh || strcasecmp($p['state'] ?? '', $pradesh) === 0) && (! $district || strcasecmp($p['district'] ?? '', $district) === 0);
        });
        if (! $hit) {
            if (! $actor->isSuper() && ! $actor->isCountryAdmin()) throw ValidationException::withMessages(['city' => "\"$city\" is not in the Locations master for your area. Add it in Locations first."]);
            return [$pradesh, $district];
        }
        if ($zone && ! GeoNode::where(['parent_id' => $hit->id, 'level' => 'zone', 'name' => $zone])->exists() && ! $actor->isSuper() && ! $actor->isCountryAdmin())
            throw ValidationException::withMessages(['zone' => "\"$zone\" is not in this city. Add the zone in Locations first."]);
        $p = $hit->path();
        return [$pradesh ?: ($p['state'] ?? null), $district ?: ($p['district'] ?? null)];
    }

    public function create(User $actor, array $d): User
    {
        abort_unless(in_array($d['role'], $actor->creatableRoles(), true), 403, 'You do not have permission to create this role.');
        $perm = array_values(array_intersect($d['permissions'] ?? config("permissions.defaults.{$d['role']}", []), $actor->grantablePermissions()));
        $u = User::create([
            'name' => $d['name'], 'email' => strtolower($d['email']), 'mobile' => $d['mobile'], 'role' => $d['role'],
            'password' => $d['password'], 'must_change_password' => true, 'is_active' => true, 'created_by' => $actor->id, 'permissions' => $perm,
        ] + $this->area($actor, $d, $d['role']));
        $this->log($actor, 'USER_CREATE', "#{$u->id} {$u->role}");
        return $u;
    }

    public function update(User $actor, User $t, array $d): User
    {
        abort_unless($actor->canManage($t), 403);
        $attrs = ['name' => $d['name'], 'email' => strtolower($d['email']), 'mobile' => $d['mobile']] + $this->area($actor, $d + ['pradesh' => $t->pradesh, 'country' => $t->country, 'city' => $t->city, 'zone' => $t->zone], $t->role);
        if (! empty($d['password'])) { $attrs['password'] = $d['password']; $t->tokens()->delete(); }
        if (array_key_exists('permissions', $d)) {
            $grant = $actor->grantablePermissions();
            // keep anything the target already has that the actor cannot manage (no silent stripping)
            $attrs['permissions'] = array_values(array_unique(array_merge(
                array_intersect($d['permissions'] ?? [], $grant), array_diff($t->permissions ?? [], $grant)
            )));
        }
        $t->update($attrs);
        $this->log($actor, 'USER_UPDATE', "#{$t->id}");
        return $t->refresh();
    }

    public function toggle(User $actor, User $t): User
    {
        abort_unless($actor->canManage($t), 403);
        $t->update(['is_active' => ! $t->is_active]);
        if (! $t->is_active) $t->tokens()->delete();       // log the mobile app out immediately
        $this->log($actor, $t->is_active ? 'USER_ENABLE' : 'USER_DISABLE', "#{$t->id}");
        return $t;
    }

    /** Returns the new temporary password (shown once to the actor). */
    public function resetPassword(User $actor, User $t): string
    {
        abort_unless($actor->canManage($t), 403);
        $pw = Str::password(12, symbols: false);
        $t->update(['password' => $pw, 'must_change_password' => true]);
        $t->tokens()->delete();
        $this->log($actor, 'USER_PWRESET', "#{$t->id}");
        return $pw;
    }

    private function log(User $actor, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'action' => $action, 'detail' => $detail, 'ip' => request()->ip()]);
    }
}
