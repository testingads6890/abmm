<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use App\Services\GreetingService;
use App\Support\MemberApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: "Today's birthdays & anniversaries", one-tap wish, birthday privacy. */
class GreetingController extends Controller
{
    /** GET greetings/today?scope=family|district|state */
    public function today(Request $r)
    {
        $d = $r->validate(['scope' => 'nullable|in:family,district,state']);
        $scope = $d['scope'] ?? 'district';
        $u = $r->user();
        $fam = Family::withoutGlobalScopes()->findOrFail($u->family_id);
        if ($scope === 'district' && ! $fam->district) $scope = 'family';
        if ($scope === 'state' && ! $fam->state) $scope = 'family';

        $out = ['date' => now()->toDateString(), 'scope' => $scope, 'birthdays' => [], 'anniversaries' => []];
        foreach (GreetingService::KINDS as $kind) {
            if (! GreetingService::enabled($kind)) continue;
            $q = GreetingService::celebrating($kind)->withoutGlobalScopes()->join('families as f', 'f.id', '=', 'members.family_id')
                ->select('members.id', 'members.name', 'members.surname', 'members.photo', 'members.dob', 'members.anniversary', 'members.hide_birthday',
                    'members.family_id', 'members.relation', 'f.district as f_district', 'f.city as f_city', 'f.pradesh as f_pradesh');
            match ($scope) {
                'family' => $q->where('members.family_id', $fam->id),
                'district' => $q->where('f.district', $fam->district),
                'state' => $q->where('f.state', $fam->state),
            };
            if ($scope !== 'family') $q->where('members.hide_birthday', false); // hidden members show only inside their own family
            $off = GreetingService::offPradesh();
            $rows = $q->orderBy('members.name')->limit(200)->get()->reject(fn ($m) => $m->f_pradesh && in_array($m->f_pradesh, $off, true));

            $wished = DB::table('greeting_wishes')->where('from_user_id', $u->id)->where('kind', $kind)->where('year', now()->year)
                ->whereIn('to_member_id', $rows->pluck('id'))->pluck('to_member_id')->all();

            $out[$kind === 'birthday' ? 'birthdays' : 'anniversaries'] = $rows->map(fn ($m) => [
                'id' => $m->id, 'kind' => $kind, 'name' => trim($m->name . ' ' . $m->surname), 'photo_url' => MemberApp::photoUrl($m->photo),
                'place' => $m->f_city ?: $m->f_district, 'same_family' => (int) $m->family_id === (int) $fam->id,
                'age' => $kind === 'birthday' && $m->dob ? (int) now()->year - (int) $m->dob->year : null,
                'years' => $kind === 'anniversary' && $m->anniversary ? (int) now()->year - (int) $m->anniversary->year : null,
                'is_me' => (int) $m->id === (int) $u->member_id, 'wished' => in_array($m->id, $wished),
            ])->values();
        }
        $out['hide_birthday'] = (bool) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('hide_birthday');
        return $out;
    }

    /** POST greetings/{member}/wish {kind} */
    public function wish(Request $r, int $member)
    {
        $d = $r->validate(['kind' => 'required|in:birthday,anniversary']);
        $u = $r->user();
        if ((int) $member === (int) $u->member_id) return response()->json(['message' => 'You cannot send wishes to yourself.'], 422);

        $m = GreetingService::celebrating($d['kind'])->withoutGlobalScopes()->whereKey($member)->first();
        if (! $m) return response()->json(['message' => 'Today is not this member\'s day.'], 422);
        $fam = Family::withoutGlobalScopes()->find($u->family_id);
        $sameFamily = (int) $m->family_id === (int) $u->family_id;
        if (! $sameFamily && ($m->hide_birthday || Family::withoutGlobalScopes()->whereKey($m->family_id)->value('state') !== $fam?->state)) {
            return response()->json(['message' => 'You cannot send wishes to this member.'], 403);
        }

        $fresh = DB::table('greeting_wishes')->insertOrIgnore(['kind' => $d['kind'], 'from_user_id' => $u->id, 'to_member_id' => $m->id, 'year' => now()->year, 'created_at' => now()]);
        if (! $fresh) return ['ok' => true, 'already' => true, 'message' => 'You have already sent wishes.'];

        $from = trim((string) Member::withoutGlobalScopes()->whereKey($u->member_id)->value('name')) ?: 'One member';
        $to = User::where('member_id', $m->id)->where('is_active', true)->first();
        if ($to) {
            $what = $d['kind'] === 'birthday' ? 'sent a birthday wish' : 'sent an anniversary wish';
            app(GreetingService::class)->notify($to, "$from ne aapko $what", GreetingService::text($d['kind'], trim($m->name)), ['type' => 'wish']);
        }
        return ['ok' => true, 'message' => 'Wishes sent.'];
    }

    /** POST greetings/privacy {hide_birthday, member_id?} — own row, or a family member's when the caller is Head of Family. */
    public function privacy(Request $r)
    {
        $d = $r->validate(['hide_birthday' => 'required|boolean', 'member_id' => 'nullable|integer']);
        $u = $r->user();
        $target = $d['member_id'] ?? $u->member_id;
        $m = Member::withoutGlobalScopes()->where('family_id', $u->family_id)->whereKey($target)->firstOrFail();
        if ((int) $m->id !== (int) $u->member_id) {
            $isHead = Member::withoutGlobalScopes()->whereKey($u->member_id)->value('is_head');
            abort_unless($isHead, 403, 'Only the Head of Family can change another member\'s settings.');
        }
        $m->update(['hide_birthday' => (bool) $d['hide_birthday']]);
        return ['ok' => true, 'hide_birthday' => (bool) $m->hide_birthday];
    }
}
