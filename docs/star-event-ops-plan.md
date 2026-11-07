# STAR — Event Operations & Finance Platform

Status: agreed implementation plan, greenfield (repo empty).
Domain: `https://star.alk-tech.my.id`
Branding: `STAR — Supported by Alenkosa`
Design system: `design.md` (wordmark + color only; Alenkosa navy internal, Honda red dealer portal)

## 1. Product definition

STAR is an event operations system for EO teams and dealers. It covers event execution, task management, RAB/finance-lite, documentation, event results, and reporting. It is not a full ERP.

Primary flow:

`Event -> Phases -> Task Groups -> Tasks -> Evidence -> Review -> Report -> Publish -> Dealer`

Finance flow:

`RAB Planning -> RAB Final -> Expenses -> Realization -> Client Payment -> Financial Report`

## 2. Scope

### In scope

- Event core: events, configurable phases, divisions, members, roles.
- Task management: task groups, tasks, checklists, dependencies, Gantt, S-Curve, dependency graph.
- Progress rollup: checklist -> task -> task group -> phase -> event.
- RAB: planning, final, realization, versioning, XLSX import.
- Expense: recording, receipt attachments, approval, offline entry.
- Client payment: invoices/terms, payments, outstanding/overdue tracking.
- Finance reports: daily during event, final realization, budget vs actual, cash flow.
- Documentation: images only, GPS + timestamp mandatory, watermark + OSM map, camera-only/upload modes.
- Visitor/lead capture and event results.
- Reports: PDF + Excel, versioned, published to dealer portal.
- In-app notifications.
- RBAC, event-level roles, division scope, audit log.
- PWA adaptive: mobile, tablet, desktop; offline queue.
- SQLite local development, MySQL production.

### Out of scope (do not build now)

- Procurement/PO/RFQ, vendor master, vendor scorecard.
- Inventory/assets, warehouse, logistics.
- Payroll/HR/attendance.
- Full CRM pipeline, commission, DMS integration.
- Marketing automation, email/WhatsApp/push.
- Full accounting/GL/tax/banking.
- Multi-tenant SaaS, billing/subscription.
- Generic workflow builder, BI warehouse.
- E-signature.
- Video upload/transcoding.

## 3. Stack and environment

- Laravel 13, PHP 8.4 (Laravel 13 supports PHP 8.3–8.5).
- MySQL 8 on VPS; SQLite for local development/tests.
- Redis for queue/cache/session.
- Tailwind CSS.
- Livewire + Alpine for back office and dealer portal.
- PWA field app: Alpine/Vue + IndexedDB + service worker.
- Laravel Sanctum for field API.
- Laravel Reverb for real-time in-app notifications/progress.
- `spatie/laravel-pdf` v2 + Browsershot/Chromium for PDF.
- `maatwebsite/excel` for XLSX import/export.
- Intervention Image for resize/watermark.
- `spatie/laravel-permission` + `spatie/laravel-activitylog`.
- systemd for queue, Reverb, scheduler.
- Nginx + PHP-FPM + HTTPS.
- OSM tiles + Nominatim reverse geocoding, cached server-side.

## 4. Architecture

Modular monolith. Domain modules:

`Identity`, `Organization`, `Event`, `Task`, `Budget`, `Expense`, `ClientPayment`, `Documentation`, `Visitor`, `Reporting`, `Notification`, `Audit`.

Shared services:

- Organization/tenant scoping.
- Numbering.
- Progress calculation.
- Finance calculation.
- Approval service (fixed approval flows, not a generic builder).
- Document/media service.
- Notification service.
- Audit service.
- Import/export service.
- OSM map/geocode service.

Data integrity principles:

- One source of truth per entity.
- No manually entered derived totals.
- Parent progress is read-only.
- Final RAB, approved report, verified payment are immutable.
- Foreign keys mandatory.
- Service layer only; no direct model writes from controllers.
- Transactions + row locks for approvals.
- Domain events/outbox for recalculation.
- Idempotent recalculation jobs.
- Nightly reconciliation + integrity dashboard.
- Soft delete; no hard delete of referenced data.

## 5. Domain model

### 5.1 Event hierarchy

```
Event
└── Phase
    └── Task Group
        └── Task
            └── Checklist item
```

Tables:

- `events`
- `event_phases` (`event_id`, name, sort_order, weight, status)
- `task_groups` (`phase_id`, name, sort_order, weight, status)
- `tasks` (`task_group_id`, name, sort_order, weight, status, progress)
- `task_checklist_items` (`task_id`, label, is_done, sort_order)

No orphan tasks. No task without phase/group.

### 5.2 Task progress rules

Leaf task:

- With checklist: `done_items / total_items * 100`.
- Without checklist:
  - `todo` = 0
  - `in_progress` = 50
  - `review` = 90
  - `done` = 100
  - `blocked` = 0
  - `cancelled` excluded

Rollup:

- Task group = `Σ(task.weight × task.progress) / Σ(task.weight)` (non-cancelled).
- Phase = `Σ(task_group.weight × task_group.progress) / Σ(task_group.weight)`.
- Event = `Σ(phase.weight × phase.progress) / Σ(phase.weight)`.

Default weight = 1; weight must be > 0. Parent progress is computed and read-only. Parent status derived:

- all children done -> done
- any child blocked -> blocked
- progress > 0 -> in_progress
- otherwise -> todo

Event lifecycle (`draft`, `active`, `closed`) is separate from progress.

### 5.3 Task dependencies and time

- `task_dependencies`: `task_id`, `depends_on_task_id`, type (`finish_to_start`, `start_to_start`, `finish_to_finish`, `start_to_finish`), `lag_minutes`.
- Validate same event, no self-dependency, no cycle (DFS).
- Blocked state derived from predecessors.
- Critical path calculated server-side.
- `time_entries`: retained schema (`task_id`, `event_id`, `user_id`, `started_at`, `ended_at`, `duration_seconds`, `source`, `note`, `is_billable`, `hourly_rate`, `status`, `approved_by`, `approved_at`, `client_uuid`) but the timer feature is retired: no timer UI/routes are exposed and the table stays dormant for historical data.
- Tasks show a countdown badge instead: `Terlambat X hari` (danger), `Hari ini`/`Besok`/`≤3 hari` (warning), `>3 hari` (muted).

### 5.4 RAB and finance

```
Event
└── Budget Version (planning / final)
    └── Budget Item
        └── Expense
```

Tables:

- `budget_versions` (`event_id`, `stage`, `version`, `status`, `parent_id`, approved/immutable fields).
- `budget_items` (`budget_version_id`, `stable_key`, category, description, qty, unit, unit_price, planned_amount, `task_group_id` nullable).
- `expenses` (`event_id`, `budget_item_id` required, `task_id` nullable, date, category, description, amount, vendor text, payment_method, paid_by, receipt, status, approved_by, notes).
- `client_invoices` (`event_id`, dealer, number, term, amount, due_date, status).
- `client_payments` (`client_invoice_id`, amount, paid_at, method, proof, verified_by, status).

Rules:

- `stable_key` is stable across budget versions.
- Final RAB is immutable; revision creates a new version.
- Expenses always link to a budget item. Unbudgeted expenses use a flagged "Unbudgeted" line.
- `committed = sum(expense submitted + approved)`.
- `actual = sum(expense approved)`.
- `paid = sum(expense paid/reimbursed)`.
- `remaining = planned - actual`.
- `variance = actual - planned`.
- `absorption = actual / planned * 100`.
- Invoice balance = invoice amount - sum(verified payments).
- Invoice status derived: `unpaid`, `partial`, `paid`, `overdue`.
- No manual status/total fields.

### 5.5 Documentation

- `media` (`event_id`, `event_phase_id`, `event_requirement_id`, `task_id` optional, path, watermarked_path, mime, size, width, height, caption, `captured_at`, `gps_lat`, `gps_lng`, `gps_accuracy`, `address`, checksum, status, uploaded_by).
- Images only: JPG/JPEG/PNG.
- GPS + timestamp mandatory.
- Capture modes at event/template level: `camera_only` or `upload_or_camera`.
- Camera-only uses getUserMedia + canvas, no gallery.
- Upload mode requires EXIF GPS + timestamp; manual metadata is flagged.
- Server-side watermark: `STAR — Supported by Alenkosa`, time, full address, coordinates, accuracy, event/dealer, small OSM map, attribution.
- OSM tiles cached under `storage/app/osm-tiles`; Nominatim reverse geocode cached.
- Map failure must not fail upload; fallback to coordinates-only block.

### 5.6 Visitors and results

- `visitor_entries`: identity, contact, city/province, source, model interest, budget, payment method, trade-in, test drive, lead status, sales PIC, follow-up date, notes, consent, offline metadata.
- `sales_records`: lightweight unit/SPK/revenue/handover records.
- `event_results`: visitor count, unique visitors, leads, test drives, SPK, units, revenue, handover, conversion, cost per lead, ROI, target vs actual, notes.
- Results derived from visitor/sales where possible; no duplicate manual entry.

### 5.7 Reports

- `report_versions` (`event_id`, version, status, pdf_path, xlsx_path, snapshot JSON, generated_by, approved_by, published_at, checksum).
- Reports snapshot source data; later source changes create a new version.
- Published reports are immutable.

## 6. Roles and approvals

Global roles:

- `super_admin`
- `eo_admin`
- `dealer_admin`
- `dealer_viewer`
- `finance`

Event roles (`event_members`):

- `pm_event`
- `division_pic`
- `field_crew`
- `reviewer`
- `viewer`

Division scope via `event_divisions` and `event_members.division_id`.

Approval defaults:

- RAB planning: Finance -> PM -> EO Admin/Dealer (configurable).
- RAB final: Finance + EO Admin; immutable after approval.
- Expense: Division PIC/Finance -> Finance; threshold escalation to EO Admin.
- Client payment: Finance verifies.
- Report: PM generates -> Reviewer approves -> EO Admin publishes -> Dealer acknowledges.
- Dealer does not see internal realization unless explicitly allowed.

## 7. Cross-module sync map

| Entity | Source of truth | Links |
|---|---|---|
| Event | `events` | phases, tasks, budgets, reports |
| Phase | `event_phases` | task groups, documentation |
| Task Group | `task_groups` | tasks, budget items |
| Task | `tasks` | checklist, time entries, expenses |
| Budget | `budget_versions` | budget items |
| Budget Item | `budget_items` | task groups, expenses |
| Expense | `expenses` | budget item, task, receipt |
| Invoice | `client_invoices` | event, dealer, payments |
| Payment | `client_payments` | invoice |
| Visitor | `visitor_entries` | event, results |
| Result | `event_results` | event, report |
| Media | `media` | event, phase, requirement, task |
| Report | `report_versions` | snapshot of all above |

No derived value is entered twice. Event totals, progress, invoice status, and report figures are computed.

## 8. Media pipeline

1. Capture/upload.
2. Validate type/size.
3. Extract EXIF GPS + timestamp.
4. Store original.
5. Generate report derivative (max 1600px, JPEG 85).
6. Reverse geocode + fetch/cache OSM map.
7. Apply watermark.
8. Store watermarked path + metadata + checksum.
9. Update checklist/progress.
10. Notify.

Offline queue:

- IndexedDB stores image blobs + metadata + `client_uuid`.
- Chunked/resumable upload, retry, max 2–3 parallel uploads.
- Idempotency via `client_uuid`.
- Visitor entries and expense entries also queued.
- Android Background Sync where available; iOS sync on foreground.
- UI shows pending/uploading/failed/retry; clear blobs after sync.

## 9. Import and attachments

- XLSX only for structured imports: RAB, Rundown, Planning.
- Import flow: upload -> select sheet -> validate -> preview -> commit.
- Re-import creates a new version; never overwrite history.
- Attachments: PDF, DOCX, XLSX, JPG, JPEG, PNG.
- Private storage, signed URLs.
- Attachment categories: RAB, planning, rundown, permit, vendor, other.
- Limits: document 25 MB, image 15 MB.

## 10. Reports

PDF:

- RAB planning.
- RAB final.
- Realization (budget vs actual).
- Daily finance during event.
- Expense report with receipt index.
- Client payment status.
- Task progress/effort/Gantt snapshot.
- Event result.
- Consolidated event report with documentation and sign-off.

Excel multi-sheet:

- `00_Ringkasan`
- `01_Profil_Event`
- `02_Checklist_Dokumentasi`
- `03_RAB`
- `04_Rundown`
- `05_Pengunjung`
- `06_Hasil_Penjualan`
- `07_Indeks_Dokumentasi`
- `08_Isu_Evaluasi`
- `09_Log_Submission`
- `10_Task_Timeline`
- `11_Time_Log`
- `12_Dependencies`
- `13_Effort_Summary`

## 11. PWA and UX

- Adaptive: mobile (<640), tablet (640–1024), desktop (>1024).
- Mobile: bottom nav, camera capture, queue badge.
- Tablet: split view, camera, review.
- Desktop: sidebar, grid, drag-drop/bulk upload, report builder.
- Shared offline queue and notifications.
- In-app notification center with unread count and mark-read.
- Notifications: assignment, deadline, upload failure, approval/rejection, report ready, publish, payment due/overdue, time approval, task unblocked, critical path change.

## 12. Deployment

Environment:

```env
APP_URL=https://star.alk-tech.my.id
DB_CONNECTION=mysql
SESSION_SECURE_COOKIE=true
REVERB_HOST=star.alk-tech.my.id
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
```

Nginx:

- `server_name star.alk-tech.my.id`
- root to `public/`
- `client_max_body_size 25m`
- WebSocket proxy for Reverb (`/app`, `/apps`)
- HTTPS + HSTS

Services:

- PHP-FPM 8.4
- MySQL 8
- Redis
- Queue worker(s)
- `php artisan reverb:start`
- Scheduler cron

Storage:

- `storage/app/private` for originals, watermarked images, reports, attachments.
- `storage/app/osm-tiles` for tile cache.
- Nightly MySQL + storage backup off-site.
- Monitor disk, queue length, failed jobs, Reverb, Chromium.

SQLite -> MySQL:

- All schema via Laravel migrations; no engine-specific raw SQL.
- Use `string` for enums, `json` for config, `foreignId()->constrained()`.
- MySQL `utf8mb4` / `utf8mb4_unicode_ci`.
- No fulltext; no engine-specific JSON path queries.
- Test on SQLite; CI job on MySQL 8.
- Move schema + seeders, not the SQLite file.

## 13. Build order

1. Foundation: Laravel, auth/roles, organizations, dealers, event, phases, divisions, members.
2. Task hierarchy: task groups, tasks, checklists, progress rollup, dependencies, Gantt, S-Curve.
3. Budget: versions, items, XLSX import, final lock.
4. Expense: recording, receipts, approval, offline.
5. Client payment: invoices, payments, outstanding.
6. Documentation: capture modes, GPS/timestamp, OSM map/geocode, watermark, offline queue.
7. Visitor/result: form, sales records, event results.
8. Reporting: PDF/Excel, snapshots, publish, dealer portal.
9. Notifications, audit, reconciliation, hardening, deploy.

## 14. Acceptance criteria

- No task without phase/group; no expense without budget line; no payment without invoice.
- Parent progress always equals child rollup; parent progress is read-only.
- Event budget/actual/outstanding always equal source sums.
- Final RAB and published reports are immutable.
- Offline capture syncs without duplicates.
- Every photo has GPS, timestamp, watermark, and map.
- Dealer sees only permitted data.
- Reconciliation job reports zero mismatches.
- SQLite local and MySQL production both migrate/seed cleanly.
- Reports generate from snapshots, not live re-entry.

---

# Proposed AGENTS.md (to apply after Plan mode)

# STAR — AGENTS.md

## Current state

- Greenfield repo. No app code, manifests, or CI yet.
- Agreed plan lives at `docs/star-event-ops-plan.md`.
- Do not assume commands exist until the Laravel app is scaffolded.
- Rewrite this file with real commands after scaffold.

## Product scope

STAR is an event operations + finance-lite system, not a full ERP. Build only:

- Events, configurable phases, divisions, event members.
- Task hierarchy: task group -> task -> checklist, with dependencies, Gantt, S-Curve, graph.
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

## Planned stack

- Laravel 13, PHP 8.4, MySQL 8 production, SQLite local.
- Redis, Tailwind, Livewire + Alpine back office.
- PWA field app with IndexedDB offline queue (Alpine/Vue), Sanctum API.
- Reverb, spatie/laravel-pdf + Browsershot, maatwebsite/excel.
- Intervention Image, spatie/laravel-permission, spatie/laravel-activitylog.
- systemd for queue/Reverb/scheduler.

## Environment and conventions

- Domain: `https://star.alk-tech.my.id`.
- Branding text: `STAR — Supported by Alenkosa`.
- Timezone Asia/Jakarta, currency IDR, UI Indonesian.
- Images: JPG/JPEG/PNG only. No video.
- Structured imports: XLSX only.
- Attachments: PDF, DOCX, XLSX, JPG, JPEG, PNG.
- OSM tiles + Nominatim, cached; always include attribution.
- Camera-only mode must use getUserMedia, not a file input.
- GPS and timestamp are mandatory for every evidence photo.
- Watermark is generated server-side.

## Planned local commands (verify after scaffold)

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run dev
php artisan serve
```

## Planned production commands (verify after scaffold)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan optimize
php artisan queue:restart
php artisan reverb:start
```

## Before changing data model

- Read the plan file first.
- Check the cross-module sync map.
- Add migrations + foreign keys + indexes.
- Add/adjust reconciliation checks.
- Update report snapshots if the change affects published figures.

## When scaffolded

Replace the "Current state" and planned commands with verified commands, and add the real repo layout, test commands, and deployment steps.

## 15. UI/UX architecture (implemented)

- Global sidebar with real aggregate pages: Dashboard, My Tasks, Budget, Expenses, Payments, Documents, Reports, Master Data, Integrity, Settings.
- Event workspace uses route-based tabs: `/events/{event}/overview`, `/plan`, `/tasks`, `/finance`, `/documentation`, `/reports`, `/team`. Only the active tab renders.
- Sub-tabs: Tasks (Board/Timeline/Graph), Finance (Budget/Expenses/Payments).
- Progressive disclosure: create/edit forms live behind buttons/details panels; overview pages contain no forms.
- Report narrative uses Tiptap WYSIWYG with server-side HTMLPurifier sanitization.
- Reports support drag/drop section ordering; preview and PDF follow the saved order.
- Conflict handling uses `lock_version` optimistic locking on events, tasks, checklists, budgets, expenses, and reports.
- Role-aware UI uses `can*Event` helpers; server authorization remains mandatory.

## 16. Task update, revision, and audit

- Task/checklist updates are append-only and forward-only.
- Each update requires a note; photo is optional.
- Allowed actors: task PIC, event field crew, or matching division PIC.
- PM, EO Admin, Super Admin, and Reviewer cannot override task status.
- Reviewer/PM corrections create a linked revision task (`revision_of_id`), not a backward status change.
- `task_updates` stores from/to status, note, photo, user, event, and phase.
- Activity audit uses `activity_logs` with old/new values, IP, user agent, device, browser, platform, route, method, status, duration, request/session IDs.
- EO Admin can view audit activity; Super Admin additionally sees IP/device/request details.

## 17. UI/UX v2 (implemented)

- Event creation via centered modal; card/list view modes.
- Sticky one-screen sidebar with gradient treatment and pinned footer.
- Contract value and per-event P&L.
- Expense create/edit modals with budget recalculation.
- Invoice creation modal with contract/RAB sync and remaining validation.
- All previews and forms centered in dialogs.
- Filter bars on tables; sticky table headers; removable filter chips.
- Client/dealer module with profile, event history, financial summary, and create-event shortcut.
- Global search across modules; CSV exports that follow filters; bulk expense approval.
- Toast feedback, conflict reload banner, route progress bar, mobile bottom nav, empty states.
- Jira-like Gantt: zoom levels, today marker, weekend shading, sticky task column, click-to-edit modal, and S-Curve (planned vs actual).
- Master Data can create dealers/users (Super Admin); Settings covers profile, password, app info, and EO Admin auto-numbering formats for event/invoice/SPK/report.
- Auto-numbering uses locked `number_sequences`, configurable token formats, and collision skipping; report numbers appear on the report list, PDF cover, portal, and filenames.
- Dynamic UI/PDF watermark uses `app_settings` and is configured in Dev Tools; PDF snapshots keep the watermark context captured at generation time.

## 18. Remaining backlog

- VPS deployment execution (config exists under `deploy/`).
- Optional automatic three-way merge for version conflicts (current flow is manual via the offline queue).
- Optional notification delivery channels (email/WhatsApp) on top of in-app preferences.

## 19. Conflict workflow (implemented)

- `conflict_reports` stores every optimistic-lock rejection: user, event, model/type, expected vs actual version, submitted payload, server snapshot, route/URL, resolved state.
- `/conflicts` inbox with filters, open/resolved workflow, nav badge; `/conflicts/{id}` shows a field-level diff.
- Conflict banner links to the exact report and offers reload.
- `/conflicts/current` exposes the current server record (whitelisted types) for the offline merge UI.
- `/offline` browses the IndexedDB queue, retries, discards, and merges with the server record.
- Notification preferences (`notify_tasks`, `notify_expenses`, `notify_reports`, `notify_approvals`) are enforced by `NotificationService`; default event view (`cards`/`list`) is applied on `/events`.
- Master Data supports creating, editing, activating, and deactivating dealers/users with guards (active events, last Super Admin, self-deactivation).

## 20. UI v3 and workflow upgrades (implemented)

- Searchable selects across all forms/filters (Tom Select), with creatable categories, units, invoice terms, and remote lookups (events, users, vendors, cities, vehicle models, invoices, budget items).
- Vehicle models are DB-backed (`lookups`) and managed by EO Admin/Super Admin in Master Data.
- Global create modals: expense (event + dependent budget item) and payments (invoice + payment), scoped by event access.
- Three-way conflict merge: base/submitted/server snapshot, automatic resolution for one-sided changes, manual choice for disputed fields, audit-logged apply.
- Preview modal with metadata panel (file info, uploader/role, event/phase/task, GPS + OpenStreetMap embed).
- Event list default view is stored from the Events page, not Settings.
- New app shell: light navy-tinted sidebar (gradient, SVG icons, section labels, active pill/bar, promo card, user block), topbar search with Ctrl K, white rounded content surface.
- Dashboard: independent main/side columns, 4 KPI cards (Event Aktif, Task Perlu Perhatian, Total PnL, Outstanding Client), Chart.js vertical grouped bars for progress vs budget absorption with percentage labels, finance comparison chart (Pendapatan/Expenses bars, PnL/Piutang lines) with series toggles, finance summary (Nilai Project/omzet, Sudah Dibayar, Total Expense, Total PnL), task doughnut, per-event progress, agenda/reminders, active events, team activity, and a nearest-deadline list with countdown badges; metrics scoped to the user's visible events. No notification card and no time tracker.
- Realtime: Laravel Reverb broadcasts `UserNotificationCreated` on the user's private channel after commit (queued); Echo updates badge counts and shows a toast. Server-side broadcasts go to `REVERB_API_HOST` (local Reverb), while the browser connects through the public `REVERB_HOST`. `BROADCAST_CONNECTION=reverb` requires the Reverb service and nginx WebSocket proxy.

## 21. Remaining backlog

- VPS deployment execution (config exists under `deploy/`).
- Merge support for report narrative and budget meta (currently reload-only).
- Email/WhatsApp notification channels on top of in-app preferences.

## 22. Filter, sidebar, and notification refinements (implemented)

- All global filter forms use compact one-line toolbars (search + selects + date range + Filter/Reset) at desktop, wrapping cleanly below 1280px. Activity Log keeps all fields in a tidy two-row grid.
- Tom Select wrapper border duplication is removed so controls align with native inputs and buttons.
- Sidebar: install-app promo removed; Notifications moved to the bottom card with category counts (Tugas, Keuangan, Laporan); scrollbar is hidden until hover, 4px, navy, without arrows.
- Sidebar notification card uses aligned tabular counts and hover/active category states; collapsed bell opens a compact 216px vertical popover with inline total next to “Notifikasi” + Tugas/Keuangan/Laporan + “Lihat semua” (no subtitle/latest list, no close icon).
- Desktop sidebar is 188px expanded and collapses to a 42px icon rail (persisted in localStorage) with centered icons and hover tooltips; the toggle is a circular handle centered on the right edge. Brand, nav, notification and profile icons share one column; the active item auto-scrolls into view. The custom overlay scrollbar appears only while hovering module items (2px expanded, 1px collapsed) and its thumb follows the nav scroll position. Collapse/expand uses a 280ms width animation with a final-state fallback.
- Notifications page gains category tabs (Semua/Tugas/Keuangan/Laporan) and a “Perlu tindakan” action inbox derived from pending expense approvals, tasks due within 7 days, and draft reports ready to publish; stored unread notifications are combined into the counts.

## 23. Developer role and Dev Tools (implemented)

- Added `dev` role with env-gated full access, real user impersonation, and Dev Tools.
- Impersonation: real login as target, sticky banner, return-to-dev, `dev_sessions` history (dev-only).
- Force actions: task status any direction, final RAB item editing, published report editing/unpublish, approved expense editing, hard delete, cache/reconcile, reseed demo, empty reset.
- Audit: dev actor rows hidden from non-dev viewers; impersonated actions recorded as target user.
- Reset commands: `php artisan dev:reset --demo` and `php artisan dev:reset --empty`; UI confirmations `RESEED` / `DELETE ALL`.
