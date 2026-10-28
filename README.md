# Berea library API · Beria CMS backend

The deployed Laravel API for Berea Church's community library. It connects anonymous book discovery and member accounts to administrator-reviewed loans, checkouts and returns.

**API:** https://beria-cms-back.onrender.com/api

**Frontend:** https://beriabooks.vercel.app

**Frontend source:** [HabibElias/beria-cms](https://github.com/HabibElias/beria-cms/tree/main/front-end-r)

Render deploys this repository's root using its Dockerfile. The `back-end/` directory in the frontend repository is an older working copy and is not the production source of truth.

## Implemented capabilities

- Public, paginated catalogue with title/author search, categories, availability and private-field filtering.
- Member registration, JWT login/refresh/logout, own-profile and password updates.
- Own current loans and request history; pending-request cancellation.
- Admin-only books, categories, members, checkouts, returns, current analytics and loan review.
- Approval/decline with reader-visible notes and a due date; transactional checkout/return handling.
- Duplicate-request, borrowing-limit, competing-checkout and role/ownership enforcement.
- Local SQLite, remote Turso/libSQL and retained PostgreSQL support.
- Non-destructive startup migrations, targeted first-admin setup and a sourced starter catalogue.

## Requirements

PHP 8.3+, Composer, Laravel 12, the usual Laravel PHP extensions, and PDO SQLite for local development/tests or PDO PostgreSQL when using PostgreSQL. Turso's native SDK requires PHP FFI enabled. The repository Dockerfile supplies the production PHP/Apache runtime and FFI configuration.

The pinned `turso/libsql` SDK is 0.2.5; `turso/libsql-laravel` is a technical-preview adapter. The application registers only the libSQL connection so ordinary SQLite/PostgreSQL behavior is retained.

## Local setup

For a fresh checkout:

```sh
composer install
test -f .env || cp .env.example .env
touch database/database.sqlite
```

Edit `.env` for local SQLite; replace the database path with this checkout's absolute path:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/beria-cms-back/database/database.sqlite
FRONT_END_URL=http://localhost:5173
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

On this fresh local installation, generate keys and apply migrations:

```sh
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed --class=BookSeeder
```

Configure an administrator as described below, then run:

```sh
php artisan db:seed --class=AdminSeeder
php artisan serve --host=127.0.0.1 --port=8001
```

Preserve existing keys and records when updating an existing environment. Use the React app in the other repository with `VITE_APP_BASE_URL=http://localhost:8001/api`.

## Production on Render with Turso

Use `HabibElias/beria-cms-back`, branch `main`, Docker runtime, repository root, `Dockerfile`, and health-check path `/api/health`. The container respects Render's `PORT` (default 10000), caches Laravel configuration, runs selected startup operations, and starts Apache.

| Variable | Production setting |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://beria-cms-back.onrender.com` |
| `APP_KEY` | Existing Laravel application key |
| `JWT_SECRET` | Existing JWT signing secret |
| `FRONT_END_URL` | `https://beriabooks.vercel.app`; comma-separated exact extra origins supported |
| `DB_CONNECTION` | `libsql` |
| `TURSO_DATABASE_URL` | Your remote `libsql://…turso.io` URL |
| `TURSO_AUTH_TOKEN` | Your database auth token, entered securely on Render |
| `RUN_MIGRATIONS` | `true` to apply outstanding migrations at startup |
| `CACHE_STORE`, `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `SEED_ADMIN`, `SEED_BOOKS` | `false` after their one-time imports |

The Dockerfile, driver and startup changes are already in this repository. With that code deployed, environment variables select Turso. An older image without the driver cannot gain Turso support through variables alone.

Remote Turso avoids reliance on Render's ephemeral filesystem. A local `DB_CONNECTION=sqlite` file is suitable for development or a deliberately configured persistent disk, not durable free-service production storage. PostgreSQL remains selectable with `DB_CONNECTION=pgsql` and its normal connection variables.

Changing database settings creates no data transfer: existing PostgreSQL users, password hashes, books and relationships need a separate migration if they must be retained. No live PostgreSQL-to-Turso data conversion was performed. Keep existing application/JWT keys when moving services.

Startup uses `migrate --force` only when enabled; it never resets the database or runs the full development seeder. The additive migration `2026_10_27_160000_create_loan_requests_table.php` creates the request table and relationships without replacing existing records. Do not use `migrate:fresh` or `db:wipe` for deployment.

[Complete Render/Turso configuration and migration guidance](docs/render-database.md).

## Create the first administrator

Set these in local `.env` or securely in Render's environment settings, replacing all placeholders:

```dotenv
ADMIN_EMAIL=your-email@example.com
ADMIN_PASSWORD=YOUR_UNIQUE_PASSWORD_12_TO_72_CHARACTERS
ADMIN_NAME="Library administrator"
ADMIN_PHONE=your-phone-number
```

For Render startup, additionally set `SEED_ADMIN=true` and `RUN_MIGRATIONS=true`, then save/redeploy. The command-line equivalent is:

```sh
php artisan db:seed --class=AdminSeeder --force
```

The seeder creates only this administrator. It validates the configuration and hashes the password. Existing administrator credentials/profile are preserved on subsequent runs; an email owned by a non-admin is rejected without promoting that account.

After signing in through [the frontend login](https://beriabooks.vercel.app/login), set `SEED_ADMIN=false` and remove `ADMIN_PASSWORD` from Render. This seeder is not a password-reset mechanism.

The legacy default `DatabaseSeeder` is development-only and includes the requested demo account `admin@church.com` / `password1234`, plus other sample accounts. It is not run automatically in production and does not establish the credentials of an existing production administrator. Prefer targeted `AdminSeeder`/`BookSeeder` setup.

## Real-book starter catalogue

`BookSeeder` imports 16 real Christian titles and five categories from the committed `database/data/library-catalogue.json` snapshot. It includes edition metadata, page counts, external cover URLs, and ISBN/source notes.

On Render, use `SEED_BOOKS=true` with `RUN_MIGRATIONS=true` and redeploy. From a trusted console:

```sh
php artisan db:seed --class=BookSeeder --force
```

The transactional import preserves existing exact title/author matches, edits and availability. It does not create members or loans, reset passwords, truncate tables or remove old placeholder books. Disable `SEED_BOOKS` after the first successful import.

Seeded inventory fields (`UNASSIGNED` shelf, `good` condition and available status) require a physical inventory check before lending. Metadata/cover URLs come from a secondary source; external cover availability is not guaranteed.

The adapted catalogue data carries separate attribution and a CC BY-NC 4.0 license. [Data sources, edition choices and inventory defaults](database/data/README.md).

## API and frontend integration

All paths below start with `/api`. Protected requests use `Authorization: Bearer <JWT>` and should accept JSON.

| Access | Endpoint | Purpose |
| --- | --- | --- |
| Public | `GET /health` | Database query: 200 `ok`, or 503 `unavailable` |
| Public | `GET /catalogue/books` | Search/filter/page books; `q`, `category`, `status`, `page`, `per_page` (max 48) |
| Public | `GET /catalogue/books/{id}` | Public book detail |
| Public | `GET /catalogue/categories` | Categories with book counts |
| Public | `POST /register` | Create a regular member; returns user and JWT |
| Public | `POST /login` | Sign in for any account role |
| Valid JWT | `POST /refresh` | Refresh account/token state |
| Authenticated | `POST /logout` | Sign out |
| Authenticated | `GET /my-loans` | Own current checkouts |
| Authenticated | `GET/POST /loan-requests` | Own history / submit a request |
| Owner | `DELETE /loan-requests/{id}` | Cancel own pending request |
| Authenticated | `POST /user/profile`, `POST /user/password` | Own profile/password |
| Admin | `GET /admin/loan-requests` | Search/filter request queue |
| Admin | `POST /admin/loan-requests/{id}/approve` | Approve and issue checkout |
| Admin | `POST /admin/loan-requests/{id}/decline` | Decline, with optional note |
| Admin | `/books`, `/categories`, `/members` | Collection/member management |
| Admin | `/checkouts`, `GET /analytics` | Circulation and current totals |
| Admin | `PATCH /checkouts/{id}` | Renew a due checkout within its renewal limit |

Management routes require `admin`. Existing `librarian` and `user` accounts use the reader experience. Public registration prohibits role assignment and always creates `role=user`.

Registration accepts `name`, `email`, `phone`, `password` and `password_confirmation`. Registration, admin-created members and password changes require 12–72 characters within the bcrypt 72-byte limit and confirmation. Existing passwords are not reset; login still accepts older valid credentials. Email normalization and case-insensitive uniqueness checks protect creation and profile/member edits.

Public responses omit internal notes, shelf location, condition and storage paths. Readers can access only their own requests/loans; another reader's cancellation target returns 404. Self-profile updates cannot change roles. Login is limited to 20 requests/minute, registration to 6/minute, and loan submission to 20/minute.

The CORS configuration includes `https://beriabooks.vercel.app` and local Vite origins on ports 5173/5174. Add exact extra origins using `FRONT_END_URL`. Redeploy to rebuild cached production configuration; use `php artisan config:clear` when changing local cached settings.

[Detailed request contract, permissions and deployment order](docs/member-library.md).

## Loan lifecycle

A request is not a reservation. Creation requires an available copy, no duplicate pending request, and fewer than three current loans plus pending requests.

Approval requires a pending request and fewer than three current loans. It atomically claims the available copy, creates a checkout and records the reviewing admin. A competing approval/manual checkout cannot issue the same copy twice. The default return date is 14 days from approval; an explicit date must be more than one week away.

Cancellation and decline clear the pending request without creating a loan. Readers see the administrator's note. Returning a checkout records the return, clears its request link and restores availability transactionally. The reader can then request the book again. Manual admin checkouts use the same borrowing service.

Admins can renew a checkout on or after its current due date, up to three times. Each renewal sets its return date to one week from the renewal date and updates the renewal count transactionally.

Admin members/checkouts support bounded pages up to 100 records; the frontend follows all pages so new registrations and older loans remain accessible.

## Verification

Run against the isolated test configuration in `phpunit.xml`:

```sh
php artisan test
# Or use Composer's wrapper to clear cached configuration first:
composer test
```

The recorded reader/admin release passed **31 tests / 670 assertions**, covering native libSQL migrations/transactions, configuration mapping, health, CORS, password policy, administrator and book seeds, public-field privacy, roles, own-record access, duplicate/competing requests, borrowing limits, returns and rollback.

A separate real-HTTP browser workflow used a disposable local SQLite database and fictional accounts to cover registration through approval and return, without creating production records. Production HTTPS checks confirmed the public 16-book catalogue, frontend-origin CORS and unauthenticated admin rejection. These checks are not a live production-account browser test. The workflow file under `.github/workflows` is currently commented out; these results do not imply active CI.

## Frontend design, portfolio and current limits

The full frontend uses the supplied Berea palette, icon-only SVG, Nunito and Noto Sans Ethiopic, with responsive light/dark screens. Its [README](https://github.com/HabibElias/beria-cms/blob/main/front-end-r/README.md) links the design system and installed Impeccable/Taste tools.

The [public portfolio pack](https://beriabooks.vercel.app/downloads/Berea-Portfolio-Pack.zip) contains 21 screenshots, four presentation boards, a PDF, editable layouts and case-study copy, using fictional member/circulation data.

Cloudinary upload/cleanup code was prepared in the older frontend repository's local backend copy; this standalone API does not contain that SDK, configuration or `POST /api/book-images` route. End-to-end production cover upload and migration of existing image files remain pending. Current catalogue covers use external URLs and frontend fallback jackets.

Email verification, notifications and password-reset email delivery are not implemented. Analytics exposes current totals rather than historical trends. Free Render instances may sleep.

The application package declares MIT in `composer.json`; catalogue-data and third-party asset licenses are separate.
