# Is Flutter + Riverpod right for 15 lakh users? Yes - and what to plan on the server

**App side:** Flutter + Riverpod (+ Dio, go_router) comfortably supports 15 lakh+ users; the app is not the bottleneck. Keep: feature-wise folders, `AsyncNotifier` + repository pattern, `autoDispose` providers for screens, pagination (`per_page`) on every list, `cached_network_image`, offline cache (Hive/Isar) for home and directory, Firebase Crashlytics, split by flavor (test/live).

**What matters is the backend.** Typical numbers: 15 lakh registered -> 10-15% daily active (1.5-2.2 lakh) -> peak concurrent 15-30k (evenings, Housie night, festival greetings).

| Area | Do |
|---|---|
| Servers | 2+ app servers behind a load balancer (4 vCPU/8 GB each to start), separate MySQL (8 vCPU/32 GB, SSD), Redis, queue workers. Start on one strong server and split when metrics say so. |
| MySQL | Indexes exist on the hot columns; run `EXPLAIN` on the 10 slowest queries after real data load (`slow_query_log`). Add a read replica for reports/exports. Never run exports on the primary at peak. |
| Redis | cache, sessions, queue, rate limits (already configured in `deploy/env.production.sample`). |
| Queues | push (FCM topics/batches of 500), WhatsApp, e-mail, digests via Supervisor workers (3-10). Festival greetings to lakhs must be queued, not sent inline. |
| Housie night | State polling is already limited to one "number caller" request per second per game, others only read. For > 20k players put `GET /housie/games/{id}` behind a 1-second cache (Redis/nginx microcache) or move to Firebase Realtime DB/WebSocket (Laravel Reverb). Load-test before the first big game. |
| Files | photos/PDFs on object storage + CDN (S3/Cloudflare R2); resize on upload. |
| OTP | Firebase Phone Auth SMS has per-country quotas and a cost per SMS above the free tier - check current pricing; enable App Check / reCAPTCHA to stop abuse; consider WhatsApp OTP later. |
| Push | FCM is free; use topics by state/city for bulk. |
| Security | rate limits are on every module; keep `APP_DEBUG=false`; database backups daily + restore test. |
| Monitoring | Uptime monitor, Laravel logs to a central place, server CPU/RAM/disk alerts, Firebase Crashlytics. |

**Load test before launch:** k6 script for login, home, directory search, course list, Housie state with 5k -> 20k virtual users; target p95 < 800 ms and error rate < 1%.
