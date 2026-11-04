<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use App\Services\{AdService, TicketService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** php artisan abmm:community-jobs  (hourly) - event reminders, push ads, expiring holds / ads / listings, waitlist offers. */
class CommunityJobs extends Command
{
    protected $signature = 'abmm:community-jobs';
    protected $description = 'Event reminders (24h / 1h), push-ad delivery, expiry of stale ticket holds, ads and listings';

    public function handle(AdService $ads, TicketService $tickets): int
    {
        $this->reminders('reminded_24h_at', now()->addHours(23), now()->addHours(25), 'kal');
        $this->reminders('reminded_1h_at', now()->addMinutes(30), now()->addMinutes(90), '1 ghante me');
        $pushed = $ads->pushDue();

        // unpaid ticket orders older than the hold -> cancelled, seats go to the waitlist
        $stale = DB::table('ticket_orders')->where('status', 'pending')->where('created_at', '<', now()->subMinutes(TicketService::HOLD_MINUTES))->get();
        foreach ($stale as $o) $tickets->release($o->id, 'cancelled');

        $ended = DB::table('business_ads')->where('status', 'approved')->whereDate('ends_on', '<', today())->update(['status' => 'expired']);
        $this->info("push ads: $pushed, stale orders released: {$stale->count()}, ads expired: $ended");
        return self::SUCCESS;
    }

    private function reminders(string $col, $from, $to, string $when): void
    {
        foreach (DB::table('events')->whereNull($col)->get() as $e) {
            $start = \Carbon\Carbon::parse($e->event_date)->setTimeFromTimeString($e->event_time ?: '10:00:00');
            if (! $start->between($from, $to)) continue;
            $uids = DB::table('ticket_orders')->where(['event_id' => $e->id, 'status' => 'paid'])->pluck('user_id')
                ->merge(DB::table('event_rsvps')->where(['event_id' => $e->id, 'status' => 'going'])->pluck('user_id'))->unique();
            $link = $e->online_url ? " Use \"Join online\" in the app for the join link." : '';
            foreach ($uids as $uid) UserNotification::create(['user_id' => $uid, 'title' => "Reminder: {$e->title} $when ({$start->format('d M, h:i A')}).$link"]);
            DB::table('events')->where('id', $e->id)->update([$col => now()]);
        }
    }
}
