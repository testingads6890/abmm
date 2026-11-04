<?php

namespace App\Services;

use App\Models\{Event, Family, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** RSVP, QR pass and check-in. The QR content is "ABMM-EV<eventId>-<passCode>"; the pass code is random, unique and single-use for check-in. */
class RsvpService
{
    public function __construct(private PointsService $points, private CertificateService $certs) {}

    public static function qr(int $eventId, string $code): string { return "ABMM-EV{$eventId}-{$code}"; }

    /** Does this event concern this family? (same audience rules as the notification that created it) */
    public function visibleTo(Event $e, Family $f): bool
    {
        // an organiser's event is shown only while the organiser's subscription is live
        if ($e->partner_id && (! $e->listing_until || (string) $e->listing_until < today()->toDateString())) return false;
        return match ($e->audience) {
            'anchal' => strcasecmp((string) \App\Models\Lookup::anchalOf($f->pradesh), (string) $e->audience_value) === 0,
            'pradesh' => strcasecmp((string) $e->audience_value, (string) $f->pradesh) === 0, 'state' => strcasecmp((string) $e->audience_value, (string) $f->state) === 0,
            'district' => strcasecmp((string) $e->audience_value, (string) $f->district) === 0, 'tehsil' => strcasecmp((string) $e->audience_value, (string) $f->tehsil) === 0,
            'city' => strcasecmp((string) $e->audience_value, (string) $f->city) === 0, 'zone' => strcasecmp((string) $e->audience_value, (string) $f->zone) === 0,
            default => true,
        };
    }

    public function used(int $eventId): int
    {
        return (int) DB::table('event_rsvps')->where('event_id', $eventId)->where('status', 'going')->selectRaw('COALESCE(SUM(1 + guests),0) s')->value('s');
    }

    /** @return array{0:string,1:array} [status, payload]  status: ok | closed | full | not_allowed */
    public function rsvp(Event $e, User $u, string $status, int $guests): array
    {
        $f = Family::withoutGlobalScopes()->find($u->family_id);
        if (! $e->rsvp_enabled || ! $f || ! $this->visibleTo($e, $f)) return ['not_allowed', ['message' => 'RSVP is not available for this event.']];
        if ($why = app(ParichayService::class)->blockReason($e, $u)) return ['not_allowed', ['message' => $why]];
        if ($e->event_date->lt(today()) || ($e->rsvp_closes_on && $e->rsvp_closes_on->lt(today()))) return ['closed', ['message' => 'RSVP is closed.']];
        $guests = min(max(0, $guests), (int) $e->max_guests);
        return DB::transaction(function () use ($e, $u, $f, $status, $guests) {
            DB::table('events')->where('id', $e->id)->lockForUpdate()->first();
            $mine = DB::table('event_rsvps')->where(['event_id' => $e->id, 'user_id' => $u->id])->first();
            if ($status === 'going' && $e->capacity) {
                $other = $this->used($e->id) - ($mine && $mine->status === 'going' ? 1 + $mine->guests : 0);
                if ($other + 1 + $guests > $e->capacity) return ['full', ['message' => 'Seats are full.']];
            }
            if ($mine) {
                abort_if($mine->checked_in_at, 422, 'You have already checked in.');
                DB::table('event_rsvps')->where('id', $mine->id)->update(['status' => $status, 'guests' => $guests, 'updated_at' => now()]);
                $code = $mine->pass_code;
            } else {
                do { $code = strtoupper(Str::random(10)); } while (DB::table('event_rsvps')->where('pass_code', $code)->exists());
                DB::table('event_rsvps')->insert(['event_id' => $e->id, 'user_id' => $u->id, 'family_id' => $f->id, 'guests' => $guests, 'status' => $status, 'pass_code' => $code, 'created_at' => now(), 'updated_at' => now()]);
                if ($status === 'going') $this->points->award($u, 'event_rsvp', (string) $e->id);
            }
            return ['ok', ['status' => $status, 'guests' => $guests, 'pass_code' => $code, 'qr' => self::qr($e->id, $code)]];
        });
    }

    /** @return array{0:string,1:array} */
    public function checkIn(Event $e, string $scanned, int $staffId): array
    {
        $code = strtoupper(trim($scanned));
        if (preg_match('/^ABMM-EV(\d+)-([A-Z0-9]+)$/', $code, $m)) { if ((int) $m[1] !== $e->id) return ['wrong_event', ['message' => 'This pass belongs to another event.']]; $code = $m[2]; }
        $res = DB::transaction(function () use ($e, $code, $staffId) {
            $r = DB::table('event_rsvps')->where(['event_id' => $e->id, 'pass_code' => $code])->lockForUpdate()->first();
            if (! $r || $r->status !== 'going') return ['invalid', ['message' => 'The pass is not valid.']];
            $name = DB::table('users')->where('id', $r->user_id)->value('name');
            $info = ['name' => $name, 'guests' => (int) $r->guests];
            if ($r->checked_in_at) return ['duplicate', $info + ['checked_in_at' => $r->checked_in_at, 'message' => 'Already checked in.']];
            DB::table('event_rsvps')->where('id', $r->id)->update(['checked_in_at' => now(), 'checked_by' => $staffId, 'updated_at' => now()]);
            return ['ok', $info + ['user_id' => $r->user_id]];
        });
        if ($res[0] === 'ok') {
            $this->points->award((int) $res[1]['user_id'], 'event_attend', (string) $e->id, $e->attend_points);
            unset($res[1]['user_id']);
        }
        return $res;
    }

    public function summary(int $eventId): array
    {
        $r = DB::table('event_rsvps')->where('event_id', $eventId)->where('status', 'going');
        return ['families' => (clone $r)->count(), 'people' => (int) (clone $r)->selectRaw('COALESCE(SUM(1 + guests),0) s')->value('s'),
            'present_families' => (clone $r)->whereNotNull('checked_in_at')->count(), 'present_people' => (int) (clone $r)->whereNotNull('checked_in_at')->selectRaw('COALESCE(SUM(1 + guests),0) s')->value('s'),
            'declined' => DB::table('event_rsvps')->where('event_id', $eventId)->where('status', 'declined')->count()];
    }

    public function issueCertificates(Event $e): int
    {
        $n = 0;
        foreach (DB::table('event_rsvps')->where('event_id', $e->id)->whereNotNull('checked_in_at')->get() as $r)
            if ($this->certs->issue($r->user_id, $r->family_id, 'event', 'Participation: ' . $e->title, 'For attending on ' . $e->event_date->format('d M Y') . ' at ' . $e->venue, (string) $e->id)) $n++;
        return $n;
    }
}
