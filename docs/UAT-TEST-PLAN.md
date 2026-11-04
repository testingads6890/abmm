# User acceptance test plan (run on the live Laravel + MySQL setup)

The automated suite (900+ checks) passes on SQLite with the manual gateway. This plan covers what only the real environment can prove: MySQL behaviour, real OTP, real payment gateway, push, e-mail, PDF fonts. Mark each line Pass/Fail with date and tester. Use the demo data on a **staging** copy (`php artisan abmm:demo-data`); never on production.

## A. Environment
1. `php artisan abmm:go-live-check` -> no red items. 2. `php artisan test`-style smoke: open every sidebar item as Super Admin - no 500 errors (check `storage/logs`). 3. Run `php artisan migrate:fresh --force` on an empty MySQL staging DB once - proves all migrations work on MySQL.

## B. Roles (log in as each; confirm you see only your area)
| Role | Must see | Must NOT see |
|---|---|---|
| Super Admin | everything, Launch readiness | - |
| Country Admin | own country data in every module | other countries |
| State Sub-Admin | own Pradesh data in every module (families, businesses, events, jobs, blood, donations, exams, Housie winners ...) | other states; admin alerts of other states |
| City / District Operator | own city/district | other cities |
| Member (app) | own family, directories (respecting privacy), modules | any admin screen (403) |

Check at least: Families, Matrimony, Businesses, Jobs, Events + tickets, Donations/campaigns, Blood requests, Investments, Academy, Exams, Housie, Ads, Payments, Reports/exports. Try opening another state's record by URL id -> must be 404.

## C. Member journeys (real phone, real OTP)
1. Register as Head of Family -> OTP -> pending approval -> admin approves -> member logs in. 2. Add a family member; admin approves. 3. Edit profile + photo. 4. Directory search; hide my number. 5. Subscribe to a plan with coupon -> pay (Razorpay live Rs 1 plan or test mode) -> receipt PDF mail + in-app. 6. Refund from admin -> subscription ends. 7. Delete my account -> logged out, admin sees request, restore works, erase works.
8. Matrimony: create profile, interest, AI match score, Parichay biodata PDF. 9. Jobs: post (paid/featured), apply, alerts. 10. Events: RSVP, buy ticket, QR scan at gate with staff login, duplicate scan rejected.
11. Blood: register donor, raise emergency request -> nearby donors notified. 12. Donation to campaign (80G PDF with PAN), anonymous donation. 13. Investments: interest, pitch with deck upload.
14. Academy: free preview, buy course, progress, certificate, live class join, library. 15. Exam: set segment, take weekly exam, instant score, rank after close, certificate. 16. Housie: buy ticket, watch numbers, claim line/full house, wrong claim rejected.

## D. Scheduler and background (watch for 24 hours)
Greetings 07:00, festivals, subscriptions reminders, reconcile payments hourly, weekly exam created Monday, class reminders, Housie starts on time and calls every N seconds, backups 03:30, account erasure 04:10. Check `storage/logs` and the Launch readiness page (scheduler heartbeat, worker heartbeat).

## E. Load and security
1. 200 concurrent users on login + home + directory (k6 / Apache bench) - p95 under 800 ms. 2. Housie with 500 players polling every 3 s - numbers stay in order, no duplicates. 3. Try SQL/XSS strings in forms; upload a .php renamed .jpg (must be rejected). 4. Rate limits: 25 wrong OTPs from one IP gets throttled. 5. Webhook with a wrong signature -> rejected.

## F. Sign-off
Defects logged with severity. Go-live allowed when: no Sev-1/2 open, every Section B row passes, payments + OTP verified live, restore test done.
