# Formwerks Dashboard

The management dashboard, rebuilt on Laravel 13, Livewire 4 and Flux, following
[Spatie's guidelines](https://github.com/spatie/guidelines-skills). It replaces
the Python dashboard, now archived in `../Management Dashboard Backup`: every
page except Mosaic is ported. The sidebar and the Help page list both come from
`config/navigation.php`.

- **Sign-in:** Microsoft only (Socialite + Entra ID). Everyone starts as
  **Staff**; admins promote people on the **Users** page and get an email when
  someone new signs in. `nathan@formwerks.ca` is seeded as an admin.
- **Data:** upload `Dashboard Data.xlsx` (the old dashboard's export) on
  **Upload workbook**. It replaces the billing and office data in one
  transaction, then checks every figure on the workbook's *Report for Jim*
  against the new billing engine, to the cent.
- **Hosting:** Vercel (`vercel-php`), database on Supabase Postgres, email
  through Mailgun.

## Roles

| Role | Can |
| --- | --- |
| Admin | Everything, including workbook uploads and user roles |
| Manager | Firm health, Projects and Business development pages; edit billing, the team, pursuits and developers, software and hardware |
| Staff | Software, Hardware, Team Lineup and Help, read only |

Roles and permissions use `spatie/laravel-permission`. The mapping lives in
`app/Enums/Role.php`; re-run `php artisan db:seed --class=RolesAndPermissionsSeeder`
after changing it.

## How the billing works

`app/Billing/BillingEngine.php` is a port of the old `engine.py`. It loads the
billing tables once per request and derives every figure from stored inputs,
rounding half away from zero at each step the way the workbook did (exact
decimals through `app/Support/Money.php`, never floats). Highlights:

- A phase draw is a **percentage** of the fee or a **typed dollar amount** (the
  CA monthly draw). The fee is frozen on each entry, so amending a contract never
  restates an invoice.
- Hourly ("extras") billing is the sum of the staff lines unless a job-month
  override replaces it. It uses up the contract balance.
- A closed billing period refuses writes. Every write is recorded in
  `audit_logs` with who made it, before and after.

Imported against the current `Dashboard Data.xlsx`, all 1,680 figures on Report
for Jim match the old dashboard exactly.

## Local development

```bash
composer setup     # install, .env, key, migrate, build assets
php artisan db:seed
php artisan import-workbook "/path/to/Dashboard Data.xlsx"
composer dev       # or: php artisan serve
```

Sign in locally without Microsoft at `/dev/login/nathan@formwerks.ca`. That
route only exists when `APP_ENV=local` and only answers requests from this
machine.

On the office share, `vendor/` and `node_modules/` are symlinks to
`~/.local/share/fwdash/` because installing thousands of small files over SMB is
too slow. Keep the SQLite database on local disk too (`DB_DATABASE` in `.env`).
Run `npm install` in `~/.local/share/fwdash/npm` (npm replaces a symlinked
`node_modules`).

Checks: `composer test` runs Pint, Larastan and the Pest suite.

## Deploying

### 1. Microsoft sign-in (Entra ID)

In Entra ID → App registrations → New registration:

- Supported accounts: *this organization only*.
- Redirect URI (Web): `https://<your-domain>/auth/microsoft/callback`, plus
  `http://localhost:8000/auth/microsoft/callback` for local use.
- Certificates & secrets → new client secret.
- API permissions: Microsoft Graph `User.Read` (delegated) is enough.

Set `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` and `AZURE_TENANT_ID` (the
directory ID). `AZURE_ALLOWED_DOMAINS` (default `formwerks.ca`) is a second guard.

### 2. Supabase

Create a project and copy the **transaction pooler** connection string (port
6543) into `DB_URL`. `vercel.json` already sets `DB_CONNECTION=pgsql`,
`DB_SSLMODE=require` and `DB_EMULATE_PREPARES=true`, which the pooler needs.
Then, from a machine with the production env loaded:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan import-workbook "/path/to/Dashboard Data.xlsx"
```

### 3. Mailgun

Verify the sending domain, then set `MAILGUN_DOMAIN`, `MAILGUN_SECRET`,
`MAILGUN_ENDPOINT` (`api.eu.mailgun.net` for an EU domain) and
`MAIL_FROM_ADDRESS`.

### 4. Vercel

Import the GitHub repository. `vercel.json` sets the PHP runtime, the build
(`npm run build`, served from `public/`) and the serverless paths (caches and
compiled views in `/tmp`, logs to stderr, sessions and cache in the database).
Add these environment variables in the Vercel project:

`APP_KEY` (from `php artisan key:generate --show`), `APP_URL`, `DB_URL`,
`AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`,
`AZURE_REDIRECT_URI`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAIL_FROM_ADDRESS`.

Uploads are read and applied in a single request, because nothing written to
disk on Vercel survives into the next one. The workbook must be under 4 MB
(Vercel's request limit is 4.5 MB).

## Outreach list

The Outreach list holds third-party contact details, so it is never committed.
Load it into the database from the old dashboard's `outreach.json` (thumbnails
are read from `outreach-thumbs/` beside it unless `--thumbs` says otherwise):

```bash
php artisan outreach:import "../Management Dashboard Backup/data/outreach.json"
```

## Mosaic project roles

The timesheet lists the projects Mosaic has you on first. Refresh the Mosaic
Mock pull (GET only) and load its roles; re-running replaces them:

```bash
python3 "../Mosaic Mock/sync/mosaic_pull.py" --only reference
python3 "../Mosaic Mock/sync/mosaic_pull.py" --only portfolios
php artisan mosaic:import-roles "../Mosaic Mock/data/raw"
```

## Not ported

Mosaic is left out: hours come from the workbook's Hours sheet, and "Paused" is
set on the Projects page instead of Mosaic. Monthly targets are not in the
workbook, so they are kept only in the database.
