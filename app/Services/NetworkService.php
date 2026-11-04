<?php

namespace App\Services;

use App\Models\{Family, Member, User, UserNotification};
use Illuminate\Support\Facades\DB;

/** Member networking: connections (request/accept/reject/block), auto-connect, suggestions, discovery, QR share, consent-based contact matching, chat. */
class NetworkService
{
    public static function qrToken(int $userId): string { return "ABMM-P-$userId-" . CardService::sign('profile', $userId); }

    public static function resolveQr(string $t): ?User
    {
        if (! preg_match('/^ABMM-P-(\d+)-([a-f0-9]{10})$/', trim($t), $m) || ! hash_equals(CardService::sign('profile', (int) $m[1]), $m[2])) return null;
        return User::withoutGlobalScopes()->where('role', 'member')->find((int) $m[1]);
    }

    public function settings(int $uid): object
    {
        return DB::table('networking_settings')->where('user_id', $uid)->first() ?? (object) ['user_id' => $uid, 'discoverable' => true, 'auto_accept' => false, 'contact_sync' => false, 'phone_hash' => null];
    }

    public function saveSettings(User $u, array $d): object
    {
        $cur = $this->settings($u->id); $row = ['updated_at' => now()];
        foreach (['discoverable', 'auto_accept'] as $k) if (array_key_exists($k, $d)) $row[$k] = (bool) $d[$k];
        if (array_key_exists('contact_sync', $d)) {            // explicit consent: hash is stored only while the member has agreed
            $row['contact_sync'] = (bool) $d['contact_sync'];
            $row['consent_at'] = $d['contact_sync'] ? now() : null;
            $row['phone_hash'] = $d['contact_sync'] ? self::hashPhone($u) : null;
        }
        DB::table('networking_settings')->updateOrInsert(['user_id' => $u->id], $row + ['created_at' => now()] + ['discoverable' => $cur->discoverable]);
        return $this->settings($u->id);
    }

    public static function hashPhone(User $u): string
    {
        $f = Family::withoutGlobalScopes()->find($u->family_id);
        return hash('sha256', PhoneService::e164((string) $u->mobile, $f->country_code ?: '+91'));
    }

    public function blocked(int $a, int $b): bool
    {
        return DB::table('connections')->where('status', 'blocked')->where(fn ($q) => $q->where(['user_id' => $a, 'target_id' => $b])->orWhere(['user_id' => $b, 'target_id' => $a]))->exists();
    }

    public function link(int $a, int $b): ?object
    {
        return DB::table('connections')->where(fn ($q) => $q->where(['user_id' => $a, 'target_id' => $b])->orWhere(['user_id' => $b, 'target_id' => $a]))->where('status', '!=', 'blocked')->first();
    }

    /** @return array [status, message] */
    public function request(User $me, int $targetId, ?string $note): array
    {
        if ($targetId === $me->id) return ['invalid', 'You cannot connect with yourself.'];
        $t = User::withoutGlobalScopes()->where('role', 'member')->where('is_active', true)->find($targetId);
        if (! $t || $this->blocked($me->id, $targetId) || ! $this->settings($targetId)->discoverable) return ['not_found', 'Member not found.'];
        if ($l = $this->link($me->id, $targetId)) return [$l->status === 'accepted' ? 'connected' : 'exists', 'A request already exists.'];
        $auto = (bool) $this->settings($targetId)->auto_accept;
        DB::table('connections')->insert(['user_id' => $me->id, 'target_id' => $targetId, 'status' => $auto ? 'accepted' : 'pending', 'note' => $note ? mb_substr($note, 0, 200) : null, 'created_at' => now(), 'updated_at' => now()]);
        UserNotification::create(['user_id' => $targetId, 'title' => $auto ? "{$me->name} connected with you (auto connect)." : "{$me->name} has sent you a connect request."]);
        return [$auto ? 'connected' : 'pending', $auto ? 'Connected.' : 'Request sent.'];
    }

    public function respond(User $me, int $connId, string $decision): array
    {
        $c = DB::table('connections')->where(['id' => $connId, 'target_id' => $me->id, 'status' => 'pending'])->first();
        if (! $c) return ['not_found', 'Request not found.'];
        DB::table('connections')->where('id', $c->id)->update(['status' => $decision === 'accept' ? 'accepted' : 'rejected', 'updated_at' => now()]);
        if ($decision === 'accept') UserNotification::create(['user_id' => $c->user_id, 'title' => "{$me->name} accepted your request."]);
        return ['ok', $decision];
    }

    public function block(User $me, int $targetId): void
    {
        DB::table('connections')->where(fn ($q) => $q->where(['user_id' => $me->id, 'target_id' => $targetId])->orWhere(['user_id' => $targetId, 'target_id' => $me->id]))->delete();
        DB::table('connections')->insert(['user_id' => $me->id, 'target_id' => $targetId, 'status' => 'blocked', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function unblock(User $me, int $targetId): void { DB::table('connections')->where(['user_id' => $me->id, 'target_id' => $targetId, 'status' => 'blocked'])->delete(); }

    public function card(int $uid, bool $full = false): array
    {
        $u = User::withoutGlobalScopes()->find($uid); $f = Family::withoutGlobalScopes()->find($u->family_id);
        $m = $u->member_id ? Member::withoutGlobalScopes()->find($u->member_id) : null;
        return ['user_id' => $u->id, 'name' => $m?->name ?? $u->name, 'city' => $f->city, 'country' => $f->country, 'profession' => $m?->profession ?: $m?->occupation, 'photo_url' => \App\Support\MemberApp::photoUrl($m?->photo ?: $f->photo)]
            + ($full ? ['education' => $m?->education, 'business_type' => $m?->business_type, 'verified' => $f->verification_status === 'verified'] : []);
    }

    public function view(User $me, int $targetId): void
    {
        if ($targetId === $me->id) return;
        if (! DB::table('profile_views')->where(['viewer_id' => $me->id, 'target_id' => $targetId])->where('viewed_at', '>=', now()->subHour())->exists())
            DB::table('profile_views')->insert(['viewer_id' => $me->id, 'target_id' => $targetId, 'viewed_at' => now()]);
    }

    /** Same city / profession / education, not yet linked, discoverable. */
    public function suggestions(User $me, int $limit = 20): array
    {
        $f = Family::withoutGlobalScopes()->find($me->family_id); $m = $me->member_id ? Member::withoutGlobalScopes()->find($me->member_id) : null;
        $linked = DB::table('connections')->where('user_id', $me->id)->pluck('target_id')->merge(DB::table('connections')->where('target_id', $me->id)->pluck('user_id'))->push($me->id)->all();
        $q = User::withoutGlobalScopes()->join('families', 'families.id', '=', 'users.family_id')->leftJoin('members', 'members.id', '=', 'users.member_id')
            ->leftJoin('networking_settings as ns', 'ns.user_id', '=', 'users.id')->where('users.role', 'member')->where('users.is_active', true)->whereNotIn('users.id', $linked)
            ->where(fn ($w) => $w->whereNull('ns.discoverable')->orWhere('ns.discoverable', true))->where('families.country', $f->country)
            ->selectRaw('users.id, (CASE WHEN families.city = ? THEN 2 ELSE 0 END) + (CASE WHEN members.profession IS NOT NULL AND members.profession = ? THEN 2 ELSE 0 END) + (CASE WHEN families.zone IS NOT NULL AND families.zone = ? THEN 1 ELSE 0 END) as score', [$f->city, $m?->profession ?? '', $f->zone ?? ''])
            ->orderByDesc('score')->orderBy('users.id')->limit($limit);
        return $q->get()->map(fn ($x) => $this->card($x->id) + ['score' => (int) $x->score])->all();
    }

    /** International discovery: filters by country / city / profession; only discoverable members. */
    public function discover(User $me, array $f, int $limit = 30): array
    {
        $q = User::withoutGlobalScopes()->join('families', 'families.id', '=', 'users.family_id')->leftJoin('members', 'members.id', '=', 'users.member_id')->leftJoin('networking_settings as ns', 'ns.user_id', '=', 'users.id')
            ->where('users.role', 'member')->where('users.is_active', true)->where('users.id', '!=', $me->id)->where(fn ($w) => $w->whereNull('ns.discoverable')->orWhere('ns.discoverable', true))
            ->whereNotIn('users.id', DB::table('connections')->where('status', 'blocked')->where('target_id', $me->id)->pluck('user_id'))
            ->when($f['country'] ?? null, fn ($w, $v) => $w->where('families.country', $v))->when($f['city'] ?? null, fn ($w, $v) => $w->where('families.city', $v))
            ->when($f['profession'] ?? null, fn ($w, $v) => $w->where('members.profession', 'like', "%$v%"))->when($f['q'] ?? null, fn ($w, $v) => $w->where('users.name', 'like', "%$v%"))
            ->orderBy('users.name')->limit($limit)->select('users.id');
        return $q->get()->map(fn ($x) => $this->card($x->id))->all();
    }

    /** Contact sync: the app sends SHA-256 hashes of E.164 numbers; only members who ALSO consented to contact sync can be matched. */
    public function matchContacts(User $me, array $hashes): array
    {
        if (! $this->settings($me->id)->contact_sync) return ['consent_required', []];
        $hashes = array_slice(array_values(array_unique(array_filter($hashes, fn ($h) => is_string($h) && preg_match('/^[a-f0-9]{64}$/', $h)))), 0, 1000);
        $ids = DB::table('networking_settings')->where('contact_sync', true)->whereIn('phone_hash', $hashes)->where('user_id', '!=', $me->id)->pluck('user_id');
        return ['ok', $ids->map(fn ($id) => $this->card($id))->all()];
    }

    // ---- chat (only between accepted connections)
    public function conn(User $me, int $otherId): ?object
    {
        $c = DB::table('connections')->where('status', 'accepted')->where(fn ($q) => $q->where(['user_id' => $me->id, 'target_id' => $otherId])->orWhere(['user_id' => $otherId, 'target_id' => $me->id]))->first();
        return $c && ! $this->blocked($me->id, $otherId) ? $c : null;
    }

    public function send(User $me, int $otherId, string $body): ?int
    {
        $c = $this->conn($me, $otherId); if (! $c) return null;
        $id = DB::table('chat_messages')->insertGetId(['connection_id' => $c->id, 'sender_id' => $me->id, 'body' => mb_substr(trim($body), 0, 2000), 'created_at' => now()]);
        UserNotification::create(['user_id' => $otherId, 'title' => "New message: {$me->name}"]);
        return $id;
    }
}
