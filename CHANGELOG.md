# Changelog

All notable changes to the Appointment Module, grouped by phase.

## Phase 0 — Pre-flight

- Added `web` session guard in `config/auth.php` so Sanctum stateful flows never blow up on missing guard lookups.
- Cleared Sanctum `guard => []` — the SPA uses bearer tokens only.
- Default `DB_CONNECTION` to `pgsql`; dropped `mariadb`/`sqlsrv` blocks.
- `.env.example` updated with `APP_TIMEZONE=Europe/Istanbul`, Render Postgres hints, and `RUN_MIGRATIONS`/`SEED_DATABASE` gates.
- `config/cors.php` allows the production Netlify origin and `*.netlify.app` preview regex.

## Phase 1 — Data integrity

- **Schema rename**: `catagory_id` → `category_id` on `staff` and `services`, plus the `services_(category_id|name)` unique index. Non-destructive migration `2026_08_15_000001_rename_catagory_to_category.php` with portable rename logic across MySQL/Postgres/SQLite.
- **Replacement of destructive `2026_07_28_000001_add_unique_constraints`** with a no-op stub + new `2026_08_15_000002_unique_constraints_v2.php` that adds indexes idempotently and only reports duplicate-blocker groups (never deletes).
- **Soft delete** (`SoftDeletes` trait) on `categories`, `services`, `staff`. Migration `2026_08_15_000003_soft_delete_parents.php` switches appointment FKs from `CASCADE` to `RESTRICT` so historical appointments survive parent removal.
- **FK casts** added to `Appointment`, `Staff`, `Customer`, `Admin`, `Service` models so MySQL/Postgres (which hydrate ints as strings) compare cleanly against PK int casts.
- All strict `===`/`!==` comparisons in `AppointmentController` and `StaffController` use `(int)` casts as a belt-and-braces guard.
- New artisan command `data:dedupe-before-unique` with `--dry-run` default and a `CONFIRM_DESTRUCTIVE_DEDUPE=YES` environment guard for any `--apply` run.

## Phase 2 — HTTP contract fixes

- `App\Support\AppointmentStateMachine` is now the single source of truth for transitions. `updateStatusAsStaff` and admin `update()` both consume it; terminal states are guaranteed unreachable.
- `switch-role` response now includes `other_roles` (parity with `login`).
- All three appointment list endpoints (`/appointments`, `/my-appointments`, `/staff/appointments`) validate `tab`/`status_id`/`staff_id`/`date`/`sort_by`/`sort_order`/`per_page`/`page` and reject unknown filters with 422.
- `Service::update` rejects duration changes while active appointments exist unless `force_duration_change=true`, and writes audit rows to `service_duration_history`.
- `Staff::update` (admin) remains the only path that may change `category_id`; `StaffProfileController` (self) strips it from the validation allowlist.
- Public availability, registration and login endpoints carry throttling (`60/min`, `30/min`, `10/min`).
- `Register`, profile update, and customer register translate unique-violation races on `phone_number` into 422 instead of a 500.
- Parent delete (category/service/staff) wrapped in a transaction with `lockForUpdate()` on the parent row + soft delete, eliminating the check-then-delete race.

## Phase 3 — Docker / Render

- `Dockerfile` slimmed down to runtime deps only — drops Node/npm (Netlify builds the SPA). Source tree is owned by root and `chmod=550`; only `storage/` and `bootstrap/cache/` are writable by `www-data`.
- `docker/entrypoint.sh` gates `APP_KEY` regeneration against existing values, refuses to seed unless `APP_ENV=local` + `SEED_DATABASE=true`, and warms Laravel's config/route/event/view caches.
- `docker/nginx.conf` explicitly forwards `HTTP_AUTHORIZATION` so bearer tokens reach PHP through Nginx.
- `.dockerignore` excludes SQLite databases and tests from the build context.
- `render.yaml` Blueprint provisions a Postgres Starter (`pserv`) and Docker web service with `/up` healthcheck and auto-deploy from `main`.

## Phase 4 — Auth/authz hardening

- `App\Providers\AppServiceProvider::boot()` defines `manage-staff`, registers `availability` + `login` rate limiters, and turns on Eloquent strict mode in non-production.
- `App\Support\BusinessClock` provides `BUSINESS_TIMEZONE = 'Europe/Istanbul'`; `Appointment::scopeTab` uses it instead of bare `now()`.
- `App\Http\Controllers\AuthRefreshController` adds `POST /api/auth/refresh` for token rotation (old token revoked).
- Empty `AUTH_GUARD` env no longer throws `Auth guard [] is not defined.` — `config/auth.php` falls back to `web`.

## Phase 5 — Frontend contract alignment

- `AuthProvider` rehydrate path now uses the typed `profiles.get(role)` helper instead of the raw `fetch()` that silently produced `user === undefined` after a hard refresh (audit C1).
- `setAuthConsumer` replaces `window.dispatchEvent("auth:logout")`; the axios interceptor calls a real callback. `useLogoutMutation` calls `handleLogout` directly.
- Schema rename propagated through `src/other/types.ts`, every page, and `src/utils/ids.ts` for stable ID coercion.
- Drop the dual service-scope → category-scope fallback in `BookAppointmentPage` and `MyAppointmentDetailPage`. Service-scoped staff only.
- `Login.tsx` collapses the two competing `useEffect`/`useNavigate` flows into a single ref-guarded branch.
- `AdminStaffEdit.tsx` (and siblings) capture `isError` so 500/404 is rendered with the right message.
- `api/interceptors` consumer is the only place that knows about 401 → logout flow.

## Phase 6 — TS strict + UX hardening

- `tsconfig.app.json` enables full `strict` mode plus `noUncheckedIndexedAccess`, `useUnknownInCatchVariables`, etc.
- Top-level React error boundary in `src/App.tsx` prevents white-screen on render-time exceptions; recoverable via a "Tekrar Dene" button.
- `netlify.toml` adds CSP, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Permissions-Policy` for camera/microphone/geolocation — applied to every branch via the SPA-wide `[[headers]]` block.

## Phase 7 — Backend tests

- New `tests/Feature/ContractFixesTest.php` (11 cases) adds coverage for:
  - state-machine transitions
  - strict ID compare (FK casts)
  - self-service category denial
  - duplicate-phone 422 translation
  - soft-delete preserves appointment history
  - date/sort-by/customer_name filter validation
- Total suite: **35 tests passing** across Feature + Unit.

## Phase 8 — CI + deploy

- `.github/workflows/api-ci.yml`: Postgres 16 service container + Composer cache + `php artisan test`.
- `.github/workflows/web-ci.yml` (frontend repo): typecheck + ESLint `--max-warnings 0` + production build.

## Phase 9 — Docs / cleanup

- `README.md` rewritten with: unified `/login` endpoint, schema rename note, Render deployment section, full endpoint table, and updated test inventory.
- Netlify `netlify.toml` (frontend repo) gains the SPA-wide security headers.
