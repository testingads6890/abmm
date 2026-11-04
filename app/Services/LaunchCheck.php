<?php

namespace App\Services;

use Illuminate\Support\Facades\{Cache, DB, Schema, Storage};

/**
 * Milestone G: pre-launch / production health checks. Each check is [name, status(ok|warn|fail), detail, how to fix].
 * Used by `php artisan abmm:go-live-check` and the admin page System > Launch readiness (super admin only).
 */
class LaunchCheck
{
    public function run(): array
    {
        $prod = app()->environment('production');
        $c = [];
        $add = function (string $name, string $st, string $detail, string $fix = '') use (&$c) { $c[] = ['name' => $name, 'status' => $st, 'detail' => $detail, 'fix' => $fix]; };

        $add('Environment', $prod ? 'ok' : 'warn', 'APP_ENV=' . app()->environment(), 'Set APP_ENV=production on the live server.');
        $add('Debug mode', config('app.debug') ? 'fail' : 'ok', config('app.debug') ? 'APP_DEBUG=true exposes errors and secrets' : 'off', 'Set APP_DEBUG=false.');
        $add('HTTPS app URL', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', (string) config('app.url'), 'Set APP_URL=https://your-domain and force HTTPS in the web server.');
        $add('App key', config('app.key') ? 'ok' : 'fail', config('app.key') ? 'set' : 'missing', 'php artisan key:generate (only once, before any data is stored).');

        try { DB::select('select 1'); $add('Database', 'ok', DB::connection()->getDriverName() . ' connected'); }
        catch (\Throwable $e) { $add('Database', 'fail', 'cannot connect', 'Check DB_* values in .env.'); }
        $add('Database engine', DB::connection()->getDriverName() === 'mysql' ? 'ok' : 'warn', DB::connection()->getDriverName(), 'Production should run MySQL / MariaDB.');
        try { $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()->diff(DB::table('migrations')->pluck('migration'))->count(); $add('Migrations', $pending ? 'fail' : 'ok', $pending ? "$pending pending" : 'up to date', 'php artisan migrate --force'); }
        catch (\Throwable $e) { $add('Migrations', 'fail', 'cannot read', 'php artisan migrate --force'); }

        $otp = config('abmm.test_otp');
        $add('Test OTP switched off', $otp ? 'fail' : 'ok', $otp ? 'TEST_OTP is set - anyone can log in with it' : 'empty', 'Remove TEST_OTP from .env.');
        $add('Firebase Phone Auth', config('abmm.firebase.project_id') ? 'ok' : 'fail', config('abmm.firebase.project_id') ?: 'FIREBASE_PROJECT_ID missing', 'Set FIREBASE_PROJECT_ID (Firebase console > Project settings).');
        $add('Firebase dev bypass off', config('abmm.firebase.dev_bypass') ? 'fail' : 'ok', config('abmm.firebase.dev_bypass') ? 'FIREBASE_DEV_BYPASS is on' : 'off', 'Remove FIREBASE_DEV_BYPASS.');

        $kid = (string) config('abmm.razorpay.key_id');
        $add('Razorpay live keys', str_starts_with($kid, 'rzp_live_') ? 'ok' : ($kid ? 'warn' : 'fail'), $kid ? (str_starts_with($kid, 'rzp_live_') ? 'live key in use' : 'TEST key in use (rzp_test_)') : 'no key', 'Put the rzp_live_ key id + secret in .env after Razorpay KYC is approved.');
        $add('Razorpay webhook secret', config('abmm.razorpay.webhook_secret') ? 'ok' : 'warn', config('abmm.razorpay.webhook_secret') ? 'set' : 'missing', 'Create the webhook in Razorpay (payment.captured, refund.processed) and set RAZORPAY_WEBHOOK_SECRET.');
        $add('Stripe (international)', config('abmm.stripe.secret_key') ? 'ok' : 'warn', config('abmm.stripe.secret_key') ? 'configured' : 'not configured - overseas members cannot pay by card', 'Set STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET if you collect payments abroad.');

        $q = config('queue.default'); $cache = config('cache.default'); $sess = config('session.driver');
        $add('Queue driver', $q === 'redis' ? 'ok' : ($q === 'sync' ? 'fail' : 'warn'), (string) $q, 'Use QUEUE_CONNECTION=redis with Supervisor (deploy/supervisor-abmm.conf). "sync" makes the app slow.');
        $add('Cache driver', $cache === 'redis' ? 'ok' : 'warn', (string) $cache, 'Use CACHE_STORE=redis.');
        $add('Session driver', in_array($sess, ['redis', 'database'], true) ? 'ok' : 'warn', (string) $sess, 'Use SESSION_DRIVER=redis (or database).');
        $mail = config('mail.default'); $add('Mail', in_array($mail, ['log', 'array'], true) ? 'fail' : 'ok', (string) $mail, 'Configure SMTP (MAIL_*) so receipts and OTP-less notices are e-mailed.');

        $beat = Cache::get('abmm.scheduler.beat');
        $add('Scheduler (cron)', $beat && now()->timestamp - $beat < 180 ? 'ok' : 'fail', $beat ? 'last beat ' . now()->diffForHumans(\Carbon\Carbon::createFromTimestamp($beat), true) . ' ago' : 'no heartbeat yet', 'Add the cron line from deploy/crontab.txt (runs schedule:run every minute). Wait 2 minutes and re-check.');
        $add('Queue worker', $q === 'sync' ? 'warn' : (Cache::get('abmm.worker.beat') && now()->timestamp - Cache::get('abmm.worker.beat') < 600 ? 'ok' : 'warn'), 'worker heartbeat ' . (Cache::get('abmm.worker.beat') ? 'seen' : 'not seen'), 'Start Supervisor (deploy/supervisor-abmm.conf). The heartbeat appears after the first queued job.');

        $link = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $add('Storage link', $link ? 'ok' : 'warn', $link ? 'present' : 'missing', 'php artisan storage:link');
        $add('Storage writable', is_writable(storage_path()) ? 'ok' : 'fail', storage_path(), 'chown/chmod storage and bootstrap/cache for the web user.');
        $add('Config cached', app()->configurationIsCached() ? 'ok' : 'warn', app()->configurationIsCached() ? 'yes' : 'no', 'php artisan config:cache && route:cache && view:cache');
        $add('Compiled CSS', file_exists(public_path('css/tw.css')) ? 'ok' : 'warn', file_exists(public_path('css/tw.css')) ? 'public/css/tw.css present (no Tailwind CDN)' : 'falling back to the Tailwind CDN', 'Upload public/css/tw.css or run: npm i && npm run build:css');

        $last = collect(glob(storage_path('app/backups/*')) ?: [])->map(fn ($f) => filemtime($f))->max();
        $add('Database backup', $last && now()->timestamp - $last < 36 * 3600 ? 'ok' : 'fail', $last ? 'last backup ' . \Carbon\Carbon::createFromTimestamp($last)->diffForHumans() : 'no backup found', 'Scheduled daily at 03:30 (abmm:backup). Copy backups off the server too.');
        $add('Push notifications (FCM)', config('services.fcm.credentials') || env('FCM_SERVICE_ACCOUNT') || env('FCM_SERVER_KEY') ? 'ok' : 'warn', 'checked via env', 'Add the Firebase service account JSON path (see README) to send app push.');
        $add('WhatsApp / SMS', (config('abmm.whatsapp.token') || config('abmm.sms.url')) ? 'ok' : 'warn', (config('abmm.whatsapp.token') ? 'WhatsApp ' : '') . (config('abmm.sms.url') ? 'SMS' : '') ?: 'none configured', 'Optional: configure WhatsApp Cloud API / SMS for notices and receipts.');

        $ios = config('abmm.ios.mode');
        $add('iPhone payments (Apple rule 3.1.1)', $ios === 'off' ? 'warn' : 'ok', "mode: $ios", 'Keep IOS_DIGITAL_MODE=external (buy on website) or iap (StoreKit). "off" risks App Store rejection.');
        if ($ios === 'iap') {
            $add('Apple IAP setup', config('abmm.ios.bundle_id') && is_file((string) config('abmm.ios.root_ca')) ? 'ok' : 'fail', 'bundle ' . (config('abmm.ios.bundle_id') ?: 'missing') . ', root CA ' . (is_file((string) config('abmm.ios.root_ca')) ? 'present' : 'missing'), 'Set IOS_BUNDLE_ID and put Apple Root CA G3 (PEM) at APPLE_ROOT_CA_PATH - see docs/IOS-PAYMENTS.md.');
            $add('Apple sandbox off', config('abmm.ios.allow_sandbox') && $prod ? 'fail' : 'ok', config('abmm.ios.allow_sandbox') ? 'IOS_ALLOW_SANDBOX is on' : 'off', 'Turn IOS_ALLOW_SANDBOX off on the live server.');
        }
        $users = DB::table('users')->where('role', 'super_admin')->count();
        $add('Super admin account', $users ? 'ok' : 'fail', "$users super admin(s)", 'Create one with: php artisan tinker or the setup guide.');
        $demo = Schema::hasTable('demo_records') && DB::table('demo_records')->exists();
        $add('Demo data removed', $demo ? ($prod ? 'fail' : 'warn') : 'ok', $demo ? 'demo records still present' : 'clean', 'php artisan abmm:demo-data --purge BEFORE the real import.');
        $add('Default passwords', DB::table('users')->where('email', 'like', '%@demo.abmm.test')->exists() ? 'fail' : 'ok', 'demo logins ' . (DB::table('users')->where('email', 'like', '%@demo.abmm.test')->exists() ? 'exist' : 'none'), 'Purge demo data.');
        return $c;
    }

    public static function summary(array $checks): array
    {
        $n = fn ($s) => count(array_filter($checks, fn ($c) => $c['status'] === $s));
        return ['ok' => $n('ok'), 'warn' => $n('warn'), 'fail' => $n('fail'), 'ready' => $n('fail') === 0];
    }
}
