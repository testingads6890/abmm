<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\{MatrimonyProfile, User, UserNotification};
use App\Services\{BiodataService, FraudService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

/** Member app (Parinay): biodata upload + auto-fill, duplicate check, biodata PDF + scheduled delivery preferences, reporting, and consent-based chat between families. */
class MatchToolsController extends Controller
{
    public function __construct(private MatrimonyController $mat, private BiodataService $bio, private FraudService $fraud) {}

    // ---------------- biodata upload / duplicate check ----------------
    private function dupView($dups)
    {
        return $dups->map(fn ($p) => ['parinay_id' => $p->parinay_id, 'name' => mb_substr((string) $p->name, 0, 1) . str_repeat('*', max(2, mb_strlen((string) $p->name) - 1))])->values();   // enough to find it, not to read it
    }

    /** POST matrimony/biodata/parse  (file: pdf / jpg / png / txt, or text) -> draft fields for the Parinay form; nothing is saved to the profile */
    public function parse(Request $r)
    {
        $r->validate(['file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp,txt', 'text' => 'nullable|string|max:20000']);
        abort_unless($r->hasFile('file') || $r->filled('text'), 422, 'Send a file or text.');
        $text = ''; $method = null; $path = null; $mime = null;
        if ($r->hasFile('file')) {
            $f = $r->file('file'); $mime = $f->getMimeType(); $path = $f->store('biodata/' . $r->user()->id, 'local');
            [$text, $method] = $this->bio->extract(Storage::disk('local')->path($path), (string) $mime);
        } else { $text = (string) $r->input('text'); $method = 'text'; }
        $fields = $text !== '' ? $this->bio->parse($text) : [];
        $id = DB::table('biodata_uploads')->insertGetId(['user_id' => $r->user()->id, 'family_id' => $r->user()->family_id, 'path' => $path, 'mime' => $mime, 'text' => $text ?: null, 'parsed' => json_encode($fields),
            'status' => $fields ? 'parsed' : 'manual', 'method' => $method, 'created_at' => now(), 'updated_at' => now()]);
        $dups = $fields ? $this->fraud->duplicates($fields, null, $r->user()->family_id) : collect();
        return ['upload_id' => $id, 'status' => $fields ? 'parsed' : 'manual', 'method' => $method, 'fields' => (object) $fields, 'duplicates' => $this->dupView($dups), 'blocked' => (bool) $this->fraud->blocked($fields),
            'message' => $fields ? 'Details extracted. Please check and save in the form.' : 'Could not read details from the biodata. The file is saved; fill in the form yourself.'];
    }

    public function check(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'dob' => 'nullable|date', 'mobile' => 'nullable|string|max:20']);
        return ['duplicates' => $this->dupView($this->fraud->duplicates($d, null, $r->user()->family_id)), 'blocked' => (bool) $this->fraud->blocked($d)];
    }

    // ---------------- biodata PDF + delivery preferences ----------------
    public function pdf(Request $r, int $id)
    {
        $me = $this->mat->acting($r, false);
        $p = MatrimonyProfile::withoutGlobalScopes()->findOrFail($id);
        $contact = false;
        if ($p->id !== $me->id && $p->family_id !== $me->family_id) {
            abort_unless(MatrimonyController::isPaidPublic($me) && $p->status === 'paid' && $p->visible, 404);
            $i = $this->interestBetween($me->id, $p->id);
            $contact = $i && $i->status === 'accepted' && ($i->from_profile_id === $p->id ? $i->contact_from : $i->contact_to);       // the other family agreed to share contact
        } else $contact = true;
        return response($this->bio->pdf($p, $contact), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="biodata-' . $p->parinay_id . '.pdf"']);
    }

    public function prefs(Request $r)
    {
        $me = $this->mat->acting($r, false);
        $x = DB::table('biodata_prefs')->where('profile_id', $me->id)->first();
        return ['frequency' => $x->frequency ?? 'off', 'channels' => $x ? json_decode($x->channels ?: '[]', true) : ['app'], 'count' => $x->count ?? 5, 'last_sent_at' => $x->last_sent_at ?? null];
    }

    public function savePrefs(Request $r)
    {
        $d = $r->validate(['frequency' => 'required|in:off,weekly,monthly', 'channels' => 'nullable|array', 'channels.*' => 'in:app,email,whatsapp', 'count' => 'nullable|integer|min:1|max:10']);
        $me = $this->mat->acting($r);
        DB::table('biodata_prefs')->updateOrInsert(['profile_id' => $me->id], ['user_id' => $r->user()->id, 'frequency' => $d['frequency'], 'channels' => json_encode($d['channels'] ?? ['app']), 'count' => $d['count'] ?? 5, 'updated_at' => now(), 'created_at' => now()]);
        return ['message' => 'Biodata delivery setting saved.'];
    }

    // ---------------- reporting ----------------
    public function report(Request $r, int $id)
    {
        $d = $r->validate(['reason' => 'required|in:' . implode(',', array_keys(FraudService::REASONS)), 'detail' => 'nullable|string|max:500']);
        $me = $this->mat->acting($r, false);
        $p = MatrimonyProfile::withoutGlobalScopes()->where('status', '!=', 'removed')->findOrFail($id);
        abort_if($p->family_id === $me->family_id, 422, 'You cannot report your own profile.');
        abort_if(DB::table('profile_reports')->where(['profile_id' => $p->id, 'reporter_id' => $r->user()->id, 'status' => 'open'])->exists(), 422, 'You have already reported this.');
        $this->fraud->report($p, $r->user()->id, $d['reason'], $d['detail'] ?? null);
        return response()->json(['message' => 'Report received. The admin team will investigate.'], 201);
    }

    // ---------------- consent based chat ----------------
    private function interestBetween(int $a, int $b): ?object
    {
        return DB::table('matrimony_interests')->where(fn ($q) => $q->where('from_profile_id', $a)->where('to_profile_id', $b))->orWhere(fn ($q) => $q->where('from_profile_id', $b)->where('to_profile_id', $a))->first();
    }

    /** the accepted interest I am part of: [interest, my profile, other profile, myFlagColumn, otherFlagColumn] */
    private function thread(Request $r, int $interestId): array
    {
        $me = $this->mat->acting($r);
        $i = DB::table('matrimony_interests')->find($interestId) ?? abort(404);
        abort_unless($i->from_profile_id === $me->id || $i->to_profile_id === $me->id, 404);
        abort_unless($i->status === 'accepted', 403, 'Chat opens only after the interest is accepted.');
        $mine = $i->from_profile_id === $me->id; $other = MatrimonyProfile::withoutGlobalScopes()->findOrFail($mine ? $i->to_profile_id : $i->from_profile_id);
        abort_if($other->status === 'removed', 404);
        return [$i, $me, $other, $mine ? 'contact_from' : 'contact_to', $mine ? 'contact_to' : 'contact_from'];
    }

    private function owner(MatrimonyProfile $p): ?User
    {
        return User::where('role', 'member')->where('is_active', true)->where('member_id', $p->member_id)->first() ?? User::where('role', 'member')->where('is_active', true)->where('family_id', $p->family_id)->orderBy('id')->first();
    }

    public function threads(Request $r)
    {
        $me = $this->mat->acting($r);
        $rows = DB::table('matrimony_interests')->where('status', 'accepted')->where(fn ($q) => $q->where('from_profile_id', $me->id)->orWhere('to_profile_id', $me->id))->orderByDesc('updated_at')->get();
        $profiles = MatrimonyProfile::withoutGlobalScopes()->where('status', '!=', 'removed')->whereIn('id', $rows->flatMap(fn ($i) => [$i->from_profile_id, $i->to_profile_id]))->get()->keyBy('id');
        return ['data' => $rows->map(function ($i) use ($me, $profiles) {
            $oid = $i->from_profile_id === $me->id ? $i->to_profile_id : $i->from_profile_id; $o = $profiles[$oid] ?? null; if (! $o) return null;
            $last = DB::table('match_messages')->where('interest_id', $i->id)->where('hidden', false)->orderByDesc('id')->first();
            return ['interest_id' => $i->id, 'profile' => ['id' => $o->id, 'parinay_id' => $o->parinay_id, 'name' => $o->name, 'age' => $o->dob?->age, 'city' => $o->city], 'last' => $last?->body, 'last_at' => $last?->created_at,
                'unread' => DB::table('match_messages')->where('interest_id', $i->id)->where('from_profile_id', '!=', $me->id)->whereNull('read_at')->where('hidden', false)->count()];
        })->filter()->values()];
    }

    public function messages(Request $r, int $id)
    {
        [$i, $me, $other, $my, $their] = $this->thread($r, $id);
        DB::table('match_messages')->where('interest_id', $id)->where('from_profile_id', '!=', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        $msgs = DB::table('match_messages')->where('interest_id', $id)->where('hidden', false)->orderBy('id')->limit(300)->get(['id', 'from_profile_id', 'body', 'created_at', 'read_at'])->map(fn ($m) => ['id' => $m->id, 'mine' => $m->from_profile_id === $me->id, 'body' => $m->body, 'at' => $m->created_at, 'read' => (bool) $m->read_at]);
        $shared = (bool) $i->$their;     // the other family agreed to share their contact
        $u = $shared ? $this->owner($other) : null;
        return ['profile' => ['id' => $other->id, 'parinay_id' => $other->parinay_id, 'name' => $other->name], 'i_share_contact' => (bool) $i->$my, 'they_share_contact' => $shared,
            'family_contact' => $shared ? ['name' => $u?->name, 'mobile' => $other->mobile] : null, 'data' => $msgs];
    }

    private static function mask(string $t): string
    {
        $t = preg_replace('/[\w.+\-]+@[\w\-]+\.[\w.\-]+/u', '[email hidden]', $t);
        return preg_replace('/(?:\+?\d[\s\-().]*){7,}/u', '[number hidden]', $t);
    }

    public function send(Request $r, int $id)
    {
        $d = $r->validate(['body' => 'required|string|min:1|max:1000']);
        [$i, $me, $other, $my, $their] = $this->thread($r, $id);
        $body = ($i->contact_from && $i->contact_to) ? trim($d['body']) : self::mask(trim($d['body']));       // numbers / e-mails are shared only when both families consent
        $mid = DB::table('match_messages')->insertGetId(['interest_id' => $id, 'from_profile_id' => $me->id, 'body' => $body, 'hidden' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('matrimony_interests')->where('id', $id)->update(['updated_at' => now()]);
        if ($u = $this->owner($other)) UserNotification::create(['user_id' => $u->id, 'title' => "New message: {$me->name} ({$me->parinay_id})"]);
        return response()->json(['id' => $mid, 'body' => $body, 'masked' => $body !== trim($d['body'])], 201);
    }

    /** POST matrimony/interests/{id}/consent {share:true|false} : agree (or stop agreeing) to share my family's contact with this match */
    public function consent(Request $r, int $id)
    {
        $d = $r->validate(['share' => 'required|boolean']);
        [$i, $me, $other, $my] = $this->thread($r, $id);
        DB::table('matrimony_interests')->where('id', $id)->update([$my => (bool) $d['share'], 'updated_at' => now()]);
        if ($d['share'] && ($u = $this->owner($other))) UserNotification::create(['user_id' => $u->id, 'title' => "The family of {$me->name} has agreed to share contact details."]);
        return ['i_share_contact' => (bool) $d['share']];
    }

    public function reportMessage(Request $r, int $id, int $mid)
    {
        [$i, $me, $other] = $this->thread($r, $id);
        abort_unless(DB::table('match_messages')->where(['id' => $mid, 'interest_id' => $id])->where('from_profile_id', '!=', $me->id)->exists(), 404);
        $this->fraud->report($other, $r->user()->id, 'abusive', "Message #$mid");
        return response()->json(['message' => 'Report received.'], 201);
    }
}
