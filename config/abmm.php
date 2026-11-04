<?php

return [
    'app_api_key' => env('APP_API_KEY'), // shared secret the mobile app sends as X-App-Key

    // Firebase Cloud Messaging (HTTP v1). Put the service-account JSON OUTSIDE public/, e.g. storage/app/private/firebase.json
    'fcm' => [
        'project_id'       => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS', storage_path('app/private/firebase-service-account.json')),
    ],
    // Firebase Phone Auth (member app OTP). The Flutter app signs in with Firebase, then sends the Firebase ID token to /api/v1/m/auth/firebase.
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'dev_bypass' => env('FIREBASE_DEV_BYPASS', false), // only honoured when APP_ENV=local: token "dev:<mobile>"
    ],
    // TESTING ONLY: when set (e.g. 123456) the member app may log in with mobile + this OTP, skipping Firebase. LEAVE EMPTY IN PRODUCTION.
    'test_otp' => env('TEST_OTP'),
    // AI helpers (smart search, match scores, drafting). Works fully offline with built-in rules; when ANTHROPIC_API_KEY is set the
    // free-text understanding and the drafts are refined by Claude (falls back silently to the built-in rules on any error).
    'ai' => [
        'anthropic_key' => env('ANTHROPIC_API_KEY'),
        'model'         => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'timeout'       => (int) env('AI_TIMEOUT', 12),
    ],
    // Zoom Server-to-Server OAuth app: creates the meeting link for online events / Parichay automatically.
    'zoom' => ['account_id' => env('ZOOM_ACCOUNT_ID'), 'client_id' => env('ZOOM_CLIENT_ID'), 'client_secret' => env('ZOOM_CLIENT_SECRET')],
    // Razorpay — use rzp_test_* keys while testing.
    'razorpay' => [
        'key_id'         => env('RAZORPAY_KEY_ID'),
        'key_secret'     => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    // Stripe (international cards). Hosted Checkout; webhook endpoint: POST /stripe/webhook
    'stripe' => [
        'secret_key'     => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    // WhatsApp Business Cloud API (Meta). Templates must be pre-approved in Meta Business Manager.
    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token'           => env('WHATSAPP_TOKEN'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'      => env('WHATSAPP_APP_SECRET'),   // optional: validates X-Hub-Signature-256 on incoming webhooks
        'receipt_template' => env('WHATSAPP_RECEIPT_TEMPLATE', 'payment_receipt'),
        'notice_template' => env('WHATSAPP_NOTICE_TEMPLATE', 'notice'),
        'biodata_template' => env('WHATSAPP_BIODATA_TEMPLATE', 'biodata_digest'),
        'template_lang'   => env('WHATSAPP_TEMPLATE_LANG', 'en'),
        // approx. per-message cost (INR, ex-GST) used only for the cost column in the message log
        'cost' => ['marketing' => 0.8631, 'utility' => 0.115, 'auth' => 0.115],
    ],
    // Generic HTTP SMS gateway (MSG91 / Twilio-compatible). Leave url empty to disable SMS.
    'sms' => [
        'url' => env('SMS_URL'), 'key' => env('SMS_KEY'), 'sender' => env('SMS_SENDER', 'ABMMSM'),
    ],
    // Milestone C: when true AND a paid plan exists for the audience, only listings with a live subscription are shown (business / vendor / directory entries).
    'listing_requires_subscription' => (bool) env('LISTING_REQUIRES_SUBSCRIPTION', false),
    // B2B quotations need a verified membership.
    'b2b_requires_verified' => (bool) env('B2B_REQUIRES_VERIFIED', true),

    // iPhone app: Apple requires In-App Purchase for digital content unlocked in the app (see App\Support\IosPolicy and docs/IOS-PAYMENTS.md).
    'ios' => [
        'mode'          => env('IOS_DIGITAL_MODE', 'external'),                                   // external | iap | off
        'purposes'      => array_filter(array_map('trim', explode(',', env('IOS_DIGITAL_PURPOSES', 'course,housie,jobpack,training')))),
        'bundle_id'     => env('IOS_BUNDLE_ID'),
        'root_ca'       => env('APPLE_ROOT_CA_PATH', storage_path('app/apple/AppleRootCA-G3.pem')),
        'allow_sandbox' => (bool) env('IOS_ALLOW_SANDBOX', false),                                 // true only on TEST/TestFlight servers
        'web_url'       => env('IOS_WEB_URL'),
    ],
    'blood' => ['gap_days' => (int) env('BLOOD_GAP_DAYS', 90)],     // minimum days between two whole-blood donations
    'exam' => ['questions' => (int) env('EXAM_QUESTIONS', 10)],         // questions per weekly exam
];
