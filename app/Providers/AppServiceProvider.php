<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}


    private function schedule(): void
    {
        // cron: * * * * * php /path/to/artisan schedule:run
        $this->app->booted(function () {
            $s = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $s->call(fn () => Cache::put('abmm.scheduler.beat', now()->timestamp, 3600))->everyMinute()->name('abmm-heartbeat');   // go-live check reads this
            $s->command('abmm:backup')->dailyAt('03:30')->withoutOverlapping()->onOneServer();               // DB dump + retention (add --files for uploads weekly via cron if wanted)
            $s->command('abmm:erase-accounts')->dailyAt('04:10')->withoutOverlapping()->onOneServer();        // erase accounts whose 30-day deletion grace is over
            $s->command('abmm:greetings')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
            $s->command('abmm:festivals')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
            $s->command('abmm:feed-push')->everyTenMinutes()->withoutOverlapping()->onOneServer();
            $s->command('abmm:subscriptions')->dailyAt('06:00')->withoutOverlapping()->onOneServer();      // expire + 7-day renewal reminder
            $s->command('abmm:reconcile-payments')->hourly()->withoutOverlapping()->onOneServer();        // settle payments whose webhook was missed
            $s->command('abmm:monthly-recognition')->monthlyOn(1, '08:00')->withoutOverlapping()->onOneServer();      // Star of the Month certificates + badge
            $s->command('abmm:profile-scan')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
            $s->command('abmm:biodata-digest')->dailyAt('09:00')->withoutOverlapping()->onOneServer();                // weekly / monthly biodata PDFs of new matches
            $s->command('abmm:career-jobs')->hourly()->withoutOverlapping()->onOneServer();                   // job alert digests, mentor session reminders
            $s->command('abmm:blood-reminders')->dailyAt('10:00')->withoutOverlapping()->onOneServer();       // donors eligible again, expire old blood requests
            $s->command('abmm:learning-run')->hourly()->withoutOverlapping()->onOneServer();                   // weekly exams, ranks, certificates, class reminders
            $s->command('abmm:housie-run')->everyMinute()->withoutOverlapping()->onOneServer();               // Housie: start games, call numbers
            $s->command('abmm:community-jobs')->hourly()->withoutOverlapping()->onOneServer();               // event reminders, push ads, ticket holds
        });
    }

    /** SMTP details saved in Admin > Settings override .env mail config (password stored encrypted). */
    public function boot(): void
    {
        $this->schedule();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\Looping::class, fn () => Cache::put('abmm.worker.beat', now()->timestamp, 1800));   // queue worker heartbeat for the go-live check
        // Role/permission gates: "module.action" (config/permissions.php). Super admin passes everything.
        foreach (User::allPermissionKeys() as $perm) Gate::define($perm, fn (User $u) => $u->hasPermission($perm));
        Gate::before(fn (User $u) => $u->is_active && $u->isSuper() ? true : null);

        try {
            if (app()->runningInConsole() && ! app()->runningUnitTests() && ! Schema::hasTable('settings')) return;
            $cfg = Cache::remember('smtp_cfg', 300, fn () => Schema::hasTable('settings') ? Setting::whereIn('key', ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_from_name', 'smtp_reply', 'smtp_tls'])->pluck('value', 'key')->all() : []);
            if (empty($cfg['smtp_host'])) return;
            config([
                'mail.default' => 'smtp', 'mail.mailers.smtp.host' => $cfg['smtp_host'], 'mail.mailers.smtp.port' => (int) ($cfg['smtp_port'] ?? 587),
                'mail.mailers.smtp.username' => $cfg['smtp_user'] ?? null,
                'mail.mailers.smtp.password' => ! empty($cfg['smtp_pass']) ? Crypt::decryptString($cfg['smtp_pass']) : null,
                'mail.mailers.smtp.scheme' => ($cfg['smtp_tls'] ?? '1') === '1' && (int) ($cfg['smtp_port'] ?? 587) === 465 ? 'smtps' : 'smtp',
                'mail.from.address' => $cfg['smtp_from'] ?? config('mail.from.address'), 'mail.from.name' => $cfg['smtp_from_name'] ?? config('mail.from.name'),
            ]);
        } catch (\Throwable) { /* DB not ready (fresh install) */ }
    }
}
