<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, UserNotification};
use App\Support\{Exporter, ManageResources};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};

/** Generic list / form / row-action screens driven by App\Support\ManageResources (Milestone C modules). */
class ManageController extends Controller
{
    private function res(string $key): array
    {
        return ManageResources::all()[$key] ?? abort(404);
    }

    private function allow(Request $r, array $res, string $action): void
    {
        abort_unless($r->user()->can($res['perm'] . '.' . $action), 403);
    }

    private function base(array $res)
    {
        $q = isset($res['query']) ? $res['query']() : DB::table($res['table']);
        $prefix = preg_match('/^(\w+)\./', (string) ($res['order'] ?? ''), $m) ? $m[1] . '.' : '';
        return self::scopeArea($q, $res['table'], $prefix, $res);
    }

    /**
     * Sub-admins only see rows of their own area (state sub-admin: own state; country / city admin: own country / city).
     * A resource can say how its rows map to an area: 'area_user' => column holding a user id, 'area_via' => [fk column, parent table].
     * Otherwise: family_id follows the family's area, else the table's own country / pradesh(state) / district / city / zone columns.
     * Master data without any of these stays visible. Fail-closed when the admin has no area.
     */
    public static function scopeArea($q, string $table, string $prefix = '', array $res = [])
    {
        $u = auth()->user();
        if (! $u || $u->isSuper()) return $q;
        if (! empty($res['area_user'])) return $q->whereIn($prefix . $res['area_user'], DB::table('users')->whereIn('family_id', $u->areaFamilyIds())->select('id'));
        if (! empty($res['area_via'])) { [$col, $parent] = $res['area_via']; return $q->whereIn($prefix . $col, self::scopeArea(DB::table($parent), $parent)->select('id')); }
        $cols = Schema::getColumnListing($table);
        if (in_array('family_id', $cols, true)) return $q->whereIn($prefix . 'family_id', $u->areaFamilyIds());
        $map = [];                                                    // user's area key => this table's column
        foreach (['country', 'pradesh', 'district', 'city', 'zone'] as $k) {
            if (in_array($k, $cols, true)) $map[$k] = $k;
            elseif ($k === 'pradesh' && in_array('state', $cols, true)) $map[$k] = 'state';
        }
        if (! $map) return $q;
        if (! $u->hasArea()) return $q->whereRaw('1 = 0');
        $f = array_intersect_key($u->areaFilter(), $map);
        if (! $f) return $q->whereRaw('1 = 0');
        foreach ($f as $k => $v) $q->where($prefix . $map[$k], $v);
        return $q;
    }

    private function spec(array $s): array
    {
        return ['label' => $s[0], 'type' => $s['type'] ?? ($s[1] ?? 'text'), 'options' => $s['options'] ?? null, 'nullable' => $s['nullable'] ?? false, 'virtual' => $s['virtual'] ?? false];
    }

    public function index(Request $r, string $key)
    {
        $res = $this->res($key); $this->allow($r, $res, 'view');
        $q = $this->base($res);
        foreach ($res['filters'] ?? [] as $col) if ($r->filled($col)) $q->where($this->qualify($res, $col), $r->input($col));
        $q->orderByRaw($res['order'] ?? 'id desc');
        if ($r->export && ! empty($res['export'])) {
            abort_unless(in_array($r->export, ['xlsx', 'csv', 'pdf'], true), 404);
            $cols = array_values($res['cols']);
            $rows = (function () use ($q, $cols) { foreach ($q->cursor() as $x) yield array_map(fn ($c) => (string) ($x->$c ?? ''), $cols); })();
            return Exporter::download($r->export, $key, $res['title'], array_keys($res['cols']), $rows);
        }
        $rows = $q->paginate(30)->withQueryString();
        $filterOptions = [];
        foreach ($res['filters'] ?? [] as $col) $filterOptions[$col] = DB::table($res['table'])->distinct()->orderBy($col)->pluck($col)->filter()->values()->all();
        return view('admin.manage.index', ['key' => $key, 'res' => $res, 'rows' => $rows, 'filterOptions' => $filterOptions]);
    }

    private function qualify(array $res, string $col): string
    {
        // list queries alias the main table: find the alias used in "order" (e.g. "a.id desc") else none
        if (preg_match('/^(\w+)\./', (string) ($res['order'] ?? ''), $m)) return $m[1] . '.' . $col;
        return $col;
    }

    public function form(Request $r, string $key, ?int $id = null)
    {
        $res = $this->res($key); $this->allow($r, $res, $id ? 'edit' : 'create');
        abort_unless($res['form'] ?? null, 404);
        $row = $id ? self::scopeArea(DB::table($res['table']), $res['table'], '', $res)->where('id', $id)->first() ?? abort(404) : null;
        $vals = $row ? (array) $row : [];
        if ($row && isset($res['fill'])) $vals = array_merge($vals, $res['fill']($row));
        $fields = [];
        foreach ($res['form'] as $name => $s) { $f = $this->spec($s); if (is_callable($f['options'])) $f['options'] = ($f['options'])(); $fields[$name] = $f; }
        return view('admin.manage.form', ['key' => $key, 'res' => $res, 'fields' => $fields, 'vals' => $vals, 'id' => $id]);
    }

    public function save(Request $r, string $key, ?int $id = null)
    {
        $res = $this->res($key); $this->allow($r, $res, $id ? 'edit' : 'create');
        abort_unless($res['form'] ?? null, 404);
        abort_if(! $id && empty($res['create']), 404);
        $rules = [];
        foreach ($res['form'] as $name => $s) {
            $f = $this->spec($s);
            $opts = is_callable($f['options']) ? ($f['options'])() : $f['options'];
            $base = match ($f['type']) {
                'number' => ['numeric'], 'date' => ['date'], 'datetime' => ['date'], 'checkbox' => ['boolean'], 'textarea' => ['string', 'max:5000'],
                'select' => $opts !== null ? [\Illuminate\Validation\Rule::in(array_map('strval', array_keys($opts)))] : ['string'], default => ['string', 'max:255'],
            };
            $rules[$name] = array_merge([$f['type'] === 'checkbox' || $f['nullable'] ? 'nullable' : 'required'], $base);
        }
        $d = $r->validate($rules);
        foreach ($res['form'] as $name => $s) if ($this->spec($s)['type'] === 'checkbox') $d[$name] = $r->boolean($name);
        foreach ($d as $k => $v) if ($v === '' ) $d[$k] = null;
        if (isset($res['before'])) $d = $res['before']($d, $id);
        $t = $res['table'];
        if (Schema::hasColumn($t, 'updated_at')) $d['updated_at'] = now();
        $isNew = ! $id;
        if ($id) { abort_unless(self::scopeArea(DB::table($t), $t, '', $res)->where('id', $id)->exists(), 404); DB::table($t)->where('id', $id)->update($d); }
        else { if (Schema::hasColumn($t, 'created_at')) $d['created_at'] = now(); $id = DB::table($t)->insertGetId($d); }
        if (isset($res['after'])) $res['after']($id, $d, $isNew);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MANAGE_SAVE', 'detail' => substr("$key #$id", 0, 250), 'ip' => $r->ip()]);
        return redirect()->route('admin.manage', $key)->with('ok', 'Saved.');
    }

    public function destroy(Request $r, string $key, int $id)
    {
        $res = $this->res($key); $this->allow($r, $res, 'delete');
        abort_unless(! empty($res['delete']), 404);
        abort_unless(self::scopeArea(DB::table($res['table']), $res['table'], '', $res)->where('id', $id)->exists(), 404);
        DB::table($res['table'])->where('id', $id)->delete();
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MANAGE_DELETE', 'detail' => substr("$key #$id", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', 'Hata diya.');
    }

    public function act(Request $r, string $key, int $id, string $action)
    {
        $res = $this->res($key); $a = $res['actions'][$action] ?? abort(404);
        $this->allow($r, $res, $a['perm'] ?? 'edit');
        $row = self::scopeArea(DB::table($res['table']), $res['table'], '', $res)->where('id', $id)->first() ?? abort(404);
        abort_if(isset($a['when']) && ! in_array($row->status ?? null, $a['when'], true), 422, 'This action is not possible in this status.');
        foreach ($a['only'] ?? [] as $c => $v) abort_if((int) $row->$c !== (int) $v, 422, 'This action is no longer possible.');
        $upd = $a['set'] + (Schema::hasColumn($res['table'], 'updated_at') ? ['updated_at' => now()] : []);
        DB::table($res['table'])->where('id', $id)->update($upd);
        if (! empty($a['fraud_remove'])) app(\App\Services\FraudService::class)->remove((int) $row->profile_id, $a['reason'] ?? 'Removed by admin', $r->user()->id, ! empty($a['block_mobile']));
        if (! empty($a['call'])) app($a['call'][0])->{$a['call'][1]}($id, $r->user());
        if (! empty($a['suspend'])) DB::table('users')->where('id', $row->{$a['suspend']})->where('role', 'member')->update(['is_active' => false]);
        if (! empty($a['hide_message'])) DB::table('chat_messages')->where('id', $row->message_id)->update(['hidden' => true]);
        if (! empty($a['notify'])) {
            [$col, $text] = $a['notify'];
            $uids = $col === '__family' ? DB::table('users')->where('family_id', $row->family_id)->where('role', 'member')->pluck('id') : collect([$row->$col])->filter();
            foreach ($uids as $uid) UserNotification::create(['user_id' => $uid, 'title' => $text]);
        }
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MANAGE_ACTION', 'detail' => substr("$key #$id $action", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', 'Updated.');
    }
}
