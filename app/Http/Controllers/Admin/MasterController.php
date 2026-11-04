<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Lookup};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Super Admin > Master Data: add / rename / reorder / deactivate every dropdown value used across the panel and app. */
class MasterController extends Controller
{
    /** type => [[table, column], ...] columns that store the chosen text (used to cascade a rename and to block deleting values in use). */
    private const USED_IN = [
        'caste' => [['families', 'caste'], ['members', 'caste']], 'gotra' => [['families', 'gotra'], ['members', 'gotra']],
        'surname' => [['families', 'surname'], ['members', 'surname']], 'gender' => [['families', 'gender'], ['members', 'gender']],
        'resident_type' => [['families', 'resident_type']], 'country' => [['families', 'country']], 'marital_status' => [['families', 'marital_status'], ['members', 'marital_status']],
        'blood_group' => [['families', 'blood_group'], ['members', 'blood_group']], 'education' => [['families', 'education'], ['members', 'education']],
        'occupation' => [['families', 'occupation'], ['members', 'occupation']], 'profession' => [['families', 'profession'], ['members', 'profession']],
        'business_type' => [['families', 'business_type'], ['members', 'business_type'], ['businesses', 'type']], 'income' => [['families', 'income_range']],
        'designation' => [['committee_members', 'designation']], 'pradesh' => [['families', 'pradesh'], ['users', 'pradesh'], ['committee_members', 'pradesh']],
        'state' => [['families', 'state'], ['matrimony_profiles', 'state']], 'district' => [['families', 'district'], ['users', 'district'], ['committee_members', 'district'], ['matrimony_profiles', 'district']],
        'tehsil' => [['families', 'tehsil'], ['users', 'tehsil'], ['committee_members', 'tehsil']], 'city' => [['families', 'city']],
        'trust_category' => [['trusts', 'trust_category']], 'trust_name' => [['trusts', 'trust_name']], 'archive_type' => [['members', 'archive_type']], 'relation' => [['members', 'relation']],
    ];

    private function cfg(string $type, bool $forWrite = false): array
    {
        abort_unless(isset(config('masters.types')[$type]), 404);
        abort_if($forWrite && ! empty(config('masters.types')[$type]['fixed']), 403, 'This list belongs to the system and cannot be changed.');
        return config('masters.types')[$type];
    }

    /** Parent is mandatory unless the type says 'parent_optional' (e.g. a Pradesh may have no Anchal yet). */
    private function parentRequired(array $cfg): bool
    {
        return isset($cfg['parent']) && empty($cfg['parent_optional']);
    }

    private function usedCount(string $type, string $name): int
    {
        $n = 0;
        foreach (self::USED_IN[$type] ?? [] as [$t, $c]) {
            if (Schema::hasColumn($t, $c)) $n += DB::table($t)->where($c, $name)->limit(1000)->count();
        }
        return $n;
    }

    public function index(Request $r, string $type)
    {
        $cfg = $this->cfg($type);
        $parentType = $cfg['parent'] ?? null;
        $rows = Lookup::ofType($type)->with('parent')
            ->when($r->q, fn ($q, $v) => $q->where('name', 'like', "%$v%"))
            ->when($r->parent, fn ($q, $v) => $q->where('parent_id', $v))
            ->when($r->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort')->orderBy('name')->paginate(50)->withQueryString();
        return view('admin.masters.index', [
            'type' => $type, 'cfg' => $cfg, 'rows' => $rows, 'total' => Lookup::ofType($type)->count(),
            'parents' => $parentType ? Lookup::ofType($parentType)->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'parentLabel' => $parentType ? config("masters.types.$parentType.label") : null,
        ]);
    }

    /** "91" / "+91" -> "+91"; anything else -> null. */
    private function dial(?string $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\+?(\d{1,4})$/', $v, $m) ? '+' . $m[1] : null;
    }

    private function dupe(string $type, string $name, ?int $parent, ?int $ignore = null): bool
    {
        return Lookup::ofType($type)->where('name', $name)->where('parent_id', $parent)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists();
    }

    public function store(Request $r, string $type)
    {
        $cfg = $this->cfg($type, true);
        $hasParent = isset($cfg['parent']);
        $d = $r->validate([
            'name' => 'nullable|string|max:190', 'bulk' => 'nullable|string|max:60000', 'pincode' => 'nullable|digits:6', 'dial' => ['nullable', 'regex:/^\+?\d{1,4}$/'],
            'parent_id' => [$this->parentRequired($cfg) ? 'required' : 'nullable', 'integer', 'exists:lookups,id'],
        ]);
        $parent = $hasParent && ! empty($d['parent_id']) ? (int) $d['parent_id'] : null;
        if ($parent) abort_unless(Lookup::whereKey($parent)->where('type', $cfg['parent'])->exists(), 422);

        $lines = [];
        $isDial = ! empty($cfg['dial']);
        if (! empty($d['name'])) $lines[] = [trim($d['name']), $isDial ? $this->dial($d['dial'] ?? null) : ($d['pincode'] ?? null)];
        foreach (preg_split('/\R/', (string) ($d['bulk'] ?? '')) as $ln) {            // bulk: one per line, "Name" or "Name,pincode"
            $ln = trim($ln); if ($ln === '') continue;
            [$n, $pin] = array_pad(array_map('trim', explode(',', $ln, 2)), 2, null);
            $lines[] = [$n, $isDial ? $this->dial($pin) : (($pin !== null && preg_match('/^\d{6}$/', $pin)) ? $pin : null)];
        }
        if (! $lines) throw ValidationException::withMessages(['name' => 'Enter a name or paste values in bulk.']);

        $added = 0; $skipped = 0; $next = (int) Lookup::ofType($type)->max('sort') + 1;
        foreach ($lines as [$n, $pin]) {
            if ($n === '' || mb_strlen($n) > 190 || $this->dupe($type, $n, $parent)) { $skipped++; continue; }
            Lookup::create(['type' => $type, 'name' => $n, 'parent_id' => $parent, 'pincode' => ! empty($cfg['pincode']) ? $pin : null, 'meta' => $isDial && $pin ? ['dial' => $pin] : null, 'sort' => $next++, 'is_active' => true]);
            $added++;
        }
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MASTER_ADD', 'detail' => "$type +$added", 'ip' => $r->ip()]);
        return back()->with('ok', "$added values added" . ($skipped ? ", $skipped already existed / were blank (skipped)" : '') . '.');
    }

    public function update(Request $r, Lookup $lookup)
    {
        $cfg = $this->cfg($lookup->type, true);
        $d = $r->validate([
            'name' => 'required|string|max:190', 'pincode' => 'nullable|digits:6', 'dial' => ['nullable', 'regex:/^\+?\d{1,4}$/'], 'sort' => 'nullable|integer|min:0|max:100000',
            'parent_id' => [$this->parentRequired($cfg) ? 'required' : 'nullable', 'integer', 'exists:lookups,id'], 'is_active' => 'nullable|boolean',
            'meta' => 'nullable|array', 'meta.address' => 'nullable|string|max:400', 'meta.country' => 'nullable|string|max:60', 'meta.state' => 'nullable|string|max:80',
            'meta.email' => 'nullable|email|max:160', 'meta.contact' => 'nullable|string|max:60', 'meta.pdf_url' => 'nullable|url|max:500',
        ]);
        $name = trim($d['name']);
        $parent = isset($cfg['parent']) && ! empty($d['parent_id']) ? (int) $d['parent_id'] : null;
        if ($parent) abort_unless(Lookup::whereKey($parent)->where('type', $cfg['parent'])->exists(), 422);
        if ($this->dupe($lookup->type, $name, $parent, $lookup->id)) throw ValidationException::withMessages(['name' => 'This value already exists.']);

        $old = $lookup->name;
        DB::transaction(function () use ($lookup, $name, $parent, $d, $cfg, $old, $r) {
            $lookup->update(['name' => $name, 'parent_id' => $parent, 'pincode' => ! empty($cfg['pincode']) ? ($d['pincode'] ?? null) : null,
                'sort' => $d['sort'] ?? $lookup->sort, 'is_active' => $r->boolean('is_active')]);
            if ($lookup->type === 'trust_name') {   // directory details shown in the member app's Trusts screen
                $lookup->update(['meta' => array_filter((array) ($d['meta'] ?? []), fn ($v) => $v !== null && $v !== '') ?: null]);
                cache()->forget('api.masters');
            }
            if (! empty($cfg['dial'])) {   // Country master: phone country code
                $lookup->update(['meta' => ($dial = $this->dial($d['dial'] ?? null)) ? ['dial' => $dial] : null]);
            }
            if ($old !== $name) {   // keep existing records in sync with the renamed value
                foreach (self::USED_IN[$lookup->type] ?? [] as [$t, $c]) if (Schema::hasColumn($t, $c)) DB::table($t)->where($c, $old)->update([$c => $name]);
                if ($lookup->type === 'anchal') foreach (['events', 'notification_logs'] as $t) DB::table($t)->where('audience', 'anchal')->where('audience_value', $old)->update(['audience_value' => $name]);
            }
        });
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MASTER_EDIT', 'detail' => substr("{$lookup->type}: $old", 0, 250), 'ip' => $r->ip()]);
        return back()->with('ok', 'Updated' . ($old !== $name ? ' (the name was also changed in old records)' : '') . '.');
    }

    public function destroy(Request $r, Lookup $lookup)
    {
        $this->cfg($lookup->type, true);
        if ($lookup->children()->exists()) return back()->withErrors(['name' => "There are more values under '{$lookup->name}'; remove them first."]);
        if ($this->usedCount($lookup->type, $lookup->name)) return back()->withErrors(['name' => "'{$lookup->name}' is used in records. Make it Inactive instead of deleting."]);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MASTER_DEL', 'detail' => substr("{$lookup->type}: {$lookup->name}", 0, 250), 'ip' => $r->ip()]);
        $lookup->delete();
        return back()->with('ok', 'Hata diya.');
    }
}
