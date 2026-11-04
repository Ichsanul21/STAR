# STAR — AGENTS.md

## Current state

- Laravel 13 app is scaffolded and running on SQLite locally.
- Product plan: `docs/star-event-ops-plan.md`. Design system: `design.md`.
- Implemented: auth, developer role + real impersonation + Dev Tools (environment/logs/routes/failed jobs/dev sessions/force actions/reseed + empty reset), dashboard (KPI Event Aktif/Task Perlu Perhatian/Total PnL/Outstanding Client, Chart.js grouped performance bars with % labels, status chart, agenda, team activity, deadline countdowns, finance strip), global pages (My Tasks, Clients, Budget, Expenses, Payments, Documents, Reports, Master Data, Settings), global search, CSV exports, compact one-line filter toolbars, collapsible desktop sidebar with tooltips + active auto-scroll, notification popover preview, expense bulk approval, global create modals for expenses/invoices/payments, conflict inbox with three-way field merge, offline queue merge UI, per-user notification/view preferences, notification action inbox (pending expenses/due tasks/draft reports) + category card/tabs, dealer/user edit and activate/deactivate, lookups (vehicle models) for EO Admin, searchable selects (Tom Select), in-app preview modal with metadata/OSM panel (image/PDF/HTML), centered app modals, toast/conflict banners, mobile bottom nav, new light navy-tinted app shell, route-based event workspace tabs (overview/plan/tasks/finance/documentation/reports/team), dynamic phases/divisions, task groups/tasks/checklists, dependencies, deadline badges (no timers), progress rollup, interactive Gantt (zoom + drag reschedule), persisted draggable dependency graph with connect/edge-create and critical path, forward-only task updates with note/photo and revision tasks, detailed activity audit log (IP/device for Super Admin), budget versions/items, XLSX import with column mapping, final RAB, expenses + approval, invoices/payments with contract validation, per-event P&L, visitor/result capture, photo documentation with GPS/timestamp/watermark + OSM map, camera-only getUserMedia, PDF/Excel reports with Tiptap narrative, section ordering, HTML preview, dealer portal, in-app notifications, PWA/offline queue, event/division role policies, version-based optimistic locking, integrity dashboard, reconciliation command.
- Verified commands: `composer install`, `npm install`, `php artisan migrate:fresh --seed`, `npm run build`, `php artisan test`, `php artisan serve`, `php artisan star:reconcile`.
- Demo accounts (password `password`): `dev@star.test`, `admin@star.test`, `eo@star.test`, `finance@star.test`, `pm@star.test`, `pic@star.test`, `field@star.test`, `reviewer@star.test`, `dealer@star.test`, `dealer.viewer@star.test`, `dealer.bdg@star.test`, `dealer.sby@star.test`.
- Seeded demo data: 3 dealers, 3 events (Jakarta active, Bandung closed, Surabaya upcoming), tasks, budgets, expenses, invoices, visitors, media, reports, notifications, 12 vehicle-model lookups.

### Known gaps

- Task updates are forward-only; there is no PM/admin status override by design. Corrections use revision tasks.
- Time tracking/timers were removed from the UI and routes; the `time_entries` table and model stay dormant for historical data.
- Deployment files exist under `deploy/` (Nginx, systemd, env example); Redis/Reverb/MySQL are not configured locally and VPS deployment has not been executed.
- Conflict reports record a base snapshot for three-way merge; merge supports Event/Task/Expense fields only.
- Offline merge supports expense/task/checklist/event records via `/conflicts/current` and links to the three-way merge page when a report id is returned.
- Report narrative, checklist, and final RAB conflicts remain reload-only (append-only/computed semantics).
- Dev role can bypass immutable rules for testing; disable `DEV_TOOLS_ENABLED` in production.
- Event/division role policies are enforced server-side and main UI actions are gated; new UI actions must use the `can*Event` helpers.

## Product scope

STAR is an event operations + finance-lite system for EO teams and dealers. It is **not** a full ERP. Build only:

- Events, configurable phases, divisions, event members.
- Task hierarchy: task group -> task -> checklist, plus dependencies, time tracking, Gantt, dependency graph.
- RAB planning/final/realization, expenses, client invoices/payments.
- Image documentation with GPS/timestamp/watermark + OSM map.
- Visitor/result data.
- PDF/Excel reports, publish, dealer portal.
- In-app notifications, audit, RBAC.

Do not add procurement, inventory, payroll, CRM pipeline, marketing automation, full accounting/GL, or generic workflow builders without explicit approval.

## Non-negotiable domain rules

- Hierarchy is fixed: `event -> phase -> task group -> task -> checklist`.
- Parent progress is computed from children and read-only.
- Rollup: task -> task group -> phase -> event, weighted.
- No derived value is entered twice. Event totals, budget actuals, invoice status, and report figures are computed.
- RAB versions: planning -> final. Final is immutable; revisions create a new version.
- Every expense must link to one budget item; unbudgeted expenses use a flagged line.
- Every payment must link to an invoice.
- Published reports are immutable snapshots.
- All writes go through services/actions; use transactions and row locks for approvals.
- Foreign keys, unique constraints, and soft deletes are mandatory.
- A nightly reconciliation job must detect progress/finance/report mismatches.

## Stack

- Laravel 13, PHP 8.3+ locally (8.4 on VPS), SQLite local, MySQL 8 production.
- Tailwind CSS 4, Vite, Blade + vanilla JS (no Livewire installed yet).
- PWA: manifest + service worker + IndexedDB photo queue.
- PDF: `dompdf/dompdf`.
- Excel: `phpoffice/phpspreadsheet`.
- Images: raw GD for watermark/map; `intervention/image` installed.
- Planned production: Redis, Reverb, systemd for queue/Reverb/scheduler.

## Environment and conventions

- Domain: `https://star.alk-tech.my.id`.
- Branding: `STAR — Supported by Alenkosa`; dealer portal uses Honda wordmark.
- Design system: `design.md`. Wordmark + color only; no logo assets yet. Internal = Alenkosa navy; dealer portal = Honda red.
- Timezone Asia/Jakarta, currency IDR, UI Indonesian.
- Images: JPG/JPEG/PNG only. No video.
- Structured imports: XLSX only.
- Attachments: PDF, DOCX, XLSX, JPG, JPEG, PNG.
- OSM tiles + Nominatim, cached; always include attribution.
- GPS and timestamp are mandatory for every evidence photo.
- Watermark is generated server-side.
- Dealer does not see internal realization unless explicitly allowed.
- Demo media lives in `storage/app/private/events/...` and is intentionally not tracked.
- Developer tooling is env-gated: `DEV_TOOLS_ENABLED`, `DEV_IMPERSONATION_ENABLED`, `DEV_ALLOW_RESET` (default off outside local/testing). With dev tools off, `/dev/*` returns 404 and the dev account cannot log in.
- Role `dev` bypasses all role/permission checks and immutable guards (task status, final RAB, published report, approved expense) for testing. Developer activity is hidden from non-dev audit views; impersonated actions are attributed to the target user and only `dev_sessions` (dev-only) records the impersonation.

## Local commands

```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run build
php artisan test
php artisan serve
php artisan star:reconcile
php artisan dev:reset --demo
php artisan dev:reset --empty
```

```bash
php artisan test --filter=StarSmokeTest
```

## Production commands (target)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan optimize
php artisan queue:restart
php artisan reverb:start
```

## Repo layout

- `app/Models` — domain models.
- `app/Http/Controllers` — web controllers, including `GlobalController`, `GlobalSearchController`, `LookupController`, `ExportController`, `ClientController`, `ConflictController`, `DevController`, `EventImportController`/`XlsxImportController`, `TaskUpdateController`, `ActivityLogController`, and `IntegrityController`.
- `app/Console/Commands/DevReset.php` — `dev:reset --demo|--empty`.
- `database/seeders/DevSeeder.php` — developer-only seed for empty reset.
- `app/Services` - progress, task progress/revisions, finance, expense creation, conflict reporting/merge, media/watermark, reports, setup, activity/audit, notifications, dependency graph, XLSX mapping/import, HTML sanitizer.
- `app/Support/PreviewMeta.php` - preview modal metadata builders (file, uploader, GPS/map, context).
- `app/Http/Middleware/AuditRequestContext.php` — request IP/device/route context for audit logs.
- `app/Observers/AuditObserver.php` — model create/update/delete audit trail.
- `database/migrations` - core schema, planning/rundown, report narrative, import batches, graph positions, section order, lock versions, conflict reports, user preferences, lookups, dev sessions.
- `database/seeders/DatabaseSeeder.php` — demo dealer/users/event/RAB/media.
- `database/seeders/DevSeeder.php` - developer account + lookups only (empty reset).
- `resources/views/dev/index.blade.php` - Dev Tools (diagnostics, impersonation, sessions, force/delete, reset).
- `resources/views/layouts/app.blade.php` / `portal.blade.php` - shell, sidebar dev menu, impersonation banner.
- `resources/views/events/workspace.blade.php` - route-based event shell; `events/partials/*` render active tab content.
- `resources/views/components/*` - shared UI: modal, money, status, progress, empty, preview modal, filter chips, icons, merge base snapshot.
- `resources/views/global/*` - global aggregate pages.
- `resources/views/clients/*` - client/dealer list and profile.
- `resources/views/conflicts/*` - conflict inbox, three-way merge diff, and apply form.
- `resources/views/offline/index.blade.php` - IndexedDB queue browser + merge dialog.
- `resources/js/selects.js` - Tom Select enhancement for all selects/autocompletes.
- `resources/js/charts.js` - Chart.js dashboard charts (lazy-loaded).
- `resources/js/offline.js` - offline queue rendering, retry, merge, discard.
- `resources/views/events/partials/timeline.blade.php` - interactive Gantt (zoom, today marker, drag reschedule).
- `resources/views/events/partials/tasks.blade.php` - forward-only update/revision/history UI.
- `resources/js/app.js` - modals, table/bulk filters, toasts, gantt drag reschedule, route progress, offline queue.
- `resources/views/activity-logs/*` — audit log list/detail; IP/device only for Super Admin.
- `resources/js/editor.js` — Tiptap rich-text editor loaded only when `[data-rich-text]` exists.
- `config/audit.php` — audit enable/read-log/retention config.
- `resources/css/app.css` — Tailwind tokens/components; `design.md` is the source of truth.
- `public/sw.js`, `public/manifest.webmanifest` — PWA.
- `tests/Feature/StarSmokeTest.php` — smoke, rollup, finance, imports, graph, report, WYSIWYG, global page tests.
- `deploy/` — Nginx, systemd units, production env example, deploy script, VPS runbook.
- `app/Console/Commands/ReconcileStar.php` — `star:reconcile` nightly reconciliation.

## Before changing the data model

- Read `docs/star-event-ops-plan.md` first.
- Check the cross-module sync map and rollup rules.
- Add migrations + foreign keys + indexes.
- Add/adjust reconciliation checks.
- Update report snapshots if the change affects published figures.
