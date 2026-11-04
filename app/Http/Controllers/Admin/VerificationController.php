<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Family, UserNotification, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

/** Membership verification: ID proof + two member references -> verified -> Membership ID (ABMM-<country iso>-<chapter>-<serial>). */
class VerificationController extends Controller
{
    public function index(Request $r)
    {
        $rows = Family::query()->whereIn('verification_status', [$r->status ?: 'submitted'])
            ->when($r->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('head_name', 'like', "%$v%")->orWhere('mobile', 'like', "%$v%")->orWhere('family_code', $v)))
            ->orderBy('updated_at')->paginate(30)->withQueryString();
        $refs = DB::table('member_references')->whereIn('family_id', $rows->pluck('id'))->get()->groupBy('family_id');
        return view('admin.global.verification', ['rows' => $rows, 'refs' => $refs]);
    }

    public function proof(Family $family)
    {
        abort_unless($family->id_proof_path && Storage::disk('local')->exists($family->id_proof_path), 404);
        return Storage::disk('local')->response($family->id_proof_path);    // private disk: never a public URL
    }

    public function decide(Request $r, Family $family)
    {
        $d = $r->validate(['decision' => 'required|in:verified,rejected', 'note' => 'nullable|string|max:250', 'member_category' => 'nullable|in:life,annual,patron,general', 'chapter' => 'nullable|string|max:150']);
        if ($d['decision'] === 'verified') {
            $confirmed = DB::table('member_references')->where('family_id', $family->id)->where('status', 'confirmed')->count();
            abort_if($confirmed < 2 && ! $r->boolean('override_refs') , 422, 'The two member references are not confirmed. (Tick the checkbox to override.)');
            abort_if($r->boolean('override_refs') && ! $r->user()->hasPermission('verification.verify'), 403);
        }
        DB::transaction(function () use ($family, $d, $r) {
            $upd = ['verification_status' => $d['decision'], 'verification_note' => $d['note'] ?? null, 'verified_by' => $r->user()->id, 'verified_at' => now()];
            if ($d['decision'] === 'verified') {
                $upd['member_category'] = $d['member_category'] ?? $family->member_category ?? 'general';
                $upd['chapter'] = $d['chapter'] ?? $family->chapter ?? ($family->city ?: null);
                if (! $family->membership_no) $upd['membership_no'] = self::nextMembershipNo($family);
            }
            Family::withoutGlobalScopes()->whereKey($family->id)->update($upd);
        });
        if ($u = User::where('role', 'member')->where('family_id', $family->id)->first())
            UserNotification::create(['user_id' => $u->id, 'title' => $d['decision'] === 'verified' ? 'Your membership is verified. Membership ID: ' . $family->fresh()->membership_no : 'Verification rejected' . ($d['note'] ? ': ' . $d['note'] : '')]);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'MEMBER_VERIFY', 'detail' => "#{$family->id} {$d['decision']}", 'ip' => $r->ip()]);
        return back()->with('ok', 'Verification updated.');
    }

    public static function nextMembershipNo(Family $f): string
    {
        $iso = strtoupper(\App\Models\CountrySetting::whereRaw('LOWER(country)=?', [mb_strtolower((string) $f->country)])->value('iso') ?: substr(preg_replace('/[^A-Za-z]/', '', (string) $f->country), 0, 2) ?: 'IN');
        $prefix = "ABMM-$iso-" . now()->format('y') . '-';
        do {
            $last = (int) substr((string) Family::withoutGlobalScopes()->where('membership_no', 'like', $prefix . '%')->orderByDesc('membership_no')->value('membership_no'), -6);
            $no = $prefix . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
        } while (Family::withoutGlobalScopes()->where('membership_no', $no)->exists());
        return $no;
    }
}
