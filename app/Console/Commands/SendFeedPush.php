<?php

namespace App\Console\Commands;

use App\Models\FeedPost;
use Illuminate\Console\Command;

/** php artisan abmm:feed-push — every 10 minutes: sends the push of scheduled news / notices once they go live. */
class SendFeedPush extends Command
{
    protected $signature = 'abmm:feed-push';
    protected $description = 'Push scheduled news and notices that have just gone live';

    public function handle(): int
    {
        $n = 0;
        FeedPost::withoutGlobalScopes()->live()->whereIn('type', ['news', 'condolence'])->whereNotNull('notify_audience')->where('notify_audience', '!=', 'none')->whereNull('notified_at')->limit(50)->get()
            ->each(function (FeedPost $p) use (&$n) {
                $sent = $p->pushToAudience($p->notify_audience, $p->type === 'condolence' ? 'Shok Samachar' : 'Mahasabha Suchna', $p->title);
                if ($sent !== null) { $p->update(['notified_at' => now()]); if ($sent) $n++; }
            });
        $this->info("Feed push: $n");
        return self::SUCCESS;
    }
}
