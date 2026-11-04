# ABMM Sampark — Laravel 13 + MySQL (overlay)

Yeh ek **overlay** hai: fresh Laravel 13 project par copy karna hai (PHP 8.3+, Composer, MySQL 8).

## Setup
```bash
composer create-project laravel/laravel:^13.0 abmm-sampark
cd abmm-sampark
# is zip ke andar ki sab files/folders project root me copy-paste karein (merge, overwrite: app/Models/User.php, routes/web.php, database/seeders/DatabaseSeeder.php)
# .env me DB set karein:
#   DB_CONNECTION=mysql  DB_DATABASE=abmm_sampark  DB_USERNAME=...  DB_PASSWORD=...
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
- Public site: `/`   · Login: `/login`   · Admin: `/admin`
- Seed admin: `admin@abmm.test` / `ChangeMe@12345` — **turant badlein** (Settings → Change password).

## Original site ke saath module-wise parity (abmmsampark.org)

**Header:** menu collapse · page title · Donate Now (modal: amount, receipt name, family id, email, mobile, city, PAN optional, 2 agree checkboxes → Razorpay) · language English/हिन्दी · notification bell (unread count, Mark all as read, Read all) · display panel (White/Dark mode, Menu Collapse, Hide/Show Image) · user menu (Profile, Desktop Notification on/off, Feedback, Change Password modal, Logout).

**Sidebar (original order):** Dashboard · Member · Upload Committee Member Data · Reports ▸ Member, Family, Hanging Data, No. of family member wise, Committee Member Report, Mangal Parinay, Archive Mangal Parinay, Business Advertise, Business, Business Reviews, Advertisement Payment · Notification · My Meetings/Events · Mahesh Vandana. Neeche "Admin tools": Businesses, Reviews, Advertisements, Public Events, Donations, Contact messages, Purana data import, Settings.

**Footer:** © Copyright · Privacy Policy · Term & Condition · Contact Us · Refund Policy · Powered By · floating Donate Now.

**Modules**
- *Dashboard:* committee card (Active, name, Sabhapati, Operational Year), Total Members / Total Family(s), committee counts Country/ABMM Pradesh/State/District, Last families, charts, activity.
- *Member (Data Bank):* Add New Family; filters Tehsil, Blood Group, Business Type, Education, Gender, Occupation, Profession, Surname, ABMM Pradesh Availability, name/mobile; entries 10/25/50/100; View / Edit / Print family card; family members add + **move to Hanging Data** (Died, Married, Migrated, Naya Chulha, Wrong Entry) + restore; head of family kept as a member row (Member ID per person); Aadhar encrypted; "Update surname of all members".
- *Upload Committee Member Data:* designation list, add form, CSV upload, search, deactivate.
- *Reports:* Member (individual rows) & Family with all filters (Tehsil, Blood Group, Business Type, Education, Gender, Occupation, Profession, Surname, Marital Status, Age Group, Alphabetical, From/To age, State, Income, Resident Type, Mediclaim, Pakshik, dates, name/mobile); Hanging Data (+Archive Type); family-size tiles 1–25 with drill-down + Excel; Committee report (Tehsil + Designation with And/Or); Mangal Parinay & Archive (Qualification, Degree, Gender, Occupation Type, Occupation, Surname, Age group, A–Z, Status, dates; Parinay/Family/Member/Payment id, days); Business (Member Id, Family ID, Status, dates); Business Advertise (Approved/Pending/Rejected); Business Reviews; Advertisement Payment. Print / PDF / Excel(CSV, max 1 lakh); inactive/archived rows excluded from exports.
- *Notification:* Country / Region(Pradesh) / State / District / Tehsil targets, Notice or Meeting (M), Seen / Not Seen counts, per-family recipient list, resend, delete, date + tehsil search, print.
- *My Meetings/Events:* meeting notifications with date, time, venue, sent by.
- *Mahesh Vandana:* audio play/pause/mute/seek/download + image download; admin uploads.
- *Profile:* details + login/logout activity. *Feedback:* submit + list/filter. *Settings:* organisation, SMTP (Host, Port, User, Password, From, From Name, Reply, TLS), policy pages, Contact Us offices, master lists, users/roles. Operators can be limited to a Pradesh/District (users.pradesh / users.district).

**Mobile app endpoints** (header `X-App-Key` = `APP_API_KEY`): `POST /app/topics` {family_code, mobile} → FCM topics to subscribe; `POST /app/notifications/{id}/seen` → feeds Seen/Not Seen.

## Excel / PDF / row actions
- **Excel:** asli `.xlsx` (bold frozen header, filter, auto widths, Hindi OK), bina kisi package ke. 1 lakh rows ~3 sec me, kam memory me. Yahin LibreOffice se open karke verify kiya.
- **PDF:** `composer require mpdf/mpdf` ek baar chalayein. A4 landscape, org header, page numbers, repeat header row; Hindi ke liye mpdf auto-font. PDF me max 3000 rows (zyada ke liye Excel). Package na ho to saaf error aata hai.
- Dono sab reports, Family-size tiles, Notification list aur Committee list par hain; inactive Member/Family downloads me nahi aate.
- **Row actions (original jaise):** Family report: View · Print · Family Tree · De-Activate. Member report: View · Print · De-Activate. Hanging Data: View · Print. Mangal Parinay: View · Print · Activate · Moved (Unarchive in archive). Business: View · Print. Advertise: View · Print. Reviews: Approve / Deny. Committee: Edit · Delete. Notification: Message Seen Status · View · Print. View/Print naye popup-tab me khulte hain; Family Tree ek peedhi-wise chart hai.

## Firebase push notifications (SMS ki jagah)
1. Firebase Console → Project settings → Service accounts → "Generate new private key" → JSON ko `storage/app/private/firebase-service-account.json` me rakhein (kabhi git/public me nahi).
2. `.env` me `FCM_PROJECT_ID` aur `FCM_CREDENTIALS` (dekhein `env-additions.txt`).
3. `QUEUE_CONNECTION=database`, `php artisan queue:table && php artisan migrate`, aur server par `php artisan queue:work` chalta rahe.
4. **Mobile app me** login ke baad user ke hisaab se FCM topics subscribe karein: `all`, `pradesh_<slug>`, `state_<slug>`, `district_<slug>`, `tehsil_<slug>`
   (slug = Laravel `Str::slug`, jaise `district_jaipur`, `pradesh_purvottar-rajasthan-pradesh-maheshwari-sabha`). Admin ka notification isi topic par jaata hai.
5. Admin → Notifications → "Push bhejein". Status (queued/sent/failed) list me dikhta hai.

## Razorpay (test mode)
1. `.env` me `RAZORPAY_KEY_ID=rzp_test_...`, `RAZORPAY_KEY_SECRET`.
2. Flow: `/donate` → server par Order banta hai → Razorpay Checkout → `/donate/verify` signature check karke hi "success" mark hota hai.
3. Webhook (backup): Razorpay Dashboard → Webhooks → URL `https://<domain>/razorpay/webhook`, events `payment.captured`, `payment.failed`, secret = `RAZORPAY_WEBHOOK_SECRET`.
4. Test card: 4111 1111 1111 1111. Live jaane se pehle sirf keys badalni hain.

## Purana data import
Admin → "Purana data import" (dry-run ke saath) ya `php artisan abmm:import families file.csv --dry-run` / `members`. Headers loosely match hote hain; dobara chalane par update hota hai, duplicate nahi.

## Baaki
Mobile app (FCM topic subscribe + API), Tailwind CDN → Vite build, aur purana data ka asli CSV export.

## Laravel 13 CSRF note
Laravel 13 renamed the CSRF middleware to `PreventRequestForgery`. The webhook and `/app/*` routes already opt out via `withoutMiddleware(...)`. If your install still rejects them (419), add in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->preventRequestForgery(except: ['razorpay/webhook', 'app/*']);
})
```

## Roles, permissions & team (Super Admin → State Sub-Admin → District Operator)
- Roles/permissions catalogue: `config/permissions.php` (permission key = `module.action`). Super Admin has everything.
- A creator can only grant permissions he/she holds, and only roles below own (`can_create`). State Sub-Admin can create District Operators **only inside own Pradesh**.
- Data is area-locked automatically (`HasArea` trait; by default only Family & Member, as on the real site where matrimony/committee/business lists are national for sub-admins — switch per model in `config/permissions.php` → `area_scoped`): Pradesh / District / Tehsil of the user. A non-super user with **no area sees nothing** (fail-closed) — after upgrading, open *Team* and assign an area to old users (old `admin` became `state_admin`, old `operator` became `district_operator`).
- Team page: `/admin/team`. Disabling a user or resetting the password logs the mobile app out immediately.
- Demo logins after `php artisan migrate --seed`: `admin@abmm.test`, `state@abmm.test`, `operator@abmm.test` (all `ChangeMe@12345`). Delete the demo ones before go-live.

## Staff mobile API v1 (for the Flutter app)
`POST /api/v1/auth/login` {login (mobile/email), password, device_name, fcm_token} → `{token, user{role, area, permissions[]}}`; then send `Authorization: Bearer <token>`.
`GET /me`, `POST /me/fcm`, `POST /auth/logout`, `GET /dashboard` (cached 10 min), `GET /families?q=&tehsil=…` (cursor pagination; needs ≥3 letters or a filter),
`GET /families/{id}`, `GET /team/catalogue`, `GET|POST /team`, `PUT /team/{id}`, `PATCH /team/{id}/toggle`, `POST /team/{id}/reset-password`.
Scale notes (10 lakh+ families): composite indexes in migration `…000004`, cursor pagination (no OFFSET/COUNT), cached dashboard counts, hashed expiring tokens. For production use Redis for cache/queue/session and run `php artisan queue:work` under Supervisor.

## 37 state / UT sub-admins (common initial password)

`php artisan migrate:fresh --seed` also creates one **State Sub-Admin per Indian state/UT** (28 states + 9 UTs = 37): login `<state-slug>@abmm.test`
(e.g. `rajasthan@abmm.test`, `tamil-nadu@abmm.test`), all with the same initial password. Set in `.env` before seeding:

    SEED_STATE_ADMIN_PASSWORD=choose-a-strong-one
    SEED_ADMIN_DOMAIN=yourdomain.org

(falls back to `ChangeMe@12345` / `abmm.test`). Every one of these accounts is flagged `must_change_password`: after the first
login they are sent to **Change password** and can't use the panel until they pick their own. Mobile numbers are placeholders
(97 00000001…), and each state gets a *placeholder* ABMM Pradesh (`<STATE> PRADESH MAHESHWARI SABHA`; Rajasthan uses the live
one) — rename/merge them in Master Data → ABMM Pradesh (renames cascade to users and families). Super Admin: `admin@abmm.test`.

## Feature matrix — Super Admin vs State Sub-Admin

Legend: ✔ = available by default, — = not available. Sub-admin permissions are the **defaults** in `config/permissions.php`; a creator can
tick/untick them per user in *Team → Edit* (only permissions the creator holds can be granted). Super Admin passes every permission check.

### Sidebar / pages

| Page (sidebar) | Super Admin | State Sub-Admin | Notes |
|---|---|---|---|
| Dashboard | ✔ | ✔ | counts, 12-month new families, state-wise families, last 8 registered, activity (sub-admin: own area only) |
| Member (Families) | ✔ | ✔ | list + multi-select filters, view / edit / add member / print / tree / activate-deactivate |
| Upload Committee Member Data | ✔ | ✔ | CSV/XLS(X) upload, sample file, add single, edit, delete (delete: Super Admin only) |
| Reports ▸ Member, Family, Hanging Data, No. of family member wise | ✔ | ✔ | filters + export (Print / Excel / PDF) |
| Reports ▸ Committee Member Report | ✔ | ✔ | national list |
| Reports ▸ Mangal Parinay / Archive Mangal Parinay | ✔ | ✔ (view, edit, status) | sub-admin cannot create profiles by default |
| Reports ▸ Business Advertise / Business / Business Reviews / Advertisement Payment | ✔ | ✔ (view only) | |
| Notification (send, resend, seen status) | ✔ | ✔ (send; no delete) | |
| My Meetings / Events | ✔ | ✔ | |
| Mahesh Vandana | ✔ | ✔ | |
| Businesses, Business Reviews, Advertisements (Admin tools) | ✔ full (create/edit/status/ads) | — | |
| Public Events | ✔ create/delete | — | |
| Donations, Contact messages | ✔ | — | |
| Purana data import | ✔ | — | |
| Team / Sub-Admins | ✔ creates State Sub-Admin + District Operator | — (Super Admin can tick `users.*` for a given sub-admin; then limited to own Pradesh) | |
| Master Data (all dropdown lists, add/rename/delete, pincode on Tehsil/Town) | ✔ | — | renames cascade to existing data |
| Settings (organisation, SMTP, offices, pages, activity log) | ✔ | — | |
| Change password, Profile, Feedback, language (EN/हिन्दी), bell, Privacy/Terms/Refund links | ✔ | ✔ | |

### Action-level permissions (module.action)

| Module | Super Admin | State Sub-Admin default | District Operator default |
|---|---|---|---|
| families: view, create, edit, archive, delete, export, print | all | all except **delete** | view, create, edit, print |
| committee: view, create, edit, delete, import | all | all except **delete** | — |
| matrimony: view, create, edit, status | all | view, edit, status | view, create |
| business: view, create, edit, status, ads | all | — | view, create |
| notifications: view, send, delete | all | view, send | — |
| events: view, create, delete | all | — | — |
| donations: view, status, export | all | — | — |
| reports: view, export | all | view, export | view |
| import: run | ✔ | — | — |
| users: view, create, edit, toggle, reset_password | all | — | — |
| masters: view, create, edit, delete | all | — | — |
| settings: manage | ✔ | — | — |

### Data scope
- **Super Admin:** all data, all states.
- **State Sub-Admin:** Families & Members only for own Pradesh/State (area lock, fail-closed). Committee, Mangal Parinay and Business lists are national, as on the live site (switch in `config('permissions.area_scoped')`).
- **District Operator:** locked to own District (and Tehsil if set).

### Known deviations (to review)
- State Sub-Admin sees **no Admin tools** (as on the live site): only Dashboard, Member, Committee upload, Reports, Notification, My Meetings, Mahesh Vandana, Change password. Defaults only affect newly created users; for existing DB users untick those permissions in Team → Edit.
- Notification page lacks the live SMS/Push channel, Send-To (Male/Female/Family Head) and Blood Group filters, and the vaccination/recovery checkboxes (needs device tokens → Flutter step).
- Nothing here has been run on real Laravel/MySQL yet; run `php artisan migrate:fresh --seed` and click through each role.

## Trusts & department submission (Super Admin only)
Menu: **Trusts**, **Trust Submissions**, **Departments** (permission module `trusts`, not given to State Sub-Admin by default).
- **Common fields (same for every category):** Category (dropdown), Name of Trust (dropdown, depends on Category), Name, Email ID, Date of Birth, Mobile Number, Gender (master), Marital Status (master: Married / Unmarried / Divorcee / Widow / Widower), City, Aadhar Card (stored encrypted, shown masked `XXXX XXXX 1234` in list/PDF/e-mail). Optional address block: address, country, Govt. State, district, pincode.
- **Category and Name of Trust are Master Data** (Master Data → *Trust* group: Trust Category, Name of Trust under a category) — add/rename/deactivate them from the side menu; add them first, otherwise the form dropdowns are empty.
- **Extra fields change per category:** in *Form Fields → Trust form* each custom field can be set to *All categories* or one Trust Category; the Add/Edit form shows only the fields of the chosen category (hidden ones are neither required nor saved), and the PDF/e-mail carry only that category's fields.
- Filters (category / trust / gender / marital status / state / district multi-select + search), add/edit/delete, per-record **PDF download**, list export (PDF/Excel/CSV).
- **Departments:** name, e-mail, fee (₹0 = free); mark inactive instead of deleting.
- **Submit (➤ icon):** pick a department. Fee 0 → submitted immediately. Fee > 0 → Razorpay checkout (same keys as donations); on verified payment (browser callback or webhook, idempotent) the submission is marked *Submitted* and the **details + PDF are e-mailed to the department**. A mail failure is shown in *Trust Submissions*; the ✉ icon re-sends.
- Needs: `php artisan migrate` (migrations `…000007`, `…000008`), working SMTP, `RAZORPAY_KEY_ID/SECRET` for paid departments, `composer require mpdf/mpdf` for PDF. Not yet run on real Laravel/MySQL.

## Custom form fields (Super Admin → Form Fields)
Every form can get extra fields without a code change: pick the form tab (Trust, Family, Member, Committee member, Mangal Parinay), then **Add field** — label, type (text, long text, number, date, email, phone, dropdown, yes/no tick), dropdown options (one per line), *required*, *show in list*, order, active. The field then appears in that form's Add/Edit screen automatically.
- Values are stored per record in an `extra` JSON column (migration `…000008`), keyed by an immutable machine key; switching a field **Active** off hides it but keeps data; deleting a field keeps already-saved values in the records.
- Trust: custom fields also appear as columns (if *show in list*), in the Trust PDF/list export and in the e-mail to the department. Family: shown in an "Additional details" card on the family page.
- Not yet: custom columns inside the Reports module exports, and per-department trust forms (one trust form for all departments).
- Permission module `customfields` (Super Admin only by default).

## Member (normal-user) mobile API — `/api/v1/m`
Login = mobile + OTP. **Testing:** set `TEST_OTP=123456` in `.env` → any mobile logs in with OTP `123456` (Flutter test mode sends `{mobile, otp}`). **Production:** remove `TEST_OTP`, set `FIREBASE_PROJECT_ID`; the app signs in with Firebase Phone Auth and sends `{id_token}`.

| Call | Purpose |
|---|---|
| `POST auth/verify` {mobile, otp} or {id_token} | known number → `{registered:true, token, user}`; new → `{registered:false, signup_token}` |
| `POST auth/register/head` {signup_token + 4-step fields} | creates Family + Head; returns token |
| `POST auth/register/member` {signup_token, family_code or head_mobile, relation + fields} | joins a family as *pending*; Head approves |
| `GET masters` (ETag) · `GET locations?q=` · `GET pages/{slug}` · `GET contact` | public: dropdowns, village/town search (auto district/state/pincode), legal, contact |
| `GET home` · `GET profile` · `POST profile` | home screen, My Profile, Edit Profile |
| `POST members` · `GET members/{id}` · `POST members/{id}` | Add Member (Head only), View, Update Profile |
| `POST members/{id}/remove` {archive_type, note} · `GET members/removed` · `POST members/{id}/restore` | Remove / Migrate, Removed Members |
| `POST family/head` {member_id} · `POST family/settings` | Update Head of Family, mediclaim / pakshik |
| `GET members/requests` · `POST members/{id}/requests/approve\|reject` | join requests |
| `POST members/{id}/parinay` · `GET parinay` | Mangal Parinay registration (status `registered`; admin marks `paid`) |
| `GET vandana` · `GET downloads` · `GET patrika` | Mahesh Vandana, Downloads (Follow Link), Patrika |
| `GET trusts/form?category=` · `POST trusts` · `GET trusts/mine` · `GET trusts/{id}/pdf` | Trusts: prefilled common fields + admin-defined extra fields |
| `GET|POST businesses` | Yashashwi |
| `POST donations` → Razorpay order · `POST donations/verify` · `GET donations` | Donate Now / My Donation |
| `GET referral` | Referral code, share text, referred families |
| `GET committee` · `GET meetings` · `GET notifications` · `POST notifications/{id}/seen` · `GET topics` | committee, meetings, notices, FCM topics |

Admin side: **Mobile App → Registered Families / Registered Members** list app sign-ups state-wise (summary chips + filters + export). Super Admin sees every state; a State Sub-Admin (and District Operator) only sees rows inside their own Pradesh/district (automatic via the area scope). **App Downloads** (Super Admin) edits the Downloads list. Run `php artisan migrate` (new: `…000009`, `…000010`).
Trust directory details (address / e-mail / contact / PDF link) are edited in Master Data → Name of Trust (expand a row → *App directory details* → Save). Parinay fee: `POST parinay/{id}/pay` + `POST parinay/verify` (Razorpay; default Rs. 500/year, change via setting `parinay_fee`; webhook also marks Paid; profile becomes `paid` with `paid_until` = +1 year). Admin-defined *Family* / *Member* Form Fields are returned in `masters` as `extra_family` / `extra_member` and sent as `extra` on register / add / update.
Referral: `GET referral` (code = Family ID, count + list); Head registration accepts optional `referral_code` (stored in `families.referred_by`). Run `php artisan migrate` for `…000011`.

---

## Login details (seeded by `php artisan migrate:fresh --seed`)

| Role | Login (email) | Password | Scope |
|---|---|---|---|
| Super Admin | `admin@abmm.test` | `ChangeMe@12345` | all states, all modules |
| Demo State Sub-Admin (Purvottar Rajasthan) | `state@abmm.test` | `ChangeMe@12345` | only its own state / pradesh |
| Demo District Operator | `operator@abmm.test` | `ChangeMe@12345` | only its own district |
| 37 State / UT Sub-Admins | `<state-slug>@abmm.test` e.g. `rajasthan@abmm.test` | `SEED_STATE_ADMIN_PASSWORD` (default `ChangeMe@12345`) | own state only; must change password on first login |
| Member app user (Flutter) | any mobile number | test OTP `123456` (`TEST_OTP` in `.env`) | own family |

Production: change every password, set `TEST_OTP=` empty and `FIREBASE_PROJECT_ID` so real Firebase Phone Auth OTP is used.

## Complete feature list

**Admin panel (web)** — layout matches the live panel: Dashboard, Member, Upload Committee Member Data, Reports (Member, Family, Hanging Data,
No. of family member wise, Committee Member Report, Mangal Parinay, Archive Mangal Parinay, Business Advertise, Business, Business Reviews,
Advertisement Payment), Notification, My Meetings/Events, Mahesh Vandana — plus admin tools below.

- Roles & permissions: Super Admin → State Sub-Admin → District Operator, per-user permission toggles, area-scoped data (fail-closed), 37 state sub-admins, forced password change.
- Families & Members: add/edit/view/print, family tree, head change, remove/migrate with archive type, restore, custom form fields, duplicate checks, Excel/PDF/print exports.
- Reports: all 11 live reports with filters, pagination, row actions (View / Print / Family Tree) and Excel/PDF export.
- Committee: upload committee member data, committee report, committee on dashboard and in app.
- Mangal Parinay: registration, online fee (Razorpay) or manual "Paid", archive, report.
- Business (Yashashwi): businesses, reviews, advertisements, ad payments.
- Trusts & department submissions (Super Admin), trust directory details (Master Data → Name of Trust), Razorpay payments.
- Donations (Razorpay + webhook, receipts), contact messages, public events, Mahesh Vandana, Downloads (App Downloads).
- Notifications: in-app bell, FCM push to topics (all / pradesh / state / district / tehsil), meetings/events.
- Master Data (all dropdown groups, renames cascade), Custom Form Fields, Purana data import, Settings, Feedback, Profile, language switch (EN/हिन्दी), display settings.
- Mobile App menu: Registered Families / Registered Members — **state-wise** summary, filters, export (Super Admin sees all states, State Sub-Admin only own state).

**Member mobile app (Flutter, one app: Head of Family / Member)** — OTP login (test 123456 / Firebase), Head registration (4 steps, optional referral code),
Member join request (Head approves), Home, My Profile / Edit Profile, Add / View / Update Member, Remove / Migrate & Removed Members, Update Head,
join requests, mediclaim / pakshik settings, Mangal Parinay registration + fee, Mahesh Vandana (audio), Downloads, Patrika, Trusts form + PDF + My Trusts,
Yashashwi businesses, Donate Now / My Donation (Razorpay), Referral, Committee, Meetings, Notifications + push, Contact, legal pages. Scales to ~10 lakh users (ETag masters, paginated APIs, indexed queries).

**Backend APIs** — Staff API `/api/v1` (Bearer token) and Member API `/api/v1/m` (see tables above); all member-app features have endpoints.

---

## Phase 2 — Engagement features (Laravel admin + Flutter app)

Run `php artisan migrate` (000012 greetings, 000013 daily/festivals, 000014 feed, 000015 feed push) and `php artisan storage:link`.
Add one cron line on the server: `* * * * * php /path/to/artisan schedule:run` — it drives the jobs below.

| Feature | Admin panel (Laravel) | Member app (Flutter) | Member API (`/api/v1/m`) |
|---|---|---|---|
| Birthday + anniversary wishes | *Birthday & Anniversary*: today's list (own area), message text, per-pradesh on/off, "send now" (Super Admin edits) | Home card "Aaj N birthday", full list (Parivaar / District / State), one-tap "Badhai do", hide-my-birthday switch | `GET greetings/today?scope=`, `POST greetings/{member}/wish`, `POST greetings/privacy` |
| Daily message (Subh Sandesh) | *Subh Sandesh*: one day or a whole month (`date \| tithi \| message`), all-India or per pradesh | Home card with tithi, Hindi/English text, Copy, link to Mahesh Vandana | `GET daily` (cached per day + pradesh) |
| Festival greetings | *Festival Greetings*: calendar, banner, message, push on the day | Festival banner on Home, upcoming festivals line | `GET daily` |
| Achievements | *Achievements & News*: state-wise moderation queue, Approve (+ push to district / state / all), Changes needed, Reject, Verified badge, Pin, private proof viewer | Community → Achievements: submit with photo + private proof, status in *Mere Post*, edit and resubmit, like ("Badhai") | `GET feed`, `GET feed/mine`, `GET feed/categories`, `POST feed`, `POST feed/{id}`, `DELETE feed/{id}`, `POST feed/{id}/like` |
| News + condolence notices | *+ Nayi News / + Shok Samachar*: image, district / pradesh targeting, schedule publish + expiry, pin, optional push | Community → News and Shok Samachar tabs | `GET feed?type=news\|condolence` |
| Toppers wall | *Toppers Wall*: yearly page, Print / Save as PDF | Toppers (trophy icon), grouped by category, year chips | `GET feed/toppers?year=` |

Scheduled jobs: `abmm:greetings` daily 07:00 (birthday/anniversary push, once per member per year), `abmm:festivals` daily 07:30, `abmm:feed-push` every 10 minutes (pushes of scheduled news).
Permissions: `engagement.*` (greetings, daily message, festivals) and `feed.*` (achievements/news; `feed.approve` to moderate). State Sub-Admin default: `engagement.view`, `feed.view/edit/approve`; existing sub-admins need these ticked once in Team. Area rules: State Sub-Admin sees and moderates only own pradesh, District Operator own district, Super Admin everything.
Privacy: members can hide their birthday from the community lists; proofs (marksheet / certificate) are stored on the private disk and only shown to admins who can see that post.

## Phase 3 — Community Services

### Help & Support (education / medical / scholarship)
Member app: raise a request with up to 3 documents, follow status. Head of Family sees the whole family's requests. Admin: **Help & Support** queue (area-scoped), status change with note, private documents, member gets bell + push. Permissions `help.view/edit/delete`.

### Job Portal (employer + job seeker)
- **Job seeker (employee):** one reusable job profile (headline, education, experience, skills, city, expected salary, optional resume link, *Open to work* switch); search/filter jobs (type, "my pradesh"); one-tap **Apply** with optional note; **Applied** tab shows status (Under review / Shortlisted / Selected / Not selected) and push on every change.
- **Employer / recruiter:** post a job (title, company, type, city, salary, experience, skills, vacancies, details, contact); **Hire** tab lists own posts with applicant counts; per job: applicants with profile + call/resume buttons, **Shortlist / Select / Reject**, close post; **Candidates** search over "open to work" profiles.
- **Admin:** **Jobs Board** queue — approve (choose days live, default 30), reject with reason, close, delete; sees applicants. Area-scoped. Permissions `jobs.view/edit/delete`.
- API: `jobs`, `jobs/{id}`, `jobs/{id}/apply`, `jobs/{id}/applicants`, `jobs/mine`, `applications/mine`, `applications/{id}/status`, `job-profile`, `candidates` (all under `api/v1/m`). Tables: `jobs_board`, `job_profiles`, `job_applications`.

### Samaj Directory (state-wise)
Members search only people of **their own pradesh** (name / surname prefix, zila, shehar, profession, education; 20 a page, max 10 pages, throttled). Everybody is listed by default, **phone is hidden unless the member opts in**, and anybody can hide completely (Directory → privacy icon). Admin side was already state-wise: State Sub-Admin sees only own state's data, District Operator own district.
**National by design:** Job Portal (jobs + candidates), Matrimony, Business listing and Business ads are visible to everyone across India. Jobs have an optional "Mere pradesh ki" filter.
Tables/columns: `members.dir_hidden`, `members.dir_show_phone`. API: `directory`, `directory/districts`, `directory/privacy`.

### Caste, Gotra, Country code and location (Master Data)
- **Master Data → Caste / Gotra / Country (+ phone code)**. `Maheshwari` is seeded with its full gotra list (editable; add other castes and their gotras the same way — Gotra rows are children of a Caste). Country rows carry a dial code (India = +91).
- Location order everywhere (admin family form, member add form, mobile registration): **Country → State → District → Tehsil/Town → City → Pradesh → Pincode**, each a dependent dropdown fed from Master Data.
- Caste/Gotra/Country code are on Family + Member, shown as dropdowns for Super Admin, State Sub-Admin, District Operator and in the app; Reports/Families have a Gotra filter. OTP login stays Indian-number-only.

### Matrimony 2.0 (global, all India; browse needs a Paid profile)
- Profile fields: marital status, gotra, height (cm), income band, diet, qualification, degree, occupation type/occupation, manglik, city/state/country, about, visibility.
- **Partner preferences** (age, height, qualification, marital, state, diet, min income, manglik, note) drive the **Matches** tab. Search filters: age, height, qualification, degree, occupation type, marital, income range, state, city, diet, manglik, same-gotra exclusion (default on).
- Interest send/accept/decline (20 per day), shortlist (200), contact details open only after acceptance.
- Admin: Matrimony list filters (same set) + full edit form incl. partner preferences; state-scoped for Sub-Admin/District Operator.
- Master Data: `parinay_marital`, `diet`, `salary_range` (yearly bands, name format "2-5 Lakh", "Below 2 Lakh", "50 Lakh and above").
- App: Home → **Rishta Search** (profile picker, Search / Matches / Interests / Shortlist / My Profile).

### Job Portal filters
- Job post: category, work mode, salary min/max (₹/month), education, experience range, gender preference. Seeker profile: category, work mode, preferred cities, expected salary, notice period.
- Seekers filter by category, work mode, salary band, education, state, experience, sort, **"Mere liye"** (matches my profile). Employers filter candidates by category, education, work mode, budget, notice period, gender, experience.
- Admin: Jobs Board and Job Candidates have the same filters. Master Data: `job_category`, `job_salary_range` (monthly bands, e.g. "10k-20k").
- Samaj Directory admin page (State Sub-Admin sees own state) has a global on/off and per-state switch.

### Deploy steps for this batch
`php artisan migrate` (000016–000020), `php artisan storage:link`, then tick the new permissions (help, jobs, directory) for existing sub-admins in Team → Permissions.


---

## Global platform — Milestone A (Global foundation)

Adds the international layer on top of the existing system. **Nothing existing was removed**; all new tables/columns are additive (migrations `2026_10_27_000021` … `000024`).

### After copying the files
1. `php artisan migrate` (4 new migrations: geography/roles, payments/subscriptions, forms/messaging, membership verification).
2. Add the keys from `env-additions.txt` (section *Milestone A*) to `.env` — Stripe, WhatsApp Cloud API, SMS (all optional; a feature whose key is empty simply stays off).
3. Make sure the queue worker is running (`php artisan queue:work`) — broadcasts are queued — and the cron line `* * * * * php artisan schedule:run` (two new jobs: `abmm:subscriptions` daily 06:00, `abmm:reconcile-payments` hourly).
4. Register these webhook URLs: Razorpay → `https://<domain>/webhooks/razorpay` (event *payment.captured*, *payment.failed*; same secret as `RAZORPAY_WEBHOOK_SECRET`; the old `/razorpay/webhook` for donations stays), Stripe → `https://<domain>/webhooks/stripe` (*checkout.session.completed*, *expired*), Meta WhatsApp → `https://<domain>/webhooks/whatsapp` (verify token = `WHATSAPP_VERIFY_TOKEN`).
5. Super Admin: **Countries & Zones** → add each country (currency, gateway, tax %, language); then create **Plans**, **Online Forms**, and grant the new permissions (Team → edit).

### What was added
| Area | Admin menu | Notes |
|---|---|---|
| Country > State > District > City > **Zone** | Countries & Zones | Zones per city (`Zone 1…N`), bulk import `Country\|State\|District\|City\|#6`. `families.zone`, `users.city/zone` added; members app can send `zone`. |
| New roles | Team / Sub-Admins | **Country Admin** (own country), **City Admin** (country+city), **Zone Coordinator** (country+city+zone). Same fail-closed area scoping as State/District. A creator can only create inside own area (`config/permissions.php → can_create`). |
| International phone | — | `PhoneService`; OTP login takes `mobile` + `country_code`; Firebase token is split into dial code + national number. Indian numbers keep the strict 10-digit rule. |
| Targeting | WhatsApp / SMS / Email | Nation → country → state → district → city → zone, cost estimate before sending. Non-super senders are forced into their own area. |
| Languages | Languages | Any locale as `English = Translation` lines; app downloads `GET /api/v1/m/translations/{locale}`. |
| Plans, coupons, subscriptions | Plans & Coupons, Subscribers | Audiences: member, business, bureau, vendor, advertiser, institution, training; periods monthly/quarterly/half-yearly/yearly/one-time; country-specific prices; auto-expiry + 7-day reminder. |
| Unified payments ledger | Payments & Receipts | One table for every payment (subscription, form fee…), tax-inclusive price split, coupons, **Razorpay + Stripe + manual (cash/bank)**, idempotent confirmation (client verify + webhook), partial/full **refunds** via the gateway, **reconciliation** (hourly + button), CSV/Excel export. |
| PDF receipts | Payments & Receipts | Generated on payment; delivered **in app** (notification + `GET payments/{no}/receipt`), **e-mail** (attached) and **WhatsApp** (utility template with the PDF as header — create template `payment_receipt` with 1 document header and 3 body variables: name, amount, receipt no). |
| Online forms | Online Forms, Form Approvals | Build forms (text, long text, number, date, dropdown, yes/no, file), optional fee, approval workflow, country/city visibility. |
| Verification | Member Verification | ID proof upload (private storage), **2 member references** (must be verified members and confirm in the app), then Membership ID `ABMM-<ISO>-<yy>-<serial>`, category (general/annual/life/patron) and chapter. |
| Messaging | WhatsApp / SMS / Email | Meta Cloud API, generic SMS HTTP gateway, e-mail; marketing needs **opt-in** (`message_optins`), reply *STOP* opts out; every send logged with cost. |
| Revenue | Revenue | Net collection by currency/country/source/month, subscribers, expiring plans, pending payments — scoped to the viewer's area. |

### Member-app API (prefix `/api/v1/m`, Bearer token)
`GET geo` · `GET translations/{locale}` (public) · `POST location` · `GET plans` · `POST subscribe` · `GET subscriptions` · `GET payments` · `POST payments/verify` (Razorpay) · `GET payments/{no}` · `GET payments/{no}/receipt` · `POST payments/{no}/send-receipt` · `GET forms` · `POST forms/{slug}/submit` · `GET forms-mine` · `GET verification` · `POST verification/id-proof` · `POST verification/references` · `GET verification/reference-requests` · `POST verification/references/{id}/respond` · `POST messaging/optin`.
Stripe returns `checkout_url` from `subscribe` / `forms/{slug}/submit`; the app opens it, the **webhook** confirms the payment.

### Known limits (planned for the next milestones)
* Jobs, help requests, news feed and job candidates now also carry `country/city/zone` (migration `000025`, back-filled from the family and stamped automatically on new rows), so Country/City/Zone roles can moderate them. Jobs keep their own `city` (the job location).
* Staff mobile API (`/api/v1`) has no endpoints for the new modules yet; the Flutter app itself is not part of this project.
* Tested end-to-end on SQLite with the real HTTP kernel (115 checks). Razorpay/Stripe/WhatsApp **live calls were not made** (signatures, webhooks and settlement logic are tested; the HTTP calls to the providers need your keys and a sandbox run).

## Engagement — Milestone B (polls, RSVP & QR pass, points & rewards, certificates, corrections)
Run `php artisan migrate` (adds `2026_10_27_000026_engagement_polls_events_points`) and `php artisan optimize:clear`. Add `WHATSAPP_NOTICE_TEMPLATE` from `env-additions.txt` if you send notices on WhatsApp. Existing Admin roles get the new menu entries after you tick the new permissions (Polls, RSVP, Points, Data corrections) in *Team*.

* **Polls & Surveys** (`/admin/polls`): one question = poll, many = survey; target everyone / country / state / district / city / zone; results with bars, country/state split, Excel/CSV/PDF export. Member API: `GET /api/v1/m/polls`, `GET /polls/{id}`, `POST /polls/{id}/vote` (one vote per member, points on completion).
* **Event RSVP + QR pass + attendance** (`/admin/rsvp`): capacity, guests per RSVP, closing date, per-event attendance points; each "going" RSVP gets a QR (`ABMM-EV<event>-<code>`). Volunteers scan from the browser (`/admin/rsvp/{event}/scanner`, camera or manual entry) or from the staff app (`POST /api/v1/scan/events/{id}`, permission `rsvp.scan`). Duplicate / wrong-event / invalid passes are flagged. Attendance report (Excel/CSV/PDF) and attendance certificates (PDF).
* **Points, badges, leaderboard, rewards** (`/admin/points`, `/admin/rewards`): editable point rules with daily caps (daily open, 7/30-day streak, poll, survey, RSVP, attendance, form submit, referral, quiz, housie), automatic Bronze/Silver/Gold badges, leaderboard by zone/city/country/world (monthly or all-time), reward catalogue with stock, redemption approval (reject = points and stock return), "active today" counter. Member API: `ping`, `points`, `leaderboard`, `rewards`, `rewards/redeem`, `rewards/mine`.
* **Star of the Month**: `php artisan abmm:monthly-recognition` runs on the 1st at 08:00 (scheduler) — top 3 per country get a PDF certificate + badge + notification.
* **Data-correction requests** (`/admin/corrections`): member/head files a request (mobile and Aadhaar excluded), the area admin approves/rejects, the value is changed with an audit-log entry. API: `/api/v1/m/corrections`.
* **Targeted notifications**: audience now also City and Zone; channel App / SMS / WhatsApp; filters male/female/head-only/blood group/vaccinated/COVID-recovered (members set the last two via `POST /api/v1/m/health`).
* **Reports** now carry the admin-defined *Custom Form Fields* as extra columns in every Member/Family download.
* Referral reward is credited to the referrer when the new family registers; each online form submission earns points.
* Tested on SQLite with the real HTTP kernel (see list in the delivery note); MySQL, live FCM/SMS/WhatsApp sends and the browser camera scanner were not exercised.

## Community & commerce — Milestone C
Run `php artisan migrate` (adds `2026_10_27_000027_community_commerce`) and `php artisan optimize:clear`. New `.env` keys are in `env-additions.txt` (`LISTING_REQUIRES_SUBSCRIPTION`, `B2B_REQUIRES_VERIFIED`). Tick the new permissions in *Team*: Member card/community, Directories, Ad packages, Marketplace, Ticketing, Vendors. The scheduler now also runs `abmm:community-jobs` hourly (event reminders 24h/1h, push ads, release of unpaid ticket holds, ad expiry).

* **Member card** (`GET /api/v1/m/card`): photo, QR, category, status and valid-till; public verification page `/verify/card/{token}` (HMAC-signed QR, shows no phone/address); admin *Card tools* to verify a card, assign a category and redeem voucher codes. Membership categories (General/Annual/Life/Patron + your own) link to a renewal plan; `POST /m/card/renew` pays it, reminders come from `abmm:subscriptions`.
* **Vouchers & benefits** (`/admin/manage/benefits`): eligibility by category, country, city, verified-only, active-only, min points; per-member and total limits, validity; members claim a code, partner/admin redeems it; redemption report (Excel/CSV/PDF).
* **Networking**: connect request / accept / reject / block / report, auto-connect (opt-in), suggested members, saved members, recently viewed + "viewed me", profile QR, consent-based contact sync (SHA-256 hashes, matched only with members who also opted in), international discovery, and chat between connected members with message reporting. Admin: *Member reports* (suspend member) and *Chat reports* (hide message).
* **Directories**: Maheshwari Bhavan / dharamshala / hotel / hospital / clinic / doctor with verification, member discount, halls & rooms with rates, availability calendar and booking enquiries (owner confirms; overlapping dates blocked). Owners register from the app.
* **Business categories** (unlimited tree), **organisation pages** (business / profit / non-profit with registration no and 12A/80G), listing subscriptions (monthly/quarterly/yearly plans for audience *business*; with `LISTING_REQUIRES_SUBSCRIPTION=true` only subscribed listings show).
* **Paid ads**: packages (featured, sponsored, home banner, category top, push), purchase → payment → admin approval → scheduled serving with city/zone targeting, impressions, clicks and CTR; push ads go out through the targeted-notification job; refund cancels the ad; receipts double as invoices.
* **B2B marketplace**: products with MOQ and bulk price tiers, seller verification, buyer access rule (verified members), quotation requests, seller leads + replies, buyer accepts, analytics. **Business groups**, meetings (venue or online link, RSVP) and **referral tracking** (given / contacted / won + closed value).
* **Event ticketing**: ticket types (paid/free), group booking with attendee names, coupons and GST through the payment ledger, QR tickets, gate scanner (admin page and `POST /api/v1/scan/tickets/{event}`), waitlist with automatic seat offers after cancellation/refund, attendee export. **Online events** (Meet / Zoom / live link released only to ticket or RSVP holders), `.ics` calendar file, reminders, post-event feedback and moderated photo gallery.
* **Event vendors**: directory by category, quote requests with lead alerts, reviews, vendor dashboard, subscriptions with top placement (plan feature line containing "Top").
* **Per-department trust forms**: each department can define its own extra fields (*Admin → manage → Departments*), asked when submitting a trust and sent in the department e-mail.
* Admin screens for most of the above are generated from `App\Support\ManageResources` (list, form, row actions, exports) — add a resource there to get a new screen.
* Tested on SQLite with the real HTTP kernel (376 checks including Milestones A and B); MySQL and live gateways not exercised.

## Partner dashboards & demo data — Milestone C2
Run `php artisan migrate` (adds `2026_10_27_000028_partners_dashboards`; also seeds 6 starter partner plans) and `php artisan optimize:clear`.

* **Marriage Bureau, Event Organiser, Job Recruiter**: a member registers (`POST /api/v1/m/partners/register`), the admin approves (*Partner dashboards* in the sidebar), then the partner **buys a subscription** (plans with audience Marriage bureau / Event organiser / Job recruiter, `POST /api/v1/m/subscribe` with `subscriber_id` = partner id). Charges are the plan price; the plan's **Limit** (`max_items`) caps profiles / events / jobs per subscription period.
* **No live subscription = locked**: adding records returns HTTP 402 with the plans to buy, listings disappear from the member app (profiles/jobs/events expire with the subscription and re-appear on renewal), and leads / applicants are shown without contact details.
* Bureau: profiles (visible in Parinay search while subscribed; interests also arrive as leads), directory, leads. Recruiter: job posts (live instantly), applicants with shortlist / hire. Organiser: events with RSVP and/or ticket types, attendee list, ticket revenue.
* Member API: `/api/v1/m/partners/{kinds,directory,register,mine,{id}/dashboard,{id}/records,{id}/enquiries,{id}/applications,{id}/attendees,{id}/enquire}`.
* Admin: `/admin/partners/{bureau|organiser|recruiter}` (KPIs, subscription revenue, expiring subscribers, state-wise split, approve), lists at *manage/bureaus|organisers|recruiters|partnerenq*, permission module `partners`.
* **State-wise visibility**: all generated *manage* screens now only show rows of the sub-admin's own area (state / country / city / zone); actions on other areas return 404.
* **Demo data**: `php artisan abmm:demo-data` creates 6 states x (6 families, committee, matrimony, businesses, ads, products, events, polls, donations, help requests, feed posts, trusts, jobs, job seekers, vendors, directory entries ...), a State Sub-Admin login per state (`demo.<rj|mh|mp|gj|ka|tg>.admin@demo.abmm.test`, password from `--password`, default `Demo@12345`) and the three partner dashboards (12-20 entries each, live / expired / pending / unsubscribed examples). Member-app logins are mobile OTP — set `TEST_OTP=123456` on a **test server only**. Remove everything with `php artisan abmm:demo-data --purge`.
* Tested on SQLite with the real HTTP kernel (464 checks including Milestones A-C); MySQL and live gateways not exercised. Ticket money is collected by the platform ledger; payout to organisers is not automated.

## AI features — Matrimony, Jobs, keyword search
No migration. Works offline with built-in rules; optional `.env`: `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (default `claude-haiku-4-5`), `AI_TIMEOUT` — when set, text understanding and drafts are refined by Claude and silently fall back to the rules on any error.

* **Natural-language search (English / Hindi / Hinglish)**: `GET /api/v1/m/matrimony/ai-search?text=CA ladki Jaipur 27 saal veg` and `GET /api/v1/m/jobs/ai-search?text=part time accountant Pune 20k` — age, height, income, diet, manglik, marital status, city/state, job type, work-from-home, experience, salary and role keywords are extracted; results are ranked and carry a match score with reasons. Hinglish words (kapda, khana, halwai, vakil ...) expand to synonyms.
* **Matrimony**: `matrimony/ai-matches` (best matches with reasons and concerns; same gotra is never suggested), `matrimony/{id}/ai-compatibility`, `POST matrimony/ai-about` (drafts the "about me" text).
* **Jobs**: `jobs/ai-matches` (jobs ranked for my job profile), `POST jobs/ai-description` (drafts a job post), recruiters: `partners/{id}/ai-rank?job_id=` ranks applicants by fit (needs the live subscription).
* **Keyword search across the platform**: `GET /api/v1/m/search?q=&types[]=business|job|event|vendor|place|product|news|partner&city=&state=&country=` with facet counts.
* **Admin → Smart search (AI)** `/admin/smart-search`: one box over families, members, matrimony, businesses, jobs, partners, vendors, events — only the admin's own area and permissions; matrimony accepts natural language.
* Scores are explainable rules (age/height/income/diet/city/qualification/preferences; skills/experience/city/salary/education), not a trained model. Tested on SQLite (486 checks); the Claude-assisted path was not exercised (no key in the test environment).

## Parichay, biodata & safety — Milestone D
Run `php artisan migrate` (adds `2026_10_27_000029_parichay_biodata_safety`) and `php artisan optimize:clear`. New `.env` keys: `ZOOM_ACCOUNT_ID`, `ZOOM_CLIENT_ID`, `ZOOM_CLIENT_SECRET` (Zoom Server-to-Server OAuth app, optional), `WHATSAPP_BIODATA_TEMPLATE` (approved template with a document header, 2 body variables: head name, number of profiles).

* **Online / offline Parichay**: *Admin → Events → kind "Parichay Sammelan"* (or a subscribed Marriage Bureau: `POST /api/v1/m/partners/{id}/parichay`, online or offline, free RSVP or paid QR tickets). Only members with a **paid Parinay profile** can register. Offline: QR pass + gate scanner check-in (existing ticket / RSVP scanners). Online: Zoom meeting is created automatically when Zoom keys are set (Google Meet / other links are pasted), link released only to registered members, every "Join" is logged as attendance, 24 h / 1 h reminders and feedback reuse the event engine. `GET /api/v1/m/parichay` lists them; `GET /api/v1/m/parichay/{id}/participants` shows the attending families (opposite gender, ranked by match score) so interests can be sent.
* **Biodata upload with auto-fill**: `POST /api/v1/m/matrimony/biodata/parse` (PDF / photo / .txt or pasted `text`) returns a draft of the Parinay form fields (name, DOB, height, qualification, occupation, income, gotra, diet, manglik, mobile, city ...). Reading order: text -> `pdftotext` -> `tesseract` OCR -> Claude (if `ANTHROPIC_API_KEY`); if the server has none of these, the file is stored and the member fills the form by hand. Nothing is saved to the profile until the member confirms.
* **Duplicate & fraud checks**: same mobile / same DOB with a similar name, blocklisted mobile / e-mail / name, under-18, one mobile on 3+ families, 15+ interests in an hour, 3 member reports. Flags go to *Admin → Parichay safety → Duplicate / fraud flags*; `php artisan abmm:profile-scan` re-checks all profiles nightly. Registration with a blocklisted mobile is refused.
* **Scam-profile tools** (*Parichay safety*): profile reports (members report from `POST matrimony/{id}/report`; 3 reporters hide the profile), one-click **Remove profile** / **Remove + block mobile**, blocklist, chat moderation (hide message), biodata upload log. New permission module `parichay`.
* **Scheduled biodata PDF**: members choose weekly / monthly and app / e-mail / WhatsApp (`PUT matrimony/biodata-prefs`); `abmm:biodata-digest` (daily 09:00) sends ONE PDF of the best new matches (min. score 50, no repeats, no contact details). `GET matrimony/{id}/biodata.pdf` downloads a biodata (contact details only for the own family, or when the other family consented after an accepted interest).
* **Consent-based chat**: after an interest is accepted either family can chat (`matrimony/chats`); phone numbers / e-mails are masked until both families consent (`POST matrimony/interests/{id}/consent`); family contact is shown only for the family that consented. Messages can be reported and hidden by moderators.
* Tested on SQLite with the real HTTP kernel (537 checks including Milestones A-C, partners and AI); the Zoom call is mocked, and OCR / Claude biodata reading and live WhatsApp / e-mail delivery were not exercised.

## Careers, blood donors, donations & investments — Milestone E

**After copying the files:** `php artisan migrate` (adds migration `2026_10_27_000030`: 25 tables), `php artisan storage:link` (pitch-deck uploads), `php artisan config:clear`. Optional `php artisan abmm:demo-data --purge && php artisan abmm:demo-data` to reload demo data including these modules.

### Careers & mentoring (admin: *Careers & mentoring*, permission module `careers`)
- **Paid / featured jobs** — admin creates *Job packs* (kind `post` or `featured`, price tax-inclusive, days). A *Featured* pack puts a live job on top of the list for N days (packs stack). Switch on **paid posting** with Settings key `jobs_paid_posting = 1`: a new job then waits as `unpaid` until the pack is paid, only then it goes for approval. Refund removes the featured status / closes an unpaid job. Orders: *Pack orders*.
- **Job alerts** — members save up to 10 alerts (keywords, category, city, type, mode, min salary; instant or daily). Instant alerts fire when the admin approves a matching job; daily digests run from `abmm:career-jobs` (hourly schedule).
- **Campus placement & internship drives** — members/colleges/companies propose drives (admin approves), members register, the poster shortlists/selects (member is notified). Admin: *Placement drives*, *Drive registrations*.
- **Mentors & mentees** — mentor profile (admin approves), mentee profile, **matching** (`GET careers/mentors/match`: skill/interest overlap + same city + mode + verified), mentoring or **career counselling** requests, mentor accepts (slot limit), either side schedules sessions (reminder 24 h before), mentor notes, mentee rating. Admin: *Mentors*, *Mentorships*, *Sessions*.
- API: `/api/v1/m/careers/...` (packs, jobs/{id}/promote, alerts, drives, mentors, mentorships, sessions).

### Blood donors (admin: *Blood donors*, module `blood`)
- Opt-in only (consent required), 90-day gap (`BLOOD_GAP_DAYS`), eligibility shown, opt out any time.
- **Search by group + city** (optionally compatible groups). Donors are shown by first name only; the **phone appears only after the donor approves** that person's request (consent).
- **Emergency requests** alert eligible, compatible donors (same city; emergencies widen to district then state) by push + inbox and alert admins. Donor approves → requester sees the phone → requester confirms "donated" → donor history updates and the donor is reminded when eligible again (`abmm:blood-reminders`, daily).
- **Camps** (donation drives): members propose, admin approves (donors of that city are notified), registrations, attendance and "donated" marking by admin; completing a camp updates donors' history.
- **Hospital, ambulance & blood-bank directory**: use *Directory* (`places`) with the new kinds `ambulance` and `blood_bank`; API `blood/directory`.
- API: `/api/v1/m/blood/...`.

### Donation campaigns & relief (admin: *Donations & relief*, module `giving`)
- *Entities* hold the trust/NGO details and **80G** approval number + validity. *Campaigns* have target, progress, end date, featured flag, area.
- Member donates via the unified ledger (`purpose = campaign`, **no GST**), anonymous option. If the entity's 80G is valid and the donor gives a PAN, a **numbered 80G certificate PDF** (`80G-<FY>-000001`) is generated, sent by e-mail and available in the app; a PAN can be added after payment. PAN is stored encrypted. Please have the wording of the certificate checked by your CA/trust lawyer before going live.
- *Utilisation reports* notify all paid donors; *Relief requests* → verify → "Approve & start campaign" creates a draft campaign; *Volunteers* register and are approved by admin.
- Refund of a donation reverses the campaign total. The earlier simple Razorpay `donations` flow is untouched.
- API: `/api/v1/m/giving/...`.

### Investments & business growth (admin: *Investments*, module `invest`)
- Curated *Opportunities* (admin only; always shown with a risk disclaimer — the app does not give investment advice), *Investor / entrepreneur profiles* (approved by admin), *Pitches* (deck link or PDF/PPT upload, admin approves, optional **Business showcase**), *Interests* (leads). Investor interest in a pitch notifies the founder; the owner gets suggested investors (sector, ticket size, stage) without any contact details.
- **Financial-literacy webinars** are normal Events with *Financial literacy webinar* selected in the event form; members find them at `invest/webinars` and RSVP through the existing RSVP endpoints.
- API: `/api/v1/m/invest/...`.

### Admin / permissions
New permission modules `careers`, `blood`, `giving`, `invest` (view, create, edit, delete, approve) are given to State and Country admins by default; City admins get `blood.view/approve` and `careers.view`. Give them to others in *Team → permissions*. Every list is area-scoped (a state sub-admin only sees his own state). Generic admin screens gained two hooks: `after` (after save) and `call` (row action calling a service).

### Not tested here
Real gateways, push/WhatsApp/e-mail delivery and MySQL itself (tested on SQLite). Use the sandbox mode of your gateway to check one donation + 80G certificate end-to-end before launch.

## Learning Academy, weekly exams & Housie — Milestone F

**Run once after upload:** `php artisan migrate` (new migration `2026_10_27_000031_milestone_f_academy_exams_housie`). The scheduler (`* * * * * php artisan schedule:run`) must be running: it now also runs `abmm:learning-run` (hourly) and `abmm:housie-run` (every minute).

### Learning Academy (admin: *Learning Academy* group)
- **Courses and lessons** - short videos (YouTube / Vimeo / MP4 / AI-video link), free-preview lessons, summary and script per lesson. Courses can be free, sold separately (tax-inclusive price, coupons work) and/or included in the **"AI training & courses" subscription plan** (create a plan with audience `training`).
- **AI course draft** (*Learning Academy > AI course draft*): type a topic, get a draft course with lesson titles, summaries, narration scripts and MCQs. Everything stays *draft / inactive* until an editor reviews it. Works with `ANTHROPIC_API_KEY` set; otherwise a simple skeleton is created. The actual video is produced with your AI-video tool and linked per lesson.
- **Instructor revenue share** - set the instructor member and share % on the course; each paid sale creates a pending earning on the net (after-tax) amount. Refund reverses it. *Instructor earnings* screen: mark paid (instructor is notified). Instructors see their earnings in the app.
- **Progress tracking** - 90% watched = lesson done (points), all lessons done = course done (bonus points + e-certificate).
- **Live classes** (Zoom / Meet link pasted by admin; link opens 15 minutes before; "subscribers only" option; attendance logged; reminders sent automatically) and **Knowledge library** (PDF / article / link / video, searchable).

### Weekly MCQ exams (admin: *Weekly exams* group)
- **Question bank** per segment (school / college / business / family): add one by one, or *Import questions* (one line per question). 
- **Auto weekly exam** every Monday per segment (needs at least 5 questions in the bank; `EXAM_QUESTIONS` = questions per exam). Admin can also create special exams and pick questions.
- Members choose their segment (*learner profile*); family exams are open to all. Timed attempt (late submissions rejected), **instant score with explanations**, live leaderboard, **weekly rank + monthly rank** (average of the month, at least 2 exams), **e-certificates** for passers, top 3 and monthly toppers. Answers are never sent to the app before submission.

### Housie (admin: *Housie* group)
- Create a game (date/time, free or paid ticket, max tickets per member, seconds between numbers, 5 prizes: Early Five, 3 lines, Full House, optional sponsor with banner). Sponsor banner is shown on game list and game screen.
- Valid 3x9 tickets are generated per member. At start time the scheduler shuffles 1-90 and calls numbers on time (even if nobody is online - missed numbers are caught up in order). The secret sequence is never sent to the app.
- Claims are auto-validated against the numbers called; a prize can be won once; 3 wrong claims block the member for that game; Full House ends the game. Winner notifications, admin alert (own-state admins only), winners leaderboard (weekly/monthly).
- Cancelling a game cancels the tickets; refund paid tickets from *Payments*.

### Demo data
`php artisan abmm:demo-data` now also adds 7 courses (35 lessons), 5 live classes, 6 library items, 24 exam questions, 8 exams, 2 sponsors, 3 Housie games globally, and for every state: 6 learner profiles, 7 enrolments, exam attempts, Housie tickets and claims. `--purge` removes it all.

### Known limits (to plan next)
- AI video generation and Zoom/Meet meeting creation are *not* automated: videos and links are pasted by admin.
- Instructor revenue share is automatic only for per-course sales (subscription revenue sharing is a manual calculation).
- Real payment gateways, push delivery and MySQL were not exercised in this environment (tested on SQLite with the manual gateway).

## Launch & production — Milestone G

**After upload:** `php artisan migrate` (adds `account_deletions`). Everything below is in `deploy/` and `docs/`.

- **Launch readiness:** `php artisan abmm:go-live-check` (or Admin > *Launch readiness*, super admin) checks debug mode, HTTPS, TEST_OTP, Firebase, Razorpay live keys, Redis queue/cache, mail, cron heartbeat, queue worker, storage link, compiled CSS, backups, demo data. Exit code 1 while anything is red, so it can gate a deploy script.
- **Production setup pack:** `deploy/env.production.sample` (Redis + live keys), `deploy/supervisor-abmm.conf` (queue workers), `deploy/crontab.txt` (the one cron line), `deploy/nginx-abmm.conf.sample`, `deploy/deploy.sh` (backup, migrate, cache, restart, check), `deploy/restore-backup.md`.
- **Backups:** `php artisan abmm:backup [--files]` — gzip'd MySQL dump in `storage/app/backups`, pruned after `BACKUP_KEEP_DAYS`; scheduled daily 03:30. Copy off-server too.
- **Real data import:** `php artisan abmm:import-check families.csv members.csv` is a dry run (nothing saved, lists bad rows); add `--commit` to import; `--audit` reports duplicate mobiles, families without head / Pradesh / city, member-count mismatches. Re-running is safe (rows are matched, not duplicated).
- **Compiled CSS:** `public/css/tw.css` replaces the Tailwind CDN (layouts fall back to the CDN if the file is missing). After changing views run `npm i && npm run build:css`.
- **Account deletion (store requirement):** in-app `POST /api/v1/m/account/delete` (`confirm=DELETE`), public page `/delete-account`, admin screen *Account deletion requests* (Restore / Erase now), automatic erase after 30 days (`abmm:erase-accounts`, daily 04:10). Payment/80G records are kept; the rest of the family is untouched.
- **Docs:** `docs/GO-LIVE-CHECKLIST.md`, `docs/UAT-TEST-PLAN.md` (role-wise tests to run on live MySQL), `docs/ADMIN-TRAINING.md`, `docs/STORE-PUBLISHING.md` (Play / App Store), `docs/STAFF-APP-SPEC.md` (optional staff screens for Flutter).
- **Also fixed:** admin bell alerts for Housie winners and investment interest now go only to super admins and the member's own state admins.

### What still needs real accounts / a live server (cannot be done from code)
Firebase project + phone auth keys, Razorpay live KYC and keys, Stripe, WhatsApp template approval, SSL domain, MySQL/Redis server, running the UAT plan, loading the real CSV, the Flutter store builds and Apple/Google developer accounts.

## iPhone app and Apple In-App Purchase
Apple requires In-App Purchase for digital content sold inside the iPhone app. The Flutter app sends `X-Platform: ios|android`; for iOS the API refuses card payments for Academy courses / subscription / paid job promotions and answers `use_web` (buy on the website) or, in `IOS_DIGITAL_MODE=iap`, `use_iap` + the App Store product id; the purchase is verified server-side (`POST /api/v1/m/iap/apple/redeem`, App Store Server Notifications at `/webhooks/apple`). Paid Housie tickets are never sold on iOS. Setup, Flutter contract and limits: `docs/IOS-PAYMENTS.md`. Scaling notes for 15 lakh users: `docs/SCALING-15-LAKH.md`. Run `php artisan migrate` (adds `iap_products`, `iap_transactions`).

---

# Updates: November 2026 (added on top of the instructions above)

The instructions above still apply. This section lists what was added or changed since, and where to find it. For the full map of the project, read `docs/PROJECT-HANDOVER.md`. For working rules, read `CLAUDE.md`.

## Location (family add and edit form)
- Fields open in order: **Country → State → District → Tehsil → ABMM Pradesh → ABMM Anchal**. A field unlocks only after the one before it is chosen. State, Tehsil and Pradesh are required. A hint under Country says what to pick next.
- Every State is linked to an ABMM Pradesh, so Anchal fills in for every state. Command: `php artisan abmm:link-states` (map in `database/data/anchal_state_map.json`).
- Tehsils come from the LGD subdistrict data (`php artisan abmm:import-geo`). Pincodes come from the LGD village-pincode data (`php artisan abmm:link-pincodes`) and fill when a tehsil is picked.
- **Village / Town / City:** when a tehsil is picked, the box suggests that tehsil's villages from `database/data/villages/` (served by `GET /admin/geo/villages`), plus cities already saved under that tehsil in the user's area.
- Sub-admins only see places in their own area. A State Sub-Admin sees every tehsil in their state, including states linked to another Pradesh.
- **WhatsApp number:** optional field on the add and edit form, 10 digits. Read from the CSV import when a WhatsApp column is present. New column `families.whatsapp` (migration `2026_11_04_000039`).

## Member search and reports
- Advanced filters: Anchal, Income, Resident type, Surname letter, typed surname (partial match), age range, birth month, registration dates, Mediclaim, Pakshik, status.
- CSV download of every matching record (needs `families.export`).
- Report dropdowns for sub-admins only list their own area.
- All filters are checked by `tests/unit/family-filters-check.php` and `tests/unit/report-filters-check.php`.

## Hanging Data
- The default view lists members whose head changed, families with no address, and married daughters (address check).
- Super Admin and State Sub-Admin can **Migrate** a member to another Family ID, or mark a member **Died** (removed from the family list).
- The older archive list is available with the "Moved out (archive)" view.

## Meeting list (Kariyakarni Sabha / Mandal Sabha)
- Sub-admins add meetings for their own Pradesh. Super Admin sees all, with Pradesh and Anchal filters and a "Submitted by" column.
- District and Anchal fill in from the chosen Pradesh. An optional PDF or image can be attached (5 MB max).

## Staff API (Flutter staff app)
- Fixed: family search was returning an error on every request.
- Added: `families/{id}/members`, `families/{id}/members/{mid}/archive`, `families/{id}/members/{mid}/migrate`, `reports/{type}` (JSON), `masters`, `masters/{type}`.
- Member app: `GET /api/v1/m/config` returns the organisation name, theme colours and logo URL.

## Theme, logo and layout
- Logo in the admin header, login, public header, favicon, print header, card and toppers. File: `public/images/abmm-logo.jpg` (must be readable, mode 644).
- Colours: Admin → Settings → Theme colours (Primary and Secondary). Shades are derived in `App\Support\Theme`.
- Dropdowns and menus close on Escape and on an outside click (`public/js/abmm-dropdowns.js`, `public/js/abmm-ms.js`).
- Chart.js is loaded with `defer`, so pages render before it.

## Sidebar
- Hidden for now (commented out in `resources/views/layouts/admin.blade.php`): Bhavan/hotel/hospital, Business ads & B2B, Tickets & online events, Event vendors, Smart search (AI), Parichay safety, Partner dashboards, Careers & mentoring, Blood donors, Donations & relief, Investments, Launch readiness, Learning Academy, Weekly exams, Housie, Polls & Surveys, Event RSVP, Points & Leaderboard, Rewards, Certificates, My Meetings/Events, Mahesh Vandana, Jobs Board, Plans & Coupons.
- "Purana data import" is renamed to **Old data import**.

## Checks and backups
- `bash tests/unit/run-all.sh` runs 13 checks, all passing. Browser behaviour is not covered by these checks.
- Database backup: `php artisan abmm:backup` (written to `storage/app/backups/`, not in git).
- Project zip: a partial copy exists in `storage/app/backups/`. Some files were skipped because of file permissions, so create it again after fixing permissions.

## Open items
- The Village / City dropdown and the Pincode fill still need to be confirmed in a browser (look at the `villages` request in the Network tab).
- Zone is not in the family form; its meaning needs to be confirmed.
- Migrate, Died, meeting save and file upload are tested by code checks only, not in a browser.
- Super Admin password: change the seeded default before go-live.
- GitHub push: see the note in `CLAUDE.md`.

## Data and checks (4 November 2026)
- Demo families (`DM` codes, 36 records with no members) were removed.
- Duplicate Master Data rows were merged (3,441 removed). Children were moved to the kept row, so state → district → tehsil links still hold.
- Records that need source data are not changed automatically: 12 families with no head, 16 family member counts that differ from active members, and 52 members with no date of birth. These will be fixed only from CSV files.
- Checks: `bash tests/unit/run-all.sh` (13 checks, all passing). New audit: `php tests/unit/data-audit.php` (read-only).
