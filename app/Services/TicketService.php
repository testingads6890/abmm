<?php

namespace App\Services;

use App\Models\{Event, Family, Payment, User, UserNotification};
use App\Services\Payments\PaymentLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Event ticketing: ticket types (paid / free), group booking, coupons via the payment ledger, QR tickets, check-in, waitlist, refunds. */
class TicketService
{
    public const HOLD_MINUTES = 15;

    public function __construct(private PaymentLedger $ledger, private PointsService $points) {}

    public static function qr(int $eventId, string $code): string { return "ABMM-TK$eventId-$code"; }

    /** Seats still free for a ticket type (paid orders + unexpired pending holds count as taken). */
    public function remaining(object $type): ?int
    {
        if ($type->quantity === null) return null;
        $taken = (int) DB::table('ticket_orders')->where('ticket_type_id', $type->id)->where(fn ($q) => $q->where('status', 'paid')
            ->orWhere(fn ($w) => $w->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(self::HOLD_MINUTES))))->sum('qty');
        return max(0, $type->quantity - $taken);
    }

    /** @return array [status, payload] status: ok | soldout | closed | invalid */
    public function order(Event $e, User $u, int $typeId, array $names, ?string $coupon, ?string $gateway = null): array
    {
        $type = DB::table('ticket_types')->where(['id' => $typeId, 'event_id' => $e->id, 'is_active' => true])->first();
        if (! $e->ticketing || ! $type) return ['invalid', ['message' => 'Ticket not available.']];
        if ($why = app(ParichayService::class)->blockReason($e, $u)) return ['invalid', ['message' => $why]];
        $now = now();
        if (($type->sale_starts && $type->sale_starts > $now) || ($type->sale_ends && $type->sale_ends < $now) || $e->event_date->lt(today())) return ['closed', ['message' => 'Ticket sales are closed.']];
        $names = array_values(array_filter(array_map(fn ($n) => mb_substr(trim((string) $n), 0, 120), $names)));
        $qty = max(1, count($names) ?: 1);
        if ($qty > $type->max_per_order) return ['invalid', ['message' => "Ek order me maximum {$type->max_per_order} ticket."]];
        $f = Family::withoutGlobalScopes()->find($u->family_id);
        $names = $names ?: [$u->name];
        return DB::transaction(function () use ($e, $u, $type, $qty, $names, $coupon, $f, $gateway) {
            DB::table('ticket_types')->where('id', $type->id)->lockForUpdate()->first();
            $left = $this->remaining($type);
            if ($left !== null && $left < $qty) return ['soldout', ['message' => 'Not enough seats left.', 'left' => $left]];
            $id = DB::table('ticket_orders')->insertGetId(['number' => 'TKT' . strtoupper(Str::random(8)), 'event_id' => $e->id, 'user_id' => $u->id, 'family_id' => $u->family_id, 'ticket_type_id' => $type->id,
                'qty' => $qty, 'amount' => 0, 'currency' => $type->currency, 'coupon' => $coupon, 'status' => 'pending', 'attendees' => json_encode($names, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
            $p = $this->ledger->create(['purpose' => 'ticket', 'purpose_ref' => (string) $id, 'amount' => round($type->price * $qty, 2), 'currency' => $type->currency, 'country' => $f->country, 'city' => $f->city,
                'user_id' => $u->id, 'family_id' => $f->id, 'payer_name' => $f->head_name, 'payer_email' => $f->email, 'payer_mobile' => PhoneService::e164($f->mobile, $f->country_code ?: '+91'),
                'coupon' => $coupon, 'gateway' => $gateway, 'audience' => 'ticket', 'meta' => ['title' => "{$e->title} - {$type->name} x$qty"]]);
            DB::table('ticket_orders')->where('id', $id)->update(['payment_id' => $p->id, 'amount' => $p->amount]);
            return ['ok', ['order_id' => $id, 'payment' => $p]];
        });
    }

    /** Payment settled -> issue the tickets (idempotent). */
    public function confirm(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $o = DB::table('ticket_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $o || $o->status === 'paid') return;
            DB::table('ticket_orders')->where('id', $orderId)->update(['status' => 'paid', 'updated_at' => now()]);
            $names = json_decode($o->attendees ?: '[]', true) ?: [];
            for ($i = 0; $i < $o->qty; $i++) {
                do { $code = strtoupper(Str::random(10)); } while (DB::table('tickets')->where('code', $code)->exists());
                DB::table('tickets')->insert(['order_id' => $o->id, 'event_id' => $o->event_id, 'holder_name' => $names[$i] ?? ('Guest ' . ($i + 1)), 'code' => $code]);
            }
            DB::table('ticket_waitlist')->where(['ticket_type_id' => $o->ticket_type_id, 'user_id' => $o->user_id])->update(['status' => 'converted', 'updated_at' => now()]);
            $title = DB::table('events')->where('id', $o->event_id)->value('title');
            UserNotification::create(['user_id' => $o->user_id, 'title' => "Your ticket is confirmed: $title ({$o->qty} ticket). Order {$o->number}"]);
        });
    }

    /** Refund / cancellation: void the tickets, free the seats, offer them to the waitlist. */
    public function release(int $orderId, string $newStatus = 'cancelled'): void
    {
        $o = DB::table('ticket_orders')->where('id', $orderId)->first();
        if (! $o || in_array($o->status, ['cancelled', 'refunded'], true)) return;
        DB::table('ticket_orders')->where('id', $orderId)->update(['status' => $newStatus, 'updated_at' => now()]);
        DB::table('tickets')->where('order_id', $orderId)->update(['void' => true]);
        $this->promote($o->ticket_type_id);
    }

    public function joinWaitlist(Event $e, User $u, int $typeId, int $qty): bool
    {
        $type = DB::table('ticket_types')->where(['id' => $typeId, 'event_id' => $e->id])->first();
        if (! $type) return false;
        DB::table('ticket_waitlist')->updateOrInsert(['ticket_type_id' => $typeId, 'user_id' => $u->id], ['event_id' => $e->id, 'qty' => max(1, min($qty, $type->max_per_order)), 'status' => 'waiting', 'notified_at' => null, 'updated_at' => now(), 'created_at' => now()]);
        return true;
    }

    /** Notifies waiting members in order while seats fit. @return int notified */
    public function promote(int $typeId): int
    {
        $type = DB::table('ticket_types')->find($typeId); if (! $type) return 0;
        $left = $this->remaining($type); $n = 0;
        foreach (DB::table('ticket_waitlist')->where(['ticket_type_id' => $typeId, 'status' => 'waiting'])->orderBy('id')->get() as $w) {
            if ($left !== null && $left < $w->qty) continue;
            DB::table('ticket_waitlist')->where('id', $w->id)->update(['status' => 'offered', 'notified_at' => now(), 'updated_at' => now()]);
            UserNotification::create(['user_id' => $w->user_id, 'title' => 'A seat is available! Book your ticket quickly: ' . DB::table('events')->where('id', $type->event_id)->value('title')]);
            if ($left !== null) $left -= $w->qty; $n++;
        }
        return $n;
    }

    /** @return array [status, payload]  ok | duplicate | invalid | wrong_event | void */
    public function checkIn(Event $e, string $code, int $by): array
    {
        $code = trim($code);
        if (preg_match('/^ABMM-TK(\d+)-([A-Z0-9]+)$/', $code, $m)) { if ((int) $m[1] !== $e->id) return ['wrong_event', ['message' => 'This ticket does not belong to this event.']]; $code = $m[2]; }
        $t = DB::table('tickets')->where('code', strtoupper($code))->first();
        if (! $t) return ['invalid', ['message' => 'Ticket not found.']];
        if ($t->event_id !== $e->id) return ['wrong_event', ['message' => 'This ticket does not belong to this event.']];
        if ($t->void) return ['void', ['message' => 'The ticket has been cancelled/refunded.']];
        if ($t->checked_in_at) return ['duplicate', ['message' => 'Already checked in.', 'name' => $t->holder_name, 'at' => $t->checked_in_at]];
        DB::table('tickets')->where('id', $t->id)->update(['checked_in_at' => now(), 'checked_by' => $by]);
        $uid = DB::table('ticket_orders')->where('id', $t->order_id)->value('user_id');
        if ($uid && $e->attend_points) $this->points->award($uid, 'event_attend', (string) $e->id, $e->attend_points);
        return ['ok', ['name' => $t->holder_name]];
    }

    public function sales(int $eventId): array
    {
        $o = DB::table('ticket_orders')->where('event_id', $eventId);
        return ['orders' => (clone $o)->where('status', 'paid')->count(), 'tickets' => (int) (clone $o)->where('status', 'paid')->sum('qty'), 'revenue' => (float) (clone $o)->where('status', 'paid')->sum('amount'),
            'checked_in' => DB::table('tickets')->where('event_id', $eventId)->where('void', false)->whereNotNull('checked_in_at')->count(), 'waitlist' => DB::table('ticket_waitlist')->where('event_id', $eventId)->where('status', 'waiting')->count()];
    }

    public static function ics(Event $e): string
    {
        $start = $e->event_date->copy()->setTimeFromTimeString($e->event_time ?: '10:00:00');
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $esc = fn ($s) => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\;", "\\,", "\\n"], (string) $s);
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ABMM Sampark//EN\r\nBEGIN:VEVENT\r\nUID:event-{$e->id}@abmm\r\nDTSTAMP:" . $fmt(now()) . "\r\nDTSTART:" . $fmt($start) . "\r\nDTEND:" . $fmt($start->copy()->addHours(2))
            . "\r\nSUMMARY:" . $esc($e->title) . "\r\nLOCATION:" . $esc($e->online_url ?: $e->venue) . "\r\nDESCRIPTION:" . $esc($e->description) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }
}
