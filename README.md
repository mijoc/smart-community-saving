# Laravel VSLA Management System

A production-ready **Village Savings & Loan Association (VSLA)** management system built with **Laravel 11**. It manages members, groups, dynamic group rules, scheduled contribution generation, payment recording, an arrears + late-fee engine, dashboards, and member passbooks.

## Features

- **Authentication** — Laravel session auth (login / register / logout / password reset)
- **Roles & permissions** (via `spatie/laravel-permission`):
  - `super_admin` — system-wide; manages all groups, users, and platform settings (does **not** belong to a group)
  - `group_admin` — full control over the group(s) they are assigned to: members, contributions, rules, reports, overrides
  - `treasurer` — financial operations within their group: payments, contributions, arrears
  - `secretary` — records management within their group: members, meetings, documentation
  - `member` — view-only access to their own contributions, group status, and passbook
- **Active group context** — staff and members can belong to multiple groups. After login the system either auto-picks their only group or shows a "Choose your group" page. The active group is shown in the topbar with a dropdown to switch any time. Super admins can also browse globally ("All groups").
- **Loans module** — members request loans from inside their group; group admins / treasurers approve, reject, disburse and record repayments. Flat-interest model with automatic principal/interest split per repayment.
- **Members** — CRUD with KYC fields, photo, status
- **Groups** — CRUD; *one member can belong to multiple groups* via `group_member` pivot (with per-group position & join date)
- **Dynamic group rules** — each group defines its own rules (contribution amount, frequency, currency, late-fee %, grace period, share value, max shares, social fund %, loan rules…)
- **Contribution schedules** — weekly / fortnightly / monthly / custom cadence; auto-generates `Contribution` rows for every active member
- **Scheduled contribution generation** — `php artisan vsla:generate-contributions` (runs daily via scheduler)
- **Payment recording** — partial / full / over-payment with passbook entries
- **Arrears + late-fee engine** — `php artisan vsla:apply-late-fees` (runs daily via scheduler) marks overdue contributions, applies the group's late-fee rule, opens an `Arrear` row
- **Dashboards** — Super-admin org dashboard, group dashboards, member dashboard
- **Member passbooks** — full per-member ledger (debits/credits/balance) per group

## Tech

- PHP 8.2+, Laravel 11
- MySQL 8 / PostgreSQL / SQLite
- Tabler.io admin UI (loaded via CDN — no build step required)
- Blade views, server-side rendered

## Install

```bash
cd laravel-vsla
composer install
cp .env.example .env
php artisan key:generate

# Edit .env — set DB_*, APP_URL, MAIL_* as needed.
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Default seeded logins (all use password `password`):

| Username     | Recovery email         | Role          | After login lands on              |
| ------------ | ---------------------- | ------------- | --------------------------------- |
| `superadmin` | `superadmin@vsla.test` | `super_admin` | Global dashboard (browse any group, switcher in topbar) |
| `groupadmin` | `groupadmin@vsla.test` | `group_admin` | "Choose group" page (assigned to **both** demo groups) |
| `treasurer`  | `treasurer@vsla.test`  | `treasurer`   | Auto-selects "Wakiso Women United" |
| `secretary`  | `secretary@vsla.test`  | `secretary`   | Auto-selects "Wakiso Women United" |
| `member`     | `member@vsla.test`     | `member`      | Personal dashboard (only their groups visible) |

Sign in with the username and password. Email remains available as a recovery/contact address, and existing accounts can still sign in with their email during the transition.

## Scheduler

Add this single line to your server's cron:

```
* * * * * cd /path/to/laravel-vsla && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler is wired in `app/Console/Kernel.php`:

| Time     | Command                            | Purpose                                                                                  |
| -------- | ---------------------------------- | ---------------------------------------------------------------------------------------- |
| 00:05 d  | `vsla:generate-contributions`      | Creates the next `Contribution` row for every active schedule whose next-due date is ≤ today |
| 00:30 d  | `vsla:apply-late-fees`             | Marks overdue contributions, applies late-fee rule, creates Arrear rows                  |
| 02:00 w  | `vsla:rebuild-passbooks`           | Rebuilds passbook balances (idempotent reconciliation)                                   |

You can run any of them manually:

```bash
php artisan vsla:generate-contributions
php artisan vsla:apply-late-fees
php artisan vsla:rebuild-passbooks
```

## Module map

| Module                 | Model                  | Controller                       | Routes prefix          |
| ---------------------- | ---------------------- | -------------------------------- | ---------------------- |
| Members                | `Member`               | `MemberController`               | `/members`             |
| Groups                 | `Group`                | `GroupController`                | `/groups`              |
| Group rules (dynamic)  | `GroupRule`            | `GroupRuleController`            | `/groups/{g}/rules`    |
| Contribution schedules | `ContributionSchedule` | `ContributionScheduleController` | `/groups/{g}/schedules`|
| Contributions          | `Contribution`         | `ContributionController`         | `/contributions`       |
| Payments               | `Payment`              | `PaymentController`              | `/payments`            |
| Arrears                | `Arrear`               | `ArrearController`               | `/arrears`             |
| Passbooks              | `PassbookEntry`        | `PassbookController`             | `/passbooks`           |
| Users                  | `User`                 | `UserController`                 | `/users`               |
| Dashboards             | —                      | `DashboardController`            | `/`                    |

## Many-to-many membership

A `Member` can belong to many `Group`s through the `group_member` pivot. The pivot also stores:

- `position` — `chairperson | secretary | treasurer | member`
- `joined_at`, `left_at`
- `share_count`
- `is_active`

This is critical for VSLAs because real members typically save in more than one group.
