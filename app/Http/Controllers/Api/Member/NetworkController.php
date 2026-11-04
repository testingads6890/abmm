<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NetworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Member app: networking - connections, auto connect, suggestions, discovery, saved members, profile views, QR share, contact sync (consent), chat. */
class NetworkController extends Controller
{
    public function __construct(private NetworkService $net) {}

    public function settings(Request $r) { return $this->net->settings($r->user()->id); }

    public function saveSettings(Request $r)
    {
        $d = $r->validate(['discoverable' => 'sometimes|boolean', 'auto_accept' => 'sometimes|boolean', 'contact_sync' => 'sometimes|boolean']);
        return $this->net->saveSettings($r->user(), $d);
    }

    public function suggestions(Request $r) { return $this->net->suggestions($r->user()); }
    public function discover(Request $r) { return $this->net->discover($r->user(), $r->only(['country', 'city', 'profession', 'q'])); }

    public function show(Request $r, int $id)
    {
        $u = User::withoutGlobalScopes()->where('role', 'member')->where('is_active', true)->find($id);
        abort_unless($u && ! $this->net->blocked($r->user()->id, $id), 404);
        $this->net->view($r->user(), $id);
        $l = $this->net->link($r->user()->id, $id);
        return $this->net->card($id, true) + ['connection' => $l ? ['id' => $l->id, 'status' => $l->status, 'incoming' => $l->target_id === $r->user()->id] : null,
            'saved' => DB::table('saved_members')->where(['user_id' => $r->user()->id, 'target_id' => $id])->exists()];
    }

    public function connect(Request $r)
    {
        $d = $r->validate(['user_id' => 'required|integer', 'note' => 'nullable|string|max:200']);
        [$st, $msg] = $this->net->request($r->user(), $d['user_id'], $d['note'] ?? null);
        return response()->json(['status' => $st, 'message' => $msg], in_array($st, ['pending', 'connected'], true) ? 200 : 422);
    }

    public function respond(Request $r, int $id)
    {
        $d = $r->validate(['decision' => 'required|in:accept,reject']);
        [$st, $msg] = $this->net->respond($r->user(), $id, $d['decision']);
        return response()->json(['status' => $st, 'message' => $msg], $st === 'ok' ? 200 : 404);
    }

    public function requests(Request $r)
    {
        $me = $r->user()->id;
        $rows = fn ($col, $other) => DB::table('connections')->where($col, $me)->where('status', 'pending')->orderByDesc('id')->get(['id', $other . ' as uid', 'note', 'created_at'])->map(fn ($c) => (array) $c + ['user' => $this->net->card($c->uid)]);
        return ['incoming' => $rows('target_id', 'user_id'), 'outgoing' => $rows('user_id', 'target_id')];
    }

    public function connections(Request $r)
    {
        $me = $r->user()->id;
        return DB::table('connections')->where('status', 'accepted')->where(fn ($q) => $q->where('user_id', $me)->orWhere('target_id', $me))->orderByDesc('updated_at')->get()
            ->map(fn ($c) => ['connection_id' => $c->id, 'since' => $c->updated_at] + $this->net->card($c->user_id === $me ? $c->target_id : $c->user_id));
    }

    public function disconnect(Request $r, int $id)
    {
        $me = $r->user()->id;
        $n = DB::table('connections')->where('id', $id)->where(fn ($q) => $q->where('user_id', $me)->orWhere('target_id', $me))->where('status', '!=', 'blocked')->delete();
        abort_unless($n, 404);
        return ['message' => 'Connection hata diya.'];
    }

    public function block(Request $r, int $id) { abort_if($id === $r->user()->id, 422); $this->net->block($r->user(), $id); return ['message' => 'Blocked.']; }
    public function unblock(Request $r, int $id) { $this->net->unblock($r->user(), $id); return ['message' => 'Unblocked.']; }

    public function report(Request $r, int $id)
    {
        $d = $r->validate(['reason' => 'required|in:spam,abuse,fake,harassment,other', 'detail' => 'nullable|string|max:500']);
        abort_if($id === $r->user()->id, 422);
        DB::table('member_reports')->insert(['reporter_id' => $r->user()->id, 'target_id' => $id, 'reason' => $d['reason'], 'detail' => $d['detail'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        return ['message' => 'Report received. Admin will review it.'];
    }

    public function save(Request $r, int $id)
    {
        abort_if($id === $r->user()->id, 422);
        $q = DB::table('saved_members')->where(['user_id' => $r->user()->id, 'target_id' => $id]);
        if ($q->exists()) { $q->delete(); return ['saved' => false]; }
        DB::table('saved_members')->insert(['user_id' => $r->user()->id, 'target_id' => $id, 'created_at' => now()]);
        return ['saved' => true];
    }

    public function saved(Request $r)
    {
        return DB::table('saved_members')->where('user_id', $r->user()->id)->orderByDesc('id')->pluck('target_id')->map(fn ($id) => $this->net->card($id));
    }

    /** Recently viewed by me (history) + number of people who viewed me this month. */
    public function history(Request $r)
    {
        $me = $r->user()->id;
        $seen = DB::table('profile_views')->where('viewer_id', $me)->orderByDesc('viewed_at')->limit(60)->get(['target_id', 'viewed_at'])->unique('target_id')->take(30)->values();
        return ['recently_viewed' => $seen->map(fn ($v) => $this->net->card($v->target_id) + ['viewed_at' => $v->viewed_at]),
            'viewed_me_30d' => DB::table('profile_views')->where('target_id', $me)->where('viewed_at', '>=', now()->subDays(30))->distinct()->count('viewer_id'),
            'connections' => DB::table('connections')->where('status', 'accepted')->where(fn ($q) => $q->where('user_id', $me)->orWhere('target_id', $me))->count()];
    }

    public function myQr(Request $r) { return ['qr' => NetworkService::qrToken($r->user()->id)]; }

    public function scanQr(Request $r)
    {
        $d = $r->validate(['qr' => 'required|string|max:60']);
        $u = NetworkService::resolveQr($d['qr']);
        abort_unless($u && ! $this->net->blocked($r->user()->id, $u->id), 404, 'The QR is not valid.');
        return $this->net->card($u->id);
    }

    public function contacts(Request $r)
    {
        $d = $r->validate(['hashes' => 'required|array|max:1000']);
        [$st, $rows] = $this->net->matchContacts($r->user(), $d['hashes']);
        return response()->json($st === 'ok' ? ['matches' => $rows] : ['message' => 'Allow contact sync first (settings).'], $st === 'ok' ? 200 : 403);
    }

    // ---------------- chat ----------------
    public function conversations(Request $r)
    {
        $me = $r->user()->id;
        $out = [];
        foreach (DB::table('connections')->where('status', 'accepted')->where(fn ($q) => $q->where('user_id', $me)->orWhere('target_id', $me))->get() as $c) {
            $other = $c->user_id === $me ? $c->target_id : $c->user_id;
            $last = DB::table('chat_messages')->where('connection_id', $c->id)->where('hidden', false)->orderByDesc('id')->first();
            if (! $last || $this->net->blocked($me, $other)) continue;
            $out[] = ['user' => $this->net->card($other), 'last' => $last->body, 'at' => $last->created_at, 'unread' => DB::table('chat_messages')->where('connection_id', $c->id)->where('sender_id', $other)->whereNull('read_at')->count()];
        }
        usort($out, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return $out;
    }

    public function messages(Request $r, int $id)
    {
        $c = $this->net->conn($r->user(), $id) ?? abort(403, 'Connect first.');
        DB::table('chat_messages')->where('connection_id', $c->id)->where('sender_id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        return DB::table('chat_messages')->where('connection_id', $c->id)->where('hidden', false)->when($r->query('after'), fn ($q, $v) => $q->where('id', '>', (int) $v))->orderByDesc('id')->limit(100)->get(['id', 'sender_id', 'body', 'read_at', 'created_at'])->reverse()->values();
    }

    public function send(Request $r, int $id)
    {
        $d = $r->validate(['body' => 'required|string|max:2000']);
        $mid = $this->net->send($r->user(), $id, $d['body']);
        abort_unless($mid, 403, 'You can only message connected members.');
        return response()->json(['id' => $mid], 201);
    }

    public function reportMessage(Request $r, int $mid)
    {
        $d = $r->validate(['reason' => 'required|string|max:80']);
        $m = DB::table('chat_messages as m')->join('connections as c', 'c.id', '=', 'm.connection_id')->where('m.id', $mid)->where(fn ($q) => $q->where('c.user_id', $r->user()->id)->orWhere('c.target_id', $r->user()->id))->first(['m.id']);
        abort_unless($m, 404);
        DB::table('message_reports')->insert(['message_id' => $mid, 'reporter_id' => $r->user()->id, 'reason' => $d['reason'], 'created_at' => now(), 'updated_at' => now()]);
        return ['message' => 'Report received.'];
    }
}
