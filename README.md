# STAR — Event Operations & Finance

STAR is an event operations system for EO teams and dealers: events, tasks, RAB/finance-lite, documentation, reporting, and a dealer portal. Built with Laravel 13, Tailwind 4, SQLite locally, MySQL on the VPS.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Demo accounts

Password for all accounts: `password`. On local/demo environments, the login page also has one-click demo login buttons.

| Role | Email |
|---|---|
| Developer | `dev@star.test` |
| Super Admin | `admin@star.test` |
| EO Admin | `eo@star.test` |
| Finance | `finance@star.test` |
| PM Event | `pm@star.test` |
| PIC Divisi | `pic@star.test` |
| Field Crew | `field@star.test` |
| Reviewer | `reviewer@star.test` |
| Dealer Admin Jakarta | `dealer@star.test` |
| Dealer Viewer Jakarta | `dealer.viewer@star.test` |
| Dealer Admin Bandung | `dealer.bdg@star.test` |
| Dealer Admin Surabaya | `dealer.sby@star.test` |

Seed data includes 3 dealers and 3 integrated events: Jakarta (active), Bandung (closed/published report), Surabaya (upcoming).

## Features

- Global workspace: Dashboard, My Tasks, Events, Clients, Budget, Expenses, Payments, Documents, Reports, Master Data, Integrity, Settings.
- Global search across events, tasks, dealers, expenses, invoices, reports, and documentation.
- CSV export for budget, expenses, payments, reports, and clients (follows active filters).
- Bulk approve/reject for expenses in the global approval inbox.
- Conflict inbox with field-level diff (submitted vs current server values), open/resolved workflow, and reload banner on conflict.
- Offline queue browser with retry, discard, and merge dialog (compare with current server record, apply server version/values).
- Per-user notification preferences; default event view (cards/list) saved from the Events page.
- Searchable selects everywhere (Tom Select) with creatable categories/units/terms; vehicle models managed by EO Admin in Master Data.
- Global create modals for expenses (choose event, dependent budget item) and payments (create invoice, record payment), scoped to the user's event access.
- Three-way per-field conflict merge (base/submitted/server) with automatic non-conflicting resolution and manual choice for disputed fields.
- Compact one-line filter toolbars (search + selects + dates + Filter/Reset) across global pages, wrapping cleanly on smaller screens.
- Dashboard KPIs: Event Aktif, Task Perlu Perhatian, Total PnL, Outstanding Client; performance card uses a Chart.js vertical grouped bar chart with percentage labels for progress vs budget absorption; deadline card lists the nearest deadlines with countdown badges. The dashboard notification card was removed (notifications live in the sidebar inbox/popover).
- Sidebar notification card with aligned category counts (Tugas, Keuangan, Laporan), hover/active states; the collapsed bell opens a compact 216px vertical popover (“Notifikasi {total}” + category counts + “Lihat semua”, no subtitle/latest list). Counts combine unread notifications with an action inbox (pending expense approvals, due tasks, draft reports).
- Collapsible desktop sidebar (42px icon rail, shared icon column, hover tooltips), edge-centered toggle handle, 280ms collapse/expand animation, auto-scroll to the active menu item, and a custom overlay scrollbar shown only on module hover (2px expanded, 1px collapsed).
- Preview modal with a metadata side panel (file info, uploader, event/phase/task, GPS + OpenStreetMap embed).
- Global search field centered in the desktop topbar (max 520px) via a 3-column grid; the burger menu only shows below 1024px.
- Dealer/user edit plus activate/deactivate, with guards for active events and last Super Admin.
- Client/dealer module: profile, event history, contract/invoiced/received/outstanding/P&L, create-event shortcut.
- Per-event P&L (contract value or final RAB as fallback) shown on event cards/list, workspace header, budget overview, and client detail.
- Route-based event workspace tabs: Overview, Plan, Tasks, Finance, Documentation, Reports, Team. Only the active tab renders.
- Task hierarchy: task group → task → checklist, weighted progress rollup, dependencies, deadline countdown badges, interactive Gantt (zoom + drag-to-reschedule), persisted draggable SVG dependency graph with drag-from-handle/connect mode (FS/SS/FF/SF + lag) + critical path.
- Forward-only task updates with mandatory note + optional photo, immutable history, and reviewer/PM revision tasks.
- Detailed activity audit log; EO Admin sees activity, Super Admin additionally sees IP/device/browser/platform.
- RAB planning/final/realization, expenses + approval, client invoices/payments.
- XLSX import with column mapping for RAB, planning, and rundown.
- Photo documentation: GPS + timestamp required, camera-only getUserMedia, server-side watermark + OSM map.
- PWA/IndexedDB offline queue for photos, expenses, and visitor forms.
- Integrity dashboard + reconciliation command: `php artisan star:reconcile`.
- Visitor/lead capture, event results.
- PDF + Excel reports with Tiptap WYSIWYG narrative, drag/drop section ordering, and in-app HTML/PDF preview modal; publish, dealer portal (Honda theme).
- Event/division role policies, version-based conflict checks, and in-app notifications.
- Developer role (`dev@star.test`) with full bypass, real impersonation of any user (banner + return to Developer), Dev Tools (environment, logs, failed jobs, routes, dev sessions, force task status, unpublish report, hard delete, cache/reconcile), and database reseed/reset.
- Developer activity is hidden from non-developer audit logs; impersonated actions are recorded as the target user.
- Env switches: `DEV_TOOLS_ENABLED`, `DEV_IMPERSONATION_ENABLED`, `DEV_ALLOW_RESET`. All default off outside local/testing; when off, dev routes 404 and the dev account cannot log in.

## Dev reset

```bash
php artisan dev:reset --demo    # migrate:fresh + full demo seed
php artisan dev:reset --empty   # migrate:fresh + developer account only
```

## Test

```bash
php artisan test
```

## Docs

- Product/architecture plan: `docs/star-event-ops-plan.md`
- Design system: `design.md`
- Agent instructions: `AGENTS.md`

## Production notes

Target: Ubuntu VPS, PHP 8.4, MySQL 8, Redis, Nginx, Reverb, systemd queue/scheduler. Domain: `https://star.alk-tech.my.id`.
