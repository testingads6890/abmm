# ABMM Sampark - Go-live checklist (Milestone G)

Work top to bottom. Every step has a command or screen to prove it is done. Final gate: `php artisan abmm:go-live-check` (or Admin > Launch readiness) shows **no red item**.

## 1. Server (one-time)
- [ ] Ubuntu 22.04/24.04, PHP 8.3 (fpm, mysql, redis, mbstring, xml, curl, zip, gd, intl, bcmath), MySQL 8 / MariaDB 10.6+, Redis, Nginx, Supervisor, Certbot.
- [ ] Domain + HTTPS (`deploy/nginx-abmm.conf.sample`, `certbot --nginx`).
- [ ] Code uploaded to `/var/www/abmm`, `composer install --no-dev -o`, web user owns `storage` and `bootstrap/cache`.
- [ ] `.env` filled from `deploy/env.production.sample` (APP_ENV=production, APP_DEBUG=false, Redis for cache/session/queue, SMTP).
- [ ] `php artisan key:generate` (only on a fresh install), `php artisan migrate --force`, `php artisan storage:link`.
- [ ] Cron line from `deploy/crontab.txt` installed; Supervisor worker from `deploy/supervisor-abmm.conf` running.
- [ ] `php artisan config:cache && route:cache && view:cache`.

## 2. Login and payments
- [ ] Firebase project created, **Phone** sign-in enabled, Android SHA-1/SHA-256 and iOS APNs key added; `FIREBASE_PROJECT_ID` set; `TEST_OTP` and `FIREBASE_DEV_BYPASS` removed.
- [ ] Add 2 reviewer phone numbers with fixed OTP in Firebase (Authentication > Phone > test numbers) for Play/App Store reviewers - remove them after approval.
- [ ] Razorpay KYC approved; `rzp_live_` key id/secret in `.env`; webhook `https://DOMAIN/webhooks/razorpay` with `payment.captured`, `payment.failed`, `refund.processed`; secret in `RAZORPAY_WEBHOOK_SECRET`.
- [ ] One real Rs 1 payment made and refunded; receipt PDF received by e-mail.
- [ ] International: Stripe live keys + webhook (if collecting abroad); per-country gateway set in Geography > Countries.
- [ ] Push (FCM service account), WhatsApp Cloud API templates approved, SMS gateway (optional).

## 3. Data
- [ ] `php artisan abmm:demo-data --purge` (removes all demo records and demo logins).
- [ ] Old system exported to CSV (UTF-8): families.csv, members.csv.
- [ ] `php artisan abmm:import-check families.csv members.csv` -> read the skipped rows, fix the CSV, repeat until **skipped = 0** (dry run, nothing is saved).
- [ ] `php artisan abmm:import-check families.csv members.csv --commit` -> then `php artisan abmm:import-check --audit`.
- [ ] Audit numbers reviewed: duplicate mobiles, families without a head, member-count mismatch, families without Pradesh/city (these are invisible to state sub-admins).
- [ ] Geography filled: countries, pradesh, districts, cities, zones; sub-admins created per state / country / city.
- [ ] Masters (gotra, profession, ...), plans and prices, ad rates, coupons, point rules reviewed.
- [ ] Privacy policy, terms, refund policy pages published (Content > Pages) - needed by the stores and Razorpay.

## 4. Safety net
- [ ] `php artisan abmm:backup` works; backups copied off-server daily; **a restore tested on staging** (`deploy/restore-backup.md`).
- [ ] Uptime monitor (UptimeRobot / Better Stack) on `https://DOMAIN` and a Laravel log alert; server disk alert at 80%.
- [ ] Super admin password changed, 2 super admins exist, every sub-admin has only the permissions they need.

## 5. Launch day
- [ ] UAT sign-off (`docs/UAT-TEST-PLAN.md`) by the Mahasabha.
- [ ] Admin training done (`docs/ADMIN-TRAINING.md`) for each country/state team.
- [ ] Soft launch: 20-50 members per state for one week, fix issues, then announce by WhatsApp/push.
- [ ] Support contact + escalation path published in the app (Contact page).
