# ABMM Sampark: project handover (updated)

A short, AI-friendly guide to this project. Keep it next to the code so any assistant or developer can pick up the work. It does not contain passwords or keys. Keep those in `.env` only.

---

## 1. What the project is

ABMM Sampark is the admin panel, member app API and staff app API for Akhil Bharatvarshiya Maheshwari Mahasabha. It manages families and members, reports, donations, community services and engagement features.

- **Users:** Super Admin, State Sub-Admin (area = Pradesh), District Operator (area = Pradesh/District/Tehsil), plus member accounts in the Flutter app.
- **Public site:** login, donation pages, card verification, delete-account page.
- **Apps:** a Flutter staff app (uses `/api/v1`) and a Flutter member app (uses `/api/v1/m`). The Flutter apps are not in this repo.

## 2. Stack

- PHP 8.2+ and Laravel 13 (routes use Laravel 11+ style `bootstrap/app.php`).
- MySQL (`DB_CONNECTION=mysql`). Cache and queue use the database or Redis.
- Blade views. Styling is `public/css/abmm.css` (hand-written) plus `public/css/tw.css` (compiled Tailwind). No build step is needed to run the site.
- Small JavaScript files in `public/js/` (no framework).
- Excel export is written by hand (`App\Support\XlsxWriter`). PDF uses mPDF (`composer require mpdf/mpdf`).
- Firebase push, Razorpay payments, SMTP email, WhatsApp/SMS templates are configured through `.env` and the Settings page.

## 3. Folder map (where things live)

| Area | Path |
|---|---|
| Routes (web, admin, API) | `routes/web.php` (the active file; `global.php` is loaded from it). `lweb.php`, `oldweb.php`, `lengage.php` etc. are older copies, not loaded. |
| Admin controllers | `app/Http/Controllers/Admin/` |
| Staff API (Flutter staff app) | `app/Http/Controllers/Api/V1/` |
| Member API (Flutter member app) | `app/Http/Controllers/Api/Member/` |
| Models | `app/Models/` (`Family`, `Member`, `Lookup` = master data, `Setting` = key/value settings, `User`) |
| Shared logic | `app/Support/` (`Exporter`, `Theme`, `Area`, `ManageResources`, `CustomForm`) |
| Services | `app/Services/` (import, FCM, Razorpay, reports, etc.) |
| Admin views | `resources/views/admin/` |
| Layouts | `resources/views/layouts/admin.blade.php` (admin), `layouts/public.blade.php` (public) |
| Permissions and roles | `config/permissions.php` (defaults for new users) |
| Master data import files | `database/data/anchal_pradesh.json`, `database/data/india_geo.json` |
| Static assets | `public/css/abmm.css`, `public/js/abmm-ms.js`, `public/js/abmm-dropdowns.js`, `public/images/abmm-logo.jpg` |
| Checks (tests) | `tests/unit/` (see section 9) |

Older copies such as `resources/views/admin.6554/`, `layouts.8672/`, `public.8732/` are leftovers. Do not edit them. The active code is in `resources/views/` without a number suffix.

## 4. Setup and running

```bash
composer install            # PHP dependencies (vendor/ is already present on the server)
cp .env.example .env        # then set DB_* and APP_URL
php artisan key:generate    # only on a fresh install
php artisan migrate         # schema
php artisan storage:link    # uploads
php artisan optimize:clear
```

On the current server `APP_URL=https://abmm.khobargarden.com/public`, so every URL and asset is under `/public`. Files in `public/` must be world-readable (`644`, folders `755`), or the web server returns 403/404. This caused the logo to fail once.

Scheduler (one cron line):
```
* * * * * php /path/to/artisan schedule:run
```

Queue worker (for push, email, WhatsApp): `php artisan queue:work` under Supervisor (see `deploy/supervisor-abmm.conf`).

## 5. Roles and access

- `super_admin`: everything.
- `state_admin` (State Sub-Admin): limited to their Pradesh. Default permissions are in `config/permissions.php`, under `'state_admin'`. Some modules are admin-only: Settings, Payments, Plans, Revenue, Translations, System, Import, Team, Forms.
- `district_operator`: narrower; limited to their Pradesh/District/Tehsil.
- Permission key format: `module.action` (for example `families.export`). Check with `$user->can('key')`.
- Area scope is enforced by the `HasArea` trait on `Family` and `Member`. A non-super user with no area sees nothing.
- Sub-admins must change their initial password on first login (`must_change_password`).
- Permissions are stored per user in the database. Changing `config/permissions.php` only affects **new** users. To change existing users, update their `permissions` column (see section 10).

## 6. Main features (admin panel)

- **Member (families):** search with advanced filters (text, surname, letter, Anchal, Pradesh, state, district, city, tehsil, gender, blood group, occupation, profession, education, business type, marital status, gotra, caste, income, resident type, age range, birth month, registration dates, mediclaim, Pakshik, status). CSV download of all matching records (`families.export`). Add/edit family, add/edit members, print, family tree.
- **Location cascade (family form):** Country → State → District → Tehsil. Picking a tehsil fills ABMM Pradesh, and Pradesh fills Anchal. Data comes from `Lookup` (types `state`, `district`, `tehsil`, `pradesh`, `anchal`, `country`).
- **Reports (`/admin/reports/{type}`):** Member, Family, Hanging Data (`moved`), No. of family member wise (`size`), Committee, Business, Business ads, Business reviews, Advertisement payment. Each has filters, CSV, Excel and PDF.
- **Hanging Data:** lists members whose head changed, families with no address, and married daughters (address check). Super Admin and State Sub-Admin can Migrate a member to another Family ID, or mark a member as Died (archive).
- **Master data:** dropdown lists (`Lookup`). Caste/Gotra, Country codes, Anchal, Pradesh, state/district/tehsil.
- **Settings:** organisation name, contact details, SMS and email settings, policy pages, offices, and the **theme colours** (Primary and Secondary). The colours apply to every admin and public page through `App\Support\Theme::css()`.
- **Engagement, Community, Commerce, Partner, Careers, Blood, Giving, Investments, Academy, Exams, Housie, Parichay, Matrimony, Jobs, Directory, Events, Polls, Points** (many are hidden from the sidebar, see section 8).
- **Data import:** Members CSV import (`/admin/members/import`), Old data import (`/admin/import`).

## 7. API

### Staff API (`/api/v1`, Bearer token from `POST /api/v1/auth/login`)
- `auth/login`, `auth/logout`, `me`, `me/fcm`
- `dashboard`
- `families` (search, needs a term of 3+ characters or a filter), `families/{id}`, `families/{id}/members`
- `families/{id}/members/{mid}/archive` (Died etc.), `families/{id}/members/{mid}/migrate` (Super/State Admin)
- `reports/{type}` (JSON, same filters as the admin report pages)
- `masters`, `masters/{type}`
- `geo`, `geo/pincode/{pin}`
- `team` (list, create, update, toggle, reset password, catalogue)
- `scan/events` (QR check-in)

### Member API (`/api/v1/m`, public or Bearer token)
- Public: `config` (organisation name, theme colours, logo URL), `masters`, `locations`, `pages/{slug}`, `auth/verify`, `auth/register/head`, `auth/register/member`
- Logged in: family, members, businesses, matrimony, jobs, donations, Mahesh Vandana, feed, events, polls, points, cards, blood, careers, academy, exams, housie, and more (about 380 routes in total). Run `php artisan route:list --path=api` for the full list.

Conventions: JSON responses, cursor pagination on the staff search, rate limits in `routes/web.php`.

## 8. Sidebar: hidden for now (commented out, not deleted)

In `resources/views/layouts/admin.blade.php` these are wrapped in `{{-- --}}`. To show one again, remove the markers on its line or block.

- Bhavan, hotel, hospital; Business, ads & B2B; Tickets & online events; Event vendors; Smart search (AI); Parichay safety; Partner dashboards; Careers & mentoring; Blood donors; Donations & relief; Investments; Launch readiness; Learning Academy; Weekly exams; Housie
- Polls & Surveys; Event RSVP & Attendance; Points & Leaderboard; Rewards; Certificates
- My Meetings/Events; Mahesh Vandana; Jobs Board; Plans & Coupons (Subscribers and iOS products stay visible)

"Purana data import" was renamed to **Old data import**.

## 9. Tests and checks

No PHPUnit is installed (adding it changes `composer.json`/`composer.lock`). The checks are plain PHP and Bun scripts in `tests/unit/`. Run them all from the project root:

```bash
bash tests/unit/run-all.sh
```

It prints one PASS/FAIL line per check. Current checks:
- Family form cascade (Bun, runs the real form script against a DOM stub; needs `php tests/unit/render-family-form.php` first, which `run-all.sh` does)
- Exports: headers and row include Anchal
- Family search and report filters return only matching rows
- Hanging Data rules
- Roles and permissions
- Sub-admin area limits
- Staff API endpoints
- Every admin page loads for Super Admin, State Sub-Admin and District Operator with no server errors

All checks are read-only. The smoke test bypasses the forced password change in memory only, and nothing is saved.

**Not covered by checks:** real browser behaviour (layout, dropdowns, responsive design), Migrate/Died buttons, email/SMS/push delivery, payments, the member app API over real HTTP with tokens.

## 10. Maintenance commands and data

```bash
php artisan abmm:import-geo          # India states, districts, tehsils (database/data/india_geo.json). Safe to re-run.
php artisan abmm:import-anchal       # Anchal and ABMM Pradesh (database/data/anchal_pradesh.json). Safe to re-run.
php artisan abmm:demo-data           # demo records (do not run on live data)
php artisan abmm:backup              # database dump
php artisan abmm:go-live-check       # launch readiness report
```

Updating a user's permissions (existing users, not defaults): set the `permissions` array on each user, for example in a script that merges keys and saves. Do not remove keys without checking which users rely on them.

Known data facts:
- Master data has 36 states, 785 districts with tehsils (from the LGD open data, subdistricts used as tehsils), and 62 ABMM Pradesh rows. Every state is linked to a Pradesh.
- Some family records store state or Pradesh as short codes (`GJ`, `MH`) or plain names (`Rajasthan`). Those records do not match the master names, so Anchal filters miss them.
- One member's gender is stored as `MALE` (the filters still find it).
- Pradesh-list spreadsheets sent in the project were empty (only a web-page wrapper). The real list is needed to load Pradesh names directly.

## 11. Theme and logo

- Logo: `public/images/abmm-logo.jpg` (must be `644`). Used in the admin header, login, public header, favicon, print header, card and toppers.
- Colours: Admin → Settings → Theme colours (Primary, Secondary). The shades are derived in `App\Support\Theme`. Default primary is logo red `#c62828`, secondary is maroon `#5c1010`.
- The print documents (`admin/print/doc.blade.php`) use the default colours through CSS variables only if the theme block is included there. Check this if print colours look wrong.

## 12. Known gaps and next steps

1. Browser testing of all screens (desktop, tablet, mobile). Not done.
2. Migrate and Died buttons: test on a test family before live use.
3. Hanging Data rules (head changed, address missing, married daughter) should be confirmed with the business owner. The "married daughter" rule is an assumption.
4. State and district master data should be checked against the official list; some tehsils in LGD are subdistricts and may not match local usage.
5. Member API and staff API: end-to-end tests with real tokens.
6. Flutter apps: use `GET /api/v1/m/config` for theme colours and the logo, instead of hard-coding them.
7. Sidebar items hidden in section 8 may be re-enabled one by one after review.
8. Gender and other stored values should be normalised (`Male` / `Female` only).

## 13. Things to never commit or paste into chat

- `.env` (database, mail, Razorpay, Firebase, SMS keys)
- `storage/app/private/firebase-service-account.json`
- Any super admin or demo passwords. Change the seeded default `ChangeMe@12345` before going live.

## 14. Working notes for AI assistants

- Read this file first, then `routes/web.php`, then the controller for the feature.
- Make small changes and run `bash tests/unit/run-all.sh` afterwards.
- Blade view edits: check the file compiles with `Blade::compileString` before saving (see the checks).
- Do not edit the numbered copies (`admin.6554`, `layouts.8672`, `public.8732`).
- Prefer changing `Lookup` master data over hard-coding lists.
- Keep the admin and API permission checks in step: a route `middleware('can:...')` and a menu `@can(...)` must use the same key.
- Use `$request->user()` or `auth()->user()` for the logged-in user; never trust a user id from the request.
