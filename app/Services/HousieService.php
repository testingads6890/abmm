<?php

namespace App\Services;

use App\Models\{User, UserNotification};
use App\Services\Payments\PaymentLedger;
use App\Support\Area;
use Illuminate\Support\Facades\DB;

/**
 * Milestone F - weekly Housie (Tambola): tickets (free or paid), server-side number calling (the sequence is never sent to clients),
 * validated claims (early five, three lines, full house), sponsor prizes and banners, leaderboard.
 */
class HousieService
{
    public const PRIZES = ['early5' => 'Early Five', 'line1' => 'Top Line', 'line2' => 'Middle Line', 'line3' => 'Bottom Line', 'full' => 'Full House'];

    public function __construct(private PaymentLedger $ledger) {}

    private function tell(?int $uid, string $title, string $body = ''): void
    {
        if ($uid) UserNotification::create(['user_id' => $uid, 'title' => trim($title . ($body ? ": $body" : ''))]);
    }

    /** Standard 3x9 ticket: 15 numbers, 5 per row, at least one per column, column c holds numbers from its own ten (1-9, 10-19 ... 80-90). */
    public function generateTicket(): array
    {
        while (true) {
            // each column gets 1 number first, then 6 more columns get a second one (9 + 6 = 15)
            $counts = array_fill(0, 9, 1);
            foreach (array_rand(range(0, 8), 6) as $c) $counts[$c]++;
            // distribute: choose rows for each column count so every row ends with exactly 5
            $rowFill = [0, 0, 0]; $grid = array_fill(0, 3, array_fill(0, 9, 0)); $ok = true;
            $order = range(0, 8); shuffle($order);
            foreach ($order as $c) {
                $avail = array_values(array_filter([0, 1, 2], fn ($r) => $rowFill[$r] < 5));
                if (count($avail) < $counts[$c]) { $ok = false; break; }
                usort($avail, fn ($a, $b) => $rowFill[$a] <=> $rowFill[$b] ?: random_int(-1, 1));
                $rows = array_slice($avail, 0, $counts[$c]);
                $lo = $c === 0 ? 1 : $c * 10; $hi = $c === 8 ? 90 : $c * 10 + 9;
                $nums = range($lo, $hi); shuffle($nums); $pick = array_slice($nums, 0, count($rows)); sort($pick); sort($rows);
                foreach ($rows as $i => $r) { $grid[$r][$c] = $pick[$i]; $rowFill[$r]++; }
            }
            if ($ok && $rowFill === [5, 5, 5]) return $grid;
        }
    }

    public function numbersOf(array $grid): array { return array_values(array_filter(array_merge(...$grid))); }

    /** @return array<int,object> tickets */
    public function buy(User $u, object $game, int $qty, ?string $coupon = null): array
    {
        abort_unless($game->status === 'scheduled' && $game->starts_at > now()->toDateTimeString(), 422, 'Tickets for this game are no longer available.');
        $have = DB::table('housie_tickets')->where(['game_id' => $game->id, 'user_id' => $u->id])->whereIn('status', ['paid', 'pending'])->count();
        abort_if($have + $qty > $game->max_tickets, 422, "A member can take at most {$game->max_tickets} tickets.");
        $paid = $game->ticket_price <= 0; $ids = [];
        for ($i = 0; $i < $qty; $i++) $ids[] = DB::table('housie_tickets')->insertGetId(['game_id' => $game->id, 'user_id' => $u->id, 'grid' => json_encode($this->generateTicket()), 'status' => $paid ? 'paid' : 'pending', 'created_at' => now(), 'updated_at' => now()]);
        if ($paid) { app(PointsService::class)->award($u, 'housie_play', (string) $game->id); return [$ids, null]; }
        $a = Area::of($u); $f = $a['family_id'] ? \App\Models\Family::withoutGlobalScopes()->find($a['family_id']) : null;
        $p = $this->ledger->create(['purpose' => 'housie', 'purpose_ref' => (string) $ids[0], 'amount' => $game->ticket_price * $qty, 'currency' => $game->currency, 'country' => $a['country'], 'city' => $a['city'], 'user_id' => $u->id, 'family_id' => $a['family_id'],
            'payer_name' => $f?->head_name ?: $u->name, 'payer_email' => $f?->email, 'payer_mobile' => $f ? PhoneService::e164($f->mobile, $f->country_code ?: '+91') : null, 'coupon' => $coupon, 'audience' => 'member', 'meta' => ['title' => "Housie: {$game->title} x$qty"]]);
        DB::table('housie_tickets')->whereIn('id', $ids)->update(['payment_id' => $p->id]);
        return [$ids, $p];
    }

    /** Settle hook: all tickets of that payment become valid. */
    public function paid(int $firstTicketId): void
    {
        $t = DB::table('housie_tickets')->find($firstTicketId); if (! $t || ! $t->payment_id) return;
        DB::table('housie_tickets')->where('payment_id', $t->payment_id)->where('status', 'pending')->update(['status' => 'paid', 'updated_at' => now()]);
        app(PointsService::class)->award($t->user_id, 'housie_play', (string) $t->game_id);
        $this->tell($t->user_id, 'Housie tickets confirm', 'Your tickets will appear in the app before the game.');
    }

    public function refunded(int $firstTicketId): void
    {
        $t = DB::table('housie_tickets')->find($firstTicketId); if (! $t || ! $t->payment_id) return;
        DB::table('housie_tickets')->where('payment_id', $t->payment_id)->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /** Admin: cancel a scheduled game; tickets are cancelled and holders told (refund paid tickets from Payments). */
    public function cancel(int $gameId): void
    {
        $g = DB::table('housie_games')->find($gameId); if (! $g) return;
        DB::table('housie_games')->where('id', $gameId)->update(['status' => 'cancelled', 'updated_at' => now()]);
        $uids = DB::table('housie_tickets')->where('game_id', $gameId)->whereIn('status', ['paid', 'pending'])->distinct()->pluck('user_id');
        DB::table('housie_tickets')->where('game_id', $gameId)->whereIn('status', ['paid', 'pending'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        foreach ($uids as $uid) $this->tell($uid, 'Housie cancel', "{$g->title} has been cancelled. Paid tickets will be refunded.");
    }

    public function start(int $gameId): void
    {
        DB::transaction(function () use ($gameId) {
            $g = DB::table('housie_games')->where('id', $gameId)->lockForUpdate()->first();
            if (! $g || $g->status !== 'scheduled') return;
            $seq = range(1, 90); shuffle($seq);
            DB::table('housie_games')->where('id', $gameId)->update(['status' => 'running', 'sequence' => json_encode($seq), 'called' => json_encode([]), 'last_called_at' => now()->subSeconds($g->interval_sec), 'updated_at' => now()]);
        });
        foreach (DB::table('housie_tickets')->where(['game_id' => $gameId, 'status' => 'paid'])->distinct()->pluck('user_id') as $uid) $this->tell($uid, 'Housie has started!', 'Open the app and watch the numbers.');
    }

    /** Calls every number that is due (one per interval). Safe to call from many requests: the game row is locked. Returns numbers called. */
    public function tick(int $gameId): int
    {
        $n = 0;
        DB::transaction(function () use ($gameId, &$n) {
            $g = DB::table('housie_games')->where('id', $gameId)->lockForUpdate()->first();
            if (! $g || $g->status !== 'running') return;
            $seq = json_decode($g->sequence, true); $called = json_decode($g->called ?: '[]', true); $last = \Carbon\Carbon::parse($g->last_called_at);
            while (count($called) < 90 && $last->copy()->addSeconds($g->interval_sec)->lte(now()) && $n < 90) { $called[] = $seq[count($called)]; $last = $last->addSeconds($g->interval_sec); $n++; }
            if (! $n) return;
            $status = count($called) >= 90 ? 'finished' : 'running';
            DB::table('housie_games')->where('id', $gameId)->update(['called' => json_encode($called), 'last_called_at' => $last, 'status' => $status, 'updated_at' => now()]);
        });
        return $n;
    }

    public function validPrize(array $grid, array $called, string $prize): bool
    {
        $marked = fn (array $row) => count(array_filter($row, fn ($v) => $v !== 0 && in_array($v, $called, true)));
        return match ($prize) {
            'early5' => $marked(array_merge(...$grid)) >= 5,
            'line1' => $marked($grid[0]) === 5, 'line2' => $marked($grid[1]) === 5, 'line3' => $marked($grid[2]) === 5,
            'full' => $marked(array_merge(...$grid)) === 15,
            default => false,
        };
    }

    public function claim(User $u, object $game, int $ticketId, string $prize): array
    {
        abort_unless(isset(self::PRIZES[$prize]), 422, 'Invalid prize.');
        $res = null;
        DB::transaction(function () use ($u, $game, $ticketId, $prize, &$res) {
            $g = DB::table('housie_games')->where('id', $game->id)->lockForUpdate()->first();
            abort_unless($g && in_array($g->status, ['running', 'finished'], true), 422, 'The game is not running right now.');
            $t = DB::table('housie_tickets')->where(['id' => $ticketId, 'game_id' => $g->id, 'user_id' => $u->id, 'status' => 'paid'])->first() ?? abort(404, 'Ticket not found.');
            abort_if(DB::table('housie_claims')->where(['game_id' => $g->id, 'prize' => $prize, 'status' => 'won'])->exists(), 422, 'This prize has already been won.');
            abort_if(DB::table('housie_claims')->where(['game_id' => $g->id, 'user_id' => $u->id, 'status' => 'invalid'])->count() >= 3, 403, 'You cannot claim in this game because of too many wrong claims.');
            $ok = $this->validPrize(json_decode($t->grid, true), json_decode($g->called ?: '[]', true), $prize);
            DB::table('housie_claims')->insert(['game_id' => $g->id, 'ticket_id' => $t->id, 'user_id' => $u->id, 'prize' => $prize, 'status' => $ok ? 'won' : 'invalid', 'created_at' => now(), 'updated_at' => now()]);
            if (! $ok) { $res = ['won' => false]; return; }
            $res = ['won' => true];
            if ($prize === 'full') DB::table('housie_games')->where('id', $g->id)->update(['status' => 'finished', 'updated_at' => now()]);
        });
        if ($res['won']) {
            $prizes = json_decode($game->prizes, true) ?: [];
            app(PointsService::class)->award($u, 'housie_win', $game->id . ':' . $prize);
            $this->tell($u->id, 'Congratulations! You won Housie', self::PRIZES[$prize] . (isset($prizes[$prize]) ? ' - ' . $prizes[$prize] : ''));
            UserNotification::notifyAdmins("Housie winner: {$u->name} - " . self::PRIZES[$prize] . " ({$game->title})", null, \App\Support\Area::of($u)['pradesh'] ?? null);
            $res['prize'] = self::PRIZES[$prize]; $res['prize_text'] = $prizes[$prize] ?? null;
            if ($prize === 'full') foreach (DB::table('housie_tickets')->where(['game_id' => $game->id, 'status' => 'paid'])->distinct()->pluck('user_id') as $uid) if ($uid !== $u->id) $this->tell($uid, 'Housie khatam', "{$u->name} won Full House. See you next week!");
        }
        return $res;
    }

    private function shortName(int $uid): string
    {
        $p = preg_split('/\s+/', trim((string) DB::table('users')->where('id', $uid)->value('name'))) ?: [];
        return ($p[0] ?? 'Member') . (isset($p[1]) ? ' ' . mb_substr($p[1], 0, 1) . '.' : '');
    }

    public function state(object $game, int $userId): array
    {
        // With thousands of players polling, only one request per second takes the row lock and calls numbers; the rest just read.
        if (\Illuminate\Support\Facades\Cache::add('housie_tick_' . $game->id, 1, 1)) $this->tick($game->id);
        $g = DB::table('housie_games')->find($game->id);
        $called = json_decode($g->called ?: '[]', true); $prizes = json_decode($g->prizes, true) ?: [];
        $winners = DB::table('housie_claims')->where(['game_id' => $g->id, 'status' => 'won'])->get()->keyBy('prize');
        $sponsor = $g->sponsor_id ? DB::table('housie_sponsors')->where('status', 'active')->find($g->sponsor_id) : null;
        $tickets = DB::table('housie_tickets')->where(['game_id' => $g->id, 'user_id' => $userId])->whereIn('status', ['paid', 'pending'])->get()->map(function ($t) use ($called) {
            $grid = json_decode($t->grid, true);
            return ['id' => $t->id, 'status' => $t->status, 'grid' => $grid, 'marked' => count(array_filter($this->numbersOf($grid), fn ($v) => in_array($v, $called, true))), 'claimable' => array_values(array_filter(array_keys(self::PRIZES), fn ($p) => $this->validPrize($grid, $called, $p)))];
        })->values();
        $next = $g->status === 'running' ? max(0, $g->interval_sec - (int) \Carbon\Carbon::parse($g->last_called_at)->diffInSeconds(now(), true)) : null;
        return ['id' => $g->id, 'title' => $g->title, 'status' => $g->status, 'starts_at' => $g->starts_at, 'ticket_price' => (float) $g->ticket_price, 'currency' => $g->currency, 'max_tickets' => $g->max_tickets,
            'called' => $called, 'last_number' => $called ? end($called) : null, 'remaining' => 90 - count($called), 'next_call_in' => $next,
            'prizes' => collect(self::PRIZES)->map(fn ($label, $k) => ['key' => $k, 'label' => $label, 'prize' => $prizes[$k] ?? null, 'winner' => isset($winners[$k]) ? $this->shortName($winners[$k]->user_id) : null])->values(),
            'sponsor' => $sponsor ? ['name' => $sponsor->name, 'banner_url' => $sponsor->banner_url, 'link_url' => $sponsor->link_url] : null, 'my_tickets' => $tickets];
    }

    public function leaderboard(?string $month = null, int $limit = 30): array
    {
        $q = DB::table('housie_claims')->where('status', 'won')->when($month, fn ($x) => $x->where('created_at', '>=', \Carbon\Carbon::parse("$month-01")->startOfMonth())->where('created_at', '<=', \Carbon\Carbon::parse("$month-01")->endOfMonth()))
            ->groupBy('user_id')->selectRaw('user_id, count(*) as wins')->orderByDesc('wins')->limit($limit)->get();
        return $q->map(fn ($r, $i) => ['rank' => $i + 1, 'name' => $this->shortName($r->user_id), 'wins' => (int) $r->wins])->all();
    }

    /** Scheduler (every minute): starts due games, calls numbers, reminds ticket holders 30 min before. */
    public function run(): array
    {
        $started = 0; $called = 0; $reminded = 0;
        foreach (DB::table('housie_games')->where('status', 'scheduled')->where('starts_at', '<=', now())->pluck('id') as $id) { $this->start($id); $started++; }
        foreach (DB::table('housie_games')->where('status', 'running')->pluck('id') as $id) $called += $this->tick($id);
        foreach (DB::table('housie_games')->where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addMinutes(30)])->whereNull('sequence')->get() as $g) {
            $key = 'housie_reminded_' . $g->id;
            if (\Illuminate\Support\Facades\Cache::add($key, 1, now()->addHours(2))) {
                foreach (DB::table('housie_tickets')->where(['game_id' => $g->id, 'status' => 'paid'])->distinct()->pluck('user_id') as $uid) { $this->tell($uid, 'Housie 30 minute me', $g->title); $reminded++; }
            }
        }
        return compact('started', 'called', 'reminded');
    }
}
