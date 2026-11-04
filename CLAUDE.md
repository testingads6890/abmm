# CLAUDE.md: working rules for this project

Read `docs/PROJECT-HANDOVER.md` first. It has the full map of features, routes, roles and commands.

## What this is
ABMM Sampark: Laravel 13 admin panel, staff API (`/api/v1`) and member API (`/api/v1/m`) for Akhil Bharatvarshiya Maheshwari Mahasabha. MySQL. Blade views. Live site: https://abmm.khobargarden.com/public (the `/public` path is in `APP_URL`).

## Before you change anything
- Run `bash tests/unit/run-all.sh` and note the result. All 12 checks should pass.
- Check the file you are about to edit. Several files were changed by hand on the server; do not overwrite changes you did not make.

## Rules
- **Never commit or print secrets.** `.env`, `storage/app/private/*` (Firebase key), backups, and any password must stay out of git and out of chat. The super admin password seeded as `ChangeMe@12345` must be changed before go-live.
- **Personal data stays on the server.** Member CSV, SQL dumps, zip archives and `storage/` are never pushed to git. See the `.gitignore`.
- **Permissions:** the route guard and the menu guard must use the same key (`can:families.export` and `@can('families.export')`). Changing `config/permissions.php` only affects new users. Existing users keep their saved `permissions`.
- **Area limits:** a Super Admin sees everything. A State Sub-Admin or District Operator sees only their own Pradesh, district or tehsil. Check `Lookup::tehsilMetaFor` and `HasArea` before adding any list or query.
- **Master data:** use `Lookup`. Do not hard-code location lists. Country → State → District → Tehsil come from `Lookup`, and Pradesh, Anchal, pincode and villages come from the data files in `database/data/`.
- **Blade:** after editing a view, check that it compiles (`Blade::compileString`). After editing JS, run `bun test tests/unit/`.
- **Files in `public/`** must be readable by the web server (`644`, folders `755`). A file created with mode `600` returns 403 or 404 (this happened with the logo).
- **Do not edit** the numbered copies `admin.6554/`, `layouts.8672/`, `public.8732/`, `routes/lweb.php`, `routes/oldweb.php`, `routes/workingweb.php`. The live routes are in `routes/web.php`, `routes/commerce.php`, `routes/global.php`.
- **Commands that change live data** (migrate, link-states, link-pincodes, import-geo, import-anchal) must be run only after the user agrees. Take a backup first: `php artisan abmm:backup`.

## Useful commands
```bash
bash tests/unit/run-all.sh             # all checks
php artisan abmm:backup                # database backup into storage/app/backups
php artisan abmm:import-geo            # states, districts, tehsils
php artisan abmm:import-anchal         # Anchal and Pradesh
php artisan abmm:link-states           # state -> Pradesh (Anchal follows)
php artisan abmm:link-pincodes         # tehsil pincodes
php artisan route:list --path=api      # all API routes
```

## Where things are
- Family form: `resources/views/admin/families/form.blade.php` (location cascade, Anchal, City/Village suggestions)
- Village endpoint: `GET /admin/geo/villages` in `FamilyController@villages`, data in `database/data/villages/`
- Member search: `FamilyController@index`; reports: `ReportController`
- Meeting list: `MeetingListController`, table `meeting_list`
- Theme colours: Admin → Settings, applied by `App\Support\Theme`
- Sidebar menus hidden for now: see `docs/PROJECT-HANDOVER.md`, section 8

## Git and GitHub
- Repository: https://github.com/testingads6890/abmm (branch `main`). The server commits locally, then pushes over SSH with the key `~/.ssh/id_ed25519_testingads` (account testingads6890).
- Push command: `GIT_SSH_COMMAND="ssh -i ~/.ssh/id_ed25519_testingads -o IdentitiesOnly=yes" git push git@github.com:testingads6890/abmm.git HEAD:main`
- Commit messages end with `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.
- `.gitignore` keeps out `vendor/`, `.env`, `storage/`, SQL dumps, CSV member lists and zip archives. Check `git status` before every commit.
- Do not force-push. Do not push member data or secrets.

## Checks
- `bash tests/unit/run-all.sh`: 13 checks (PHP and Bun). All must pass before a push.
- Browser behaviour is not covered by these checks.

## Known open items
- Village/City dropdown and Pincode fill: confirm in a browser (Network tab, `villages` request). The server side and the form script pass the checks.
- Zone is not in the family form. Its meaning must be confirmed.
- Migrate, Died, meeting save and file upload are tested by code checks only.
- Laravel 13 upgrade is not started and needs a separate check. The server runs Laravel 12.71.0 on PHP 8.2.35.
- Change the seeded Super Admin password before go-live.
