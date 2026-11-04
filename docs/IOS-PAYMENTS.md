# iPhone app and Apple In-App Purchase (IAP)

**Rule (App Store 3.1.1):** digital content or features unlocked *inside* the iPhone app must be sold with Apple's In-App Purchase (Apple keeps 15-30%). Real-world things are exempt: event tickets, donations to a registered trust, services delivered outside the app. Android has no such rule, so Razorpay works as before.

## What the backend does now
The Flutter app sends a header on every request: `X-Platform: ios` (or `android`). For iOS requests that would start a card/UPI payment for a **digital purpose** the API refuses it and tells the app what to do. Digital purposes (change with `IOS_DIGITAL_PURPOSES`): Academy courses (`course`), Academy subscription plan (`training`), paid Housie tickets (`housie`), paid job promotions (`jobpack`). Tickets, donations, member/business listing plans and ads are **not** in the list - decide with your Apple reviewer notes (listing plans that unlock app features may also be treated as digital; add `member`/`business` purposes only if Apple objects).

Choose the mode in `.env` (`IOS_DIGITAL_MODE`):

| Mode | iPhone behaviour | Effort | Apple fee |
|---|---|---|---|
| `external` (default) | Buy button replaced by "buy on the website"; access appears in the app after purchase on the web (API answer: 403 `use_web` + url). Already-bought content plays normally. | none | none, but Apple may still ask you to explain the web link |
| `iap` | App starts a StoreKit purchase, then calls the server to unlock (API answer: 409 `use_iap` + `product_id`) | Flutter `in_app_purchase` work + App Store Connect products | 15% (Small Business Program, < USD 1M) or 30% |
| `off` | no restriction | none | risk of rejection - not recommended |

**Paid Housie tickets are never sold on iOS** (any mode): paid entry for prize games can be treated as real-money gaming, which needs state licences and a different Apple category. Free Housie games work on iOS. Get legal advice before offering paid Housie anywhere.

## Setting up `iap` mode
1. App Store Connect > Business: sign the Paid Apps agreement, add bank/tax details.
2. Create products: one *non-consumable* per course, one *auto-renewable subscription* per Academy plan. Note each product identifier.
3. Admin > Plans & Coupons > **iOS products**: map each App Store product id to a Course ID or Plan ID.
4. Download **Apple Root CA - G3** (apple.com/certificateauthority), convert and place on the server:  
   `openssl x509 -inform der -in AppleRootCA-G3.cer -out storage/app/apple/AppleRootCA-G3.pem`
5. `.env`: `IOS_DIGITAL_MODE=iap`, `IOS_BUNDLE_ID=your.bundle.id` (`IOS_ALLOW_SANDBOX=true` only on a test server).
6. App Store Connect > App > App Information > **App Store Server Notifications**: URL `https://DOMAIN/webhooks/apple` (version 2, production and sandbox). This keeps renewals, expiries and refunds in sync.
7. Run `php artisan abmm:go-live-check` - the "Apple IAP setup" line must be green.

## Flutter app contract
- Add header `X-Platform: ios|android` in the Dio/http client interceptor (Riverpod: put it in the shared `dioProvider`).
- On 403 `use_web`: show "Is course ko website par kharidein" with the `url`. Do not show prices with payment buttons.
- On 409 `use_iap`: start StoreKit purchase for `product_id` (Flutter `in_app_purchase`). After a successful purchase send `POST /api/v1/m/iap/apple/redeem` with `{ "signed_transaction": "<JWS from StoreKit 2 / purchase verification data>" }`; on 200 refresh the course/plan; finish the transaction only after the 200. Call it again at app start for pending/restored purchases ("Restore purchases" button is mandatory).
- `GET /api/v1/m/iap/products` lists the mapped products.
- What the server checks: Apple signature chain to the Apple root, bundle id, product mapping, sandbox/production, refund flag, and that the transaction is not used by another account.

## Known limits
- Instructor revenue share and tax invoices are not automatic for IAP sales (Apple pays you net of its fee; reconcile from App Store Connect reports). IAP sales appear in Admin > iOS purchases, not in Payments.
- A subscription bought on iOS is active for iOS and also unlocks the same plan on Android/web for that account.
- Tested here with a locally generated certificate chain; run one real sandbox purchase from TestFlight before launch.
