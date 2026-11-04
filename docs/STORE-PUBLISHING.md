# Play Store and App Store publishing checklist (member app)

The Flutter app is built separately; the backend already provides what the stores check for.

## Accounts and costs
- Google Play Console: one-time USD 25. Apple Developer Program: USD 99 / year. Register as an organisation (D-U-N-S number needed for Apple and for Play organisation accounts) in the Mahasabha's name.

## Backend items stores require (all done)
- **Account deletion inside the app + a web URL**: in-app `POST /api/v1/m/account/delete` (type DELETE), web page `https://DOMAIN/delete-account` (paste this URL in Play Console > Data safety > Account deletion and App Store Connect).
- **Privacy policy URL**: publish a policy under Content > Pages and use that URL. It must list: data collected (name, phone, address, photo, DOB, Aadhaar if given, payment info via Razorpay, FCM token), purpose, sharing (payment gateway, SMS/WhatsApp, Firebase), retention (30-day deletion grace, tax records kept), contact.
- **Login for reviewers**: add 2 Firebase test phone numbers with a fixed OTP and a demo family with sample data; put them in "App access" (Play) and "Review notes" (Apple). Remove after approval.
- **User-generated content** (feed, groups, pitches): moderation + report/block exist in admin - mention this in the review notes (Apple 1.2).
- **Payments**: course/subscription/ticket purchases for real-world or community services via Razorpay are allowed; if the app sells *digital content consumed in the app* (Academy subscription, courses) Apple may require In-App Purchase. Decide before submission: either (a) keep Academy purchases on the website/outside the iOS app, or (b) add Apple IAP for iOS. Donations to a registered trust are exempt as charitable giving but need the trust's details in review notes.
- Permissions justification texts (camera for QR/photo, contacts only if used, location for blood/near-me, notifications).

## Play Console steps
1. Create app -> package name -> upload signed AAB (Play App Signing). 2. Store listing: Hindi + English title/description, 512 icon, 1024x500 feature graphic, 4-8 phone screenshots. 3. Content rating questionnaire, target audience (18+ for matrimony/investment sections; if the exam section targets school students, complete the Families policy). 4. Data safety form (match the privacy policy). 5. Internal testing -> Closed testing (12+ testers for 14 days is required for new personal accounts; organisation accounts are exempt) -> Production.

## App Store steps
1. App Store Connect record, bundle id, TestFlight build. 2. Screenshots 6.7" and 5.5", description, keywords, support URL, privacy URL. 3. App Privacy "nutrition labels". 4. Sign in with Apple is **not** needed (phone login only). 5. Submit; typical review 1-3 days; answer reviewer questions fast.

## After approval
Staged rollout 10% -> 50% -> 100%, crash monitoring (Firebase Crashlytics), in-app update prompt, remove reviewer test numbers.
