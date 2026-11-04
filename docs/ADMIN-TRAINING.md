# Admin training pack - ABMM Sampark

Plan: one 90-minute live session per country/state team (Zoom, recorded), then 15-minute refreshers when a new module goes live. Each trainee gets a staging login and the exercises below. Sessions can be run in Hindi; screens are English.

## Session outline (90 min)
1. **Orientation (10)** - how the hierarchy works (Country > State > District > City > Zone), what each role sees, why you only see your area, privacy rules (never share member data outside the app).
2. **Daily work (25)** - approvals queue (new families / members / corrections), search (Smart search: type `CA Jaipur 28`), edit a family, exports, notifications to your area.
3. **Money (15)** - Payments list, mark paid (cash/cheque), refunds, receipts, coupons, subscription renewals, donations and 80G certificates.
4. **Modules (30)** - pick what your role runs: Matrimony bureau / Events and tickets / Jobs and placement drives / Blood requests / Businesses and ads / Academy and exams / Housie. For each: create, approve, publish, close, report.
5. **Safety (5)** - block abusive users, remove content, escalation to Super Admin, account-deletion requests, what never to do (share OTPs, passwords).
6. **Q&A and practice (5)**.

## Role cheat-sheets
**State Sub-Admin** - Approvals first thing every morning (Members > Pending). Weekly: exam question top-up (min. 10 questions per segment), one Housie game, one event. Monthly: reports export to Country Admin. Create City/District operators from Team and give only the permissions they need.
**City / District Operator** - Verify new families by phone, approve corrections, answer help requests, confirm blood requests, scan tickets at events.
**Country Admin** - Payment gateway per country, plans and prices in local currency, ad rates, announcements across states, revenue report.
**Matrimony bureau / Event organiser / Job recruiter partners** - own dashboard: profiles/events/jobs, subscription status, enquiries, showcase.

## Exercises (each trainee must complete on staging)
1. Approve a pending family and fix a wrongly-spelt city. 2. Search a member by mobile and by Hinglish query. 3. Record a cash payment and issue a receipt, then refund it. 4. Create an event with ticket types and scan a QR. 5. Add 5 exam questions and import 5 more with the bulk tool. 6. Create a Housie game with a sponsor. 7. Handle a blood emergency request. 8. Process an account-deletion request (restore, then erase on a test user). 9. Export a report for your area.

## Rollout across countries
Train State/Country admins first (train-the-trainer), record each session, translate the cheat-sheets, run a WhatsApp support group per country for the first 30 days, and track questions in a shared sheet to fix the product or the training.

## Locations, sub-admin chain and manual add (latest)

**Locations** (menu: Locations) - Super Admin builds the tree Country → State → District → City → Zone 1, Zone 2 … → Pincode. India and all 36 states / UTs come pre-loaded. Add one item at a time or use *Bulk import*:
`Country | State | District | City | Zone | Pincode(s) | Area` - e.g. `India | Rajasthan | Jaipur | Jaipur | #4 | 302001, 302002 | Civil Lines` (`#4` creates Zone 1 … Zone 4). The same tree feeds the public dropdown API: `GET /api/v1/geo` (countries), `GET /api/v1/geo?parent_id=ID` (children), `GET /api/v1/geo/pincode/302001`.

**Sub-admin chain** - Super Admin creates State Sub-Admins → each State Sub-Admin creates District Admins → each District Admin creates City Admins → each City Admin creates Zone Admins. Everyone sees (and can edit / disable) only their own branch; the upper level can manage everyone below. A District / City / Zone Admin can only be created for a place that exists in Locations. Country Admin (for non-India countries) still works as before.

**Manual add** - these screens now have **+ Add**: mentors, placement drives, blood donors, relief requests, volunteers, investors, pitches, marriage bureaus, event organisers, recruiters, vendors, businesses, products, business groups, ads, booking enquiries, course enrolments. Type the member's registered mobile number - the member, family and area are filled automatically, and a sub-admin can only add for members of his own area. Payments, claims, reports, tickets, chats and exam results are system records and have no manual add.

**Language** - every screen and message is English. Hindi appears only when the user switches to Hindi (admin: language toggle; member app: `X-Lang: hi` header or saved profile language). Fixed messages are translated from `lang/hi.json`; anything not listed there stays English.
