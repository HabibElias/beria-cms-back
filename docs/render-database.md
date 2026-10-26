# SQLite and Turso on Render

The application supports three database options:

| Option | `DB_CONNECTION` | Persistence on Render |
| --- | --- | --- |
| PostgreSQL | `pgsql` | External database; unchanged support |
| Local SQLite file | `sqlite` | Lost on free-service restart/redeploy; use a paid persistent disk for durable local storage |
| Remote Turso (SQLite/libSQL) | `libsql` | Stored remotely, independent of Render's filesystem |

## One-time code deployment

The old `beria-cms-back` deployment did not include the Turso driver or its PHP FFI runtime. The prepared changes add them to **this repository**, so Render can stay connected to `HabibElias/beria-cms-back` with its current root directory. They also retain PostgreSQL support for a staged migration or rollback.

Local verification covers the environment-to-driver URL/token mapping, migrations, authenticated requests, transactions and database caching. Production-image checks cover HTTP health, SQLite/cache writes as the `www-data` worker, and all seven migrations through libsql/FFI on PHP 8.3. A real remote Turso connection still requires valid database credentials.

Push/review the prepared changes and deploy that commit once. Do not point the old image at Turso by setting `DB_CONNECTION=libsql` before the code is deployed: it has no such connection. The Dockerfile uses Debian PHP 8.3 with FFI, PostgreSQL and SQLite support, and respects Render's `PORT` variable.

## Turso configuration

Create a Turso database and obtain its URL and database auth token. In **Render → your web service → Environment**, set:

```dotenv
DB_CONNECTION=libsql
TURSO_DATABASE_URL=libsql://YOUR-DATABASE.turso.io
TURSO_AUTH_TOKEN=YOUR_DATABASE_AUTH_TOKEN
RUN_MIGRATIONS=true
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
SEED=false
RUN_SEEDERS=false
```

Enter the actual token securely in Render; never commit it or put it in a frontend `VITE_` variable. The example above is a placeholder, not a working credential. After the initial code deployment, changing those variables and redeploying the service is sufficient to select a different Turso database. No SQLite file or persistent Render disk is needed.

Keep existing `APP_KEY`, `JWT_SECRET` and `APP_URL` values. Preserve `FRONT_END_URL` / allowed origins for the frontend. Existing PostgreSQL `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and `DB_URL` values are not used by the libsql connection; remove obsolete entries when cutover is complete.

`RUN_MIGRATIONS=true` runs Laravel migrations to create missing tables. Startup never resets the database or runs demo seeders. The optional `SEED_ADMIN=true` creates only the administrator described below. `/api/health` returns `{"status":"ok"}` when a database query succeeds, and 503 when Turso configuration/connection is unavailable. Check login, books, categories, and checkouts separately after migration.

The official Turso Laravel adapter is in technical preview. The application locks the stable PHP SDK `0.2.5` and enables only the libsql connection, preserving ordinary PostgreSQL and SQLite connections. Local integration tests exercise the actual FFI adapter with an in-memory database; remote connectivity still needs real Turso credentials.

## Existing production data

Changing environment variables **does not copy existing users, books, or checkout records**. A new Turso database starts empty. Back up the existing PostgreSQL database and migrate its rows with IDs, password hashes and relationships preserved before changing the live service. A PostgreSQL SQL dump is not directly compatible with SQLite. Keep the old database for rollback. No live-data migration is included or has been performed.

## Create your first administrator with Render environment variables

For a fresh database, set these in **Render → your web service → Environment** and save/redeploy:

```dotenv
RUN_MIGRATIONS=true
SEED_ADMIN=true
ADMIN_EMAIL=your-email@example.com
ADMIN_PASSWORD=YOUR_UNIQUE_PASSWORD_16_TO_72_CHARACTERS
ADMIN_PHONE=your-phone-number
```

Replace the placeholders with your own email, password and phone number. Keep the password in Render, never in Git or frontend variables. Optional `ADMIN_NAME` defaults to `Administrator`. A phone number is required by the users schema; requiring a non-empty value also avoids an empty-string binding bug in the preview Turso driver.

After migrations, startup runs only `AdminSeeder`. The password is hashed by the User model. It creates one administrator without sample users, books or categories. Logs say `Administrator created` after success. Sign in at <https://beriabooks.vercel.app> using exactly the email and password you set.

After signing in, set `SEED_ADMIN=false` and remove `ADMIN_PASSWORD` from Render. Redeploying with seeding enabled does not reset an existing administrator's password or profile. If the email belongs to a non-admin, seeding fails without changing that account; choose an unused email instead.

With a trusted Laravel console, the same seeder can be run with `php artisan db:seed --class=AdminSeeder --force` after setting these credentials and migrating. This is not a password-reset mechanism. Do not run the repository's default `DatabaseSeeder` in production; it contains public development credentials and sample data.

## Local-file SQLite (temporary testing or a paid disk)

After deploying the prepared code, temporary testing can use:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
RUN_MIGRATIONS=true
```

Startup creates the file if missing and gives the application permission to write to it and its directory. These changes create no persistence on Render's free service. For a paid persistent disk mounted at `/var/data`, use `DB_DATABASE=/var/data/beria.sqlite` instead. Never use `:memory:` as the production database.

Do not run `migrate:fresh`, `db:wipe`, or demo seeding to switch production databases. Keep the original database and keys available until the new service has been verified.
