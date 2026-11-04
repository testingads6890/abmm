<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'permissions' => 'array', 'last_login_at' => 'datetime'];
    }

    public function creator() { return $this->belongsTo(self::class, 'created_by'); }
    public function createdUsers() { return $this->hasMany(self::class, 'created_by'); }
    public function tokens() { return $this->hasMany(ApiToken::class); }

    // ---------- roles ----------
    public function isSuper(): bool { return $this->role === 'super_admin'; }
    public function isStateAdmin(): bool { return $this->role === 'state_admin'; }
    public function isOperator(): bool { return $this->role === 'district_operator'; }
    public function isCountryAdmin(): bool { return $this->role === 'country_admin'; }
    public function isCityAdmin(): bool { return $this->role === 'city_admin'; }
    public function isZoneCoordinator(): bool { return $this->role === 'zone_coordinator'; }
    /** Bound to country / city / zone instead of pradesh. */
    public function isGlobalRole(): bool { return in_array($this->role, config('permissions.global_roles', []), true); }
    /** Super admin, country admin or state sub-admin (everything that is "admin-level"). */
    public function isAdmin(): bool { return in_array($this->role, ['super_admin', 'state_admin', 'country_admin'], true); }
    public function roleLabel(): string { return config("permissions.roles.{$this->role}.label", $this->role); }
    public function rank(): int { return (int) config("permissions.roles.{$this->role}.rank", 0); }

    // ---------- permissions ----------
    public function hasPermission(string $key): bool
    {
        if (! $this->is_active) return false;
        if ($this->isSuper()) return true;
        return in_array($key, $this->permissions ?? [], true);
    }

    /** All valid permission keys from config. */
    public static function allPermissionKeys(): array
    {
        $k = [];
        foreach (config('permissions.modules') as $m => $def) foreach ($def['actions'] as $a) $k[] = "$m.$a";
        return $k;
    }

    /** What THIS user may hand out: only what he/she holds (super = everything except nothing withheld). */
    public function grantablePermissions(): array
    {
        return $this->isSuper() ? self::allPermissionKeys() : array_values(array_intersect(self::allPermissionKeys(), $this->permissions ?? []));
    }

    // ---------- hierarchy ----------
    public function creatableRoles(): array { return config("permissions.can_create.{$this->role}", []); }

    /** Everything below this role in the chain (State Sub-Admin => District => City => Zone): can be viewed / edited / disabled, even if created one level down. */
    public function manageableRoles(): array
    {
        $out = []; $todo = $this->creatableRoles();
        while ($todo) { $r = array_shift($todo); if (in_array($r, $out, true)) continue; $out[] = $r; foreach (config("permissions.can_create.$r", []) as $c) $todo[] = $c; }
        return $out;
    }

    /** Area this user is bound to (super admin: unrestricted). */
    public function areaFilter(): array
    {
        if ($this->isGlobalRole()) {
            // Country Admin: country. City Admin: country+city. Zone Coordinator: country+city+zone.
            return array_filter([
                'country' => $this->country,
                'city' => $this->isCountryAdmin() ? null : $this->city,
                'zone' => $this->isZoneCoordinator() ? $this->zone : null,
            ]);
        }
        return array_filter(['pradesh' => $this->pradesh, 'district' => $this->district, 'tehsil' => $this->tehsil]);
    }

    /** Subquery of family ids inside this user's area (super: null = unrestricted). Used to scope payments, subscriptions, form submissions. */
    public function areaFamilyIds()
    {
        if ($this->isSuper()) return null;
        $f = $this->hasArea() ? $this->areaFilter() : [];
        if (! $f) return Family::withoutGlobalScopes()->whereRaw('1 = 0')->select('id');
        return Family::withoutGlobalScopes()->where($f)->select('id');
    }

    /** True when this (non-super) user has a usable area. Fail-closed scopes call this instead of checking pradesh directly. */
    public function hasArea(): bool
    {
        if ($this->isGlobalRole()) {
            return (bool) $this->country && (! in_array($this->role, ['city_admin', 'zone_coordinator'], true) || $this->city)
                && ($this->role !== 'zone_coordinator' || $this->zone);
        }
        return (bool) $this->pradesh;
    }

    /** Users this user may view/manage. Super: all but self. Others: roles they may create, inside their own area. */
    public function scopeManagedBy($q, self $actor)
    {
        $q->where('role', '!=', 'member');   // app accounts are never "team"
        if ($actor->isSuper()) return $q->where('id', '!=', $actor->id);
        $roles = $actor->manageableRoles();
        if (! $roles || ! $actor->hasArea()) return $q->whereRaw('1 = 0');
        $q->whereIn('role', $roles)->where('id', '!=', $actor->id);
        foreach ($actor->areaFilter() as $col => $val) $q->where($col, $val);
        return $q;
    }

    public function canManage(self $target): bool
    {
        if ($target->id === $this->id) return false;
        if ($this->isSuper()) return true;
        if (! $this->hasArea() || ! in_array($target->role, $this->manageableRoles(), true)) return false;
        foreach ($this->areaFilter() as $col => $val) if ((string) $target->{$col} !== (string) $val) return false;
        return true;
    }
}
