# Staff mobile screens (Flutter) - optional module

Build only if the Mahasabha wants admins on phones. The backend is ready: bearer-token **Staff API v1** (`/api/v1`). Roles: Super Admin, State Sub-Admin, District Operator (permissions decide what each can call).

| Screen | Endpoint(s) | Notes |
|---|---|---|
| Login | `POST /api/v1/auth/login` (login + password) -> token | store token securely; register push: `POST /api/v1/me/fcm` |
| Home / dashboard | `GET /api/v1/dashboard`, `GET /api/v1/me` | counts for the staff member's own area |
| Families list + search + detail | `GET /api/v1/families`, `GET /api/v1/families/{id}` | needs `families.view` |
| Team (Super/State) | `GET/POST /api/v1/team`, `PUT /team/{id}`, `PATCH /team/{id}/toggle`, `POST /team/{id}/reset-password`, `GET /team/catalogue` | needs `users.*` |
| Ticket gate scanner | `POST /api/v1/scan/tickets/{eventId}` body `{code}` -> 200 ok / 409 duplicate / 404 invalid | camera QR scan; works offline-tolerant by retrying |
| Logout | `POST /api/v1/auth/logout` | |

Suggested effort: 3-4 weeks for login, dashboard, family search, team management and the gate scanner including store release. Approvals, payments and reports stay on the web admin.
