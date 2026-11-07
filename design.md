# STAR — Design System

Status: agreed design direction. Branding is **wordmark + color only** for now. No logo assets yet.

## 1. Brand architecture

Two themes, one design system:

| Area | Brand | Theme |
|---|---|---|
| Back office, field app, internal reports | `STAR — Supported by Alenkosa` | Alenkosa Navy |
| Dealer portal, dealer-facing reports | `Honda` + dealer name | Honda Red |

Rules:

- Do not invent or approximate a logo. Use text wordmarks.
- Keep one component system; only brand tokens and wordmarks change.
- Official logos can be dropped in later without changing layout.

## 2. Wordmark

**Internal**

```
STAR
SUPPORTED BY ALENKOSA
```

- `STAR`: Plus Jakarta Sans, 700, uppercase, tracking `-0.02em`.
- `SUPPORTED BY ALENKOSA`: 11px, uppercase, tracking `0.08em`, muted.
- Color: Navy `#0A2540` on light surfaces; white on navy.

**Dealer portal**

```
Honda
[Dealer Name]
```

- `Honda`: 700, uppercase or title case, Honda Red `#CC0000`.
- Dealer name: 600, Ink `#111111`, smaller.
- No star symbol, no outline, no gradient, no stretched text.

## 3. Color tokens

### Shared semantics

| Token | Value | Usage |
|---|---|---|
| success | `#15803D` | Done, paid, approved |
| warning | `#B45309` | Pending, due soon |
| danger | `#B91C1C` | Overdue, rejected, blocked |
| info | `#2563EB` | Neutral information |
| chart-sand | `#D9C7A7` | Chart tertiary |
| chart-gray | `#CBD5E1` | Chart neutral |

Status must always have icon + label, never color alone.

### Alenkosa Navy theme

| Token | Value | Usage |
|---|---|---|
| bg | `#F7F6F2` | App canvas |
| surface | `#FFFFFF` | Cards, tables, forms |
| ink | `#0F172A` | Primary text |
| brand | `#0A2540` | CTA, active nav, links, main chart |
| brand-hover | `#071C33` | Hover/pressed |
| brand-soft | `#E9EFF7` | Active background, neutral badge |
| sidebar | `#0A2540` | Desktop sidebar |
| sidebar-hover | `#133A5E` | Sidebar hover/active |
| chart-2 | `#4F7CAC` | Secondary chart series |
| border | `#E5E7EB` | Cards, dividers |
| muted | `#64748B` | Labels, metadata |

Danger for this theme: `#B91C1C`.

### Honda theme

| Token | Value | Usage |
|---|---|---|
| bg | `#FFFFFF` | Portal canvas |
| surface | `#FFFFFF` | Cards |
| ink | `#111111` | Primary text |
| brand | `#CC0000` | Header, CTA, active state |
| brand-hover | `#A80000` | Hover/pressed |
| brand-soft | `#FDECEC` | Soft badge/active |
| chart-2 | `#1F1F1F` | Secondary chart series |
| border | `#E5E5E5` | Cards, dividers |
| muted | `#6B7280` | Labels, metadata |

Danger for this theme: `#7F1D1D` (maroon, distinct from Honda Red).

### Brand color rules

- Brand color only for primary action, active navigation, links, and main chart series.
- Do not use brand color for large content backgrounds.
- Do not use brand color for success/warning/danger.
- Honda Red is primary, not error. Danger uses maroon + icon.
- Red/navy buttons always use white text.

## 4. Typography

- UI font: **Plus Jakarta Sans**.
- Numeric tables: tabular numerals, right-aligned.
- Currency: `Rp1.250.000`.
- Dates: `04/11/2026` or `4 Nov 2026` consistently.
- Timezone: Asia/Jakarta, label `WIB` when time matters.

Scale:

| Role | Size | Weight |
|---|---|---|
| Display / greeting | 28–32px | 700 |
| Page title | 24px | 700 |
| Section title | 18px | 600 |
| Body | 14px | 400/500 |
| Label | 12px | 500/600 |
| Meta | 12px | 400 |

Do not use more than one display font. Do not use all-caps for long text.

## 5. Layout

### App shell — back office

- Sidebar 248px, collapsible to 72px icon rail.
- Topbar 64px.
- Content max-width 1440px, padding 24px.
- 12-column grid, 8px spacing base.

Sidebar groups:

```
Workspace
  Dashboard
  My Tasks
  Events
Finance
  Budget
  Expenses
  Payments
Documentation
  Documents
  Reports
  Notifications
Admin
  Master Data
  Integrity
  Settings
```

Rules:

- Every sidebar item has a real global page, not a link to an arbitrary event.
- Global pages aggregate across events and respect `Event::visibleTo(user)`.
- Badges show counts: pending expenses, my open tasks, unread notifications.
- Sidebar collapses to icon rail on tablet.
- Dealer users do not see internal finance/admin items; they use the dealer portal.

Active item: `brand-soft` background + left brand bar.

### Event workspace

Header:

- Event name, dealer, dates, location, status.
- Progress ring.
- Budget health.
- Primary action.

Pill tabs are real routes, not anchors:

```
/events/{event}/overview
/events/{event}/plan
/events/{event}/tasks
/events/{event}/finance
/events/{event}/documentation
/events/{event}/reports
/events/{event}/team
```

Only the active tab is rendered. The browser back/refresh keeps the same tab.

Sub-tabs:

- Tasks: `Board | Timeline | Graph`
- Finance: `Budget | Expenses | Payments`

Rules:

- Tabs use the brand color for active state.
- On mobile, tabs scroll horizontally.
- No page may render all modules at once.
- Event header is persistent across tabs.

### Field app

- Bottom nav: Home, Capture, Tasks, Expenses, Sync.
- Large Capture CTA.
- Full-screen camera.
- Persistent offline/sync indicator.

### Dealer portal

- Topbar only; no sidebar.
- Honda wordmark + dealer name.
- Nav: Reports, Payments, Notifications.
- Simple cards, large download/acknowledge actions.

### Page templates

Use five templates consistently:

| Template | Use |
|---|---|
| Dashboard | KPI, chart, list, alerts, activity |
| List | Header, filters, table/cards, pagination, create action |
| Detail | Header, tabs, content, side panel |
| Wizard | Create event: Info → Template/Phases → Team & Finance → Review |
| Settings | Grouped form, sticky save |

### Progressive disclosure

- Default page shows summary and actions, not every form.
- Create/edit forms open from a button, modal, drawer, or dedicated page.
- Tables show key columns; detail opens on click.
- Task detail, expense detail, and report detail open in a drawer or detail page.
- Event Overview has no forms.
- Import forms open from an "Import" action.
- Max 3 primary actions per screen.

## 6. Components

- **Button**: primary, secondary, ghost, danger; 40–44px height.
- **Input**: label above, hint/error below; currency and date variants.
- **Status badge**: icon + label + color.
- **KPI card**: label, value, delta, optional sparkline.
- **Progress**: ring for event, bar for task group/phase.
- **Data table**: sticky header, filter, bulk action, pagination; cards on mobile.
- **Photo card**: thumbnail, GPS/time badge, sync status, caption.
- **Approval panel**: side drawer with approve/reject + note.
- **Offline banner**: global, with pending count.
- **Sync drawer**: per-item status, retry, conflict.
- **Notification center**: bell, unread count, filters.
- **Empty state**: reason + next action.
- **Command palette**: desktop only.
- **Chart**: brand + chart-2 + sand/gray; status colors only for status.
- **Rich text editor**: Tiptap toolbar (bold, italic, headings, lists, link, quote, undo/redo); sanitized HTML output.
- **Conflict modal**: server value vs local value vs base; choose keep server, keep mine, or merge.
- **Page header**: title, breadcrumb, meta, primary action.
- **Filter bar**: search, status, date, reset; collapses into a sheet on mobile.
- **Toast**: success/error, non-blocking.
- **Preview modal**: images, PDF, and HTML reports open in an in-app dialog, not a new browser tab.
- **Task update form**: forward-only status, mandatory note, optional photo; immutable history.
- **Revision form**: reviewer/PM creates a linked revision task instead of changing status backward.
- **Audit log**: EO Admin sees activity; Super Admin additionally sees IP, device, browser, platform.

## 7. Key screens

| Screen | Purpose | Key elements |
|---|---|---|
| Dashboard | Overview | Greeting, 4 KPI, budget chart, task progress, quick actions, upcoming events, activity |
| Events | Browse/filter | Cards/table, dealer, date, progress, budget health |
| Event workspace | Run event | Header, tabs, progress, alerts |
| Tasks | Execute | Board/list/Gantt/S-Curve/graph, filters, task drawer |
| Budget | Control RAB | Table, version selector, variance, finalize |
| Expenses | Record spend | Quick entry, receipt, approval |
| Payments | Track client | Invoice, aging, record payment |
| Documentation | Evidence | Phase checklist, photo grid, completeness |
| Reports | Publish | Version list, preview, generate, publish |
| Field capture | Collect | Camera/upload, checklist, GPS/time, sync |
| Dealer portal | Receive | Reports, payments, notifications, acknowledge |

## 8. UX rules

- Event context is always visible.
- One primary action per screen.
- Show progress and budget health together.
- Offline is a state, not an error.
- Every empty state has a next action.
- Destructive actions require confirmation.
- Approvals happen in a side panel, not a new page.
- Long forms are split into steps; autosave drafts.
- Never hide sync failures.
- No form is visible until the user opens it.
- No page renders every module; use route-based tabs.
- Every tab has a URL and survives refresh.
- Detail views open on demand; lists stay scannable.
- Previews (image/PDF/HTML report) open in a modal; do not force a new tab.
- Conflict must never silently overwrite money, final RAB, or published reports.
- Task progress only moves forward and only by PIC/field/division PIC; corrections use revision tasks.
- Every task/checklist update requires a note; photo is optional.
- Audit log is append-only; IP/device details are Super Admin only.
- New UI actions must use `can*Event` permission helpers.

## 9. Responsive

| Breakpoint | Behavior |
|---|---|
| Desktop >1024 | Sidebar, 12-col grid, dense tables |
| Tablet 640–1024 | Collapsible sidebar, 2-col cards, split view |
| Mobile <640 | Bottom nav, stacked cards, filter sheet, full-screen capture |

- Touch targets minimum 44px.
- Tables become cards on mobile.
- No horizontal scrolling for primary data.

## 10. Accessibility

- WCAG AA contrast.
- Visible focus ring using brand color with white offset.
- Keyboard navigation for all actions.
- Labels tied to inputs; errors near fields.
- Status conveyed by icon + text, not color only.
- Respect `prefers-reduced-motion`.
- UI language: Indonesian.

## 11. Motion

- 150–200ms, ease-out.
- Use motion for feedback: sync, progress, panel open.
- No decorative animation, no bouncing cards, no parallax.

## 12. Report branding

- Internal report cover: Alenkosa Navy.
- Dealer-facing report cover: Honda Red/White/Black + dealer name.
- Footer on both: `Disiapkan oleh STAR — Supported by Alenkosa`.
- Evidence watermark stays `STAR — Supported by Alenkosa` for authenticity.
- Excel headers follow the same theme as the report.

## 13. Implementation

- Use Tailwind + CSS variables.
- Theme via `<html data-theme="alenkosa">` or `data-theme="honda"`.
- Components consume tokens only; no hardcoded hex.
- Route/guard decides theme; dealer portal uses Honda.
- Wordmark component supports `alenkosa` and `honda` variants.
- Leave a logo slot for future assets without changing layout.
- Candidate dealer portal path: `/portal`; subdomain can be added later.

## 14. Do / Don't

Do:

- Use generous whitespace with dense data.
- Use tabular numbers for money and progress.
- Keep brand color for action and identity.
- Use icons with labels for status.
- Keep one component system across both themes.

Don't:

- Use gradients, glassmorphism, or generic SaaS purple.
- Approximate a Honda logo.
- Use Honda Red for danger.
- Use navy for large card backgrounds.
- Use color alone to communicate status.
- Add more than three charts to the dashboard.

## 15. UI/UX v2 patterns

### Sidebar
- Sticky one-screen shell: `h-screen`, header fixed, nav scrolls independently, footer pinned to bottom.
- Navy gradient with subtle radial/grid texture; active item is a rounded pill.
- Icons sit in rounded containers; counts use compact badges.
- Collapsed icon rail on tablet; drawer on mobile.

### Modals
- All create/edit/preview flows use centered dialogs.
- Sizes: sm, md, lg, xl. Header/body/footer with sticky footer actions.
- Backdrop click and Esc close; focus stays inside.
- Validation errors reopen the relevant modal.

### Tables
- Every table has a filter bar: search, status, date range, dealer/event where relevant.
- Filters persist in query strings; pagination keeps filters.
- Table wrapper scrolls; `thead th` is sticky.
- Mobile tables become cards.

### Client/Dealer module
- `/clients` lists dealers with event count, contract, invoiced, outstanding, P&L.
- `/clients/{dealer}` shows profile, financial summary, event history, reports, and activity.
- Create Event can be launched from a dealer profile with the dealer preselected.

### Event P&L
- `contract_value` is the commercial baseline; fallback to final RAB.
- EO P&L = contract value - approved expenses.
- Also show invoiced, received, outstanding, and margin.
- P&L appears on event cards, list rows, event overview, finance tab, client detail, and budget overview.

### Event list
- Two modes: Cards and List rows.
- List rows are full-width horizontal cards with all key details; preference persists.

## 16. Navigation, feedback, and data actions

### Global search
- Single search field in the topbar, results page groups hits by module (events, tasks, dealer, expenses, invoices, reports, documentation).
- Type filter narrows groups; each result shows a status badge and context.

### Feedback
- Flash success/error render as dismissible toasts; validation errors stay inline.
- Version conflicts show a dedicated amber banner with a reload action.
- Navigation shows a thin top progress bar; offline queue badge and conflict badge live in the topbar.

### Tables and filters
- Filter bars submit via query string; active filters render as removable chips with a reset-all link.
- Bulk actions live above tables with a count, action select, and apply button; only eligible rows get checkboxes.
- CSV export mirrors the active filters.

### Mobile
- Bottom navigation (5 items) for internal users and 2 items for dealer portal; main content keeps bottom padding.
- Tables scroll horizontally; key cards stack to one column.

### Gantt
- Zoom: auto, day, week, month.
- Sticky task column, month + day/week header, phase/group headers, weekend shading, today marker, status colors, per-bar progress fill.
- Clicking a bar or row opens a detail modal (name, description, start, due, priority, assignee). No drag-reschedule.
- S-Curve view: planned (task weight accrued from start to due) vs actual (completed task weight), with current completion, today's plan, variance, and task counts.

## 17. Conflict and offline merge flows

### Conflict inbox
- Every rejected optimistic-lock write is stored server-side with model, record id, user, event, expected/actual versions, submitted payload, and server snapshot.
- `/conflicts` lists open/resolved reports with filters and a nav badge for open items (own items for non-admin, all for admin).
- Detail page shows a field-by-field diff (submitted vs server) and the raw JSON payloads.
- Mark resolved / reopen are explicit actions; resolving never changes the underlying record.

### Offline queue
- `/offline` renders IndexedDB entries client-side: label, endpoint, age, attachment count, and conflict reason.
- Actions: Kirim Ulang (single), Kirim Semua (non-conflicted), Hapus (with confirm), Gabung.
- Gabung fetches the current server record via `/conflicts/current` and shows a diff table.
  The user can apply the server lock version, copy all server values, then save back to the queue and resend.
- Validation failures (422) drop the entry with a message; conflicts (409/412/423) stay queued and remain flagged.

## 18. App shell v2 (light navy)

- Shell background `#eef2f7`; content is a white rounded card with soft shadow (desktop >=1024px), full-bleed on mobile.
- Sidebar: light navy gradient `#e9f0f8 -> #dfe9f5`, 188px expanded on desktop (30% narrower than 268px), rounded 24px, brand mark + STAR wordmark, uppercase section labels (Menu, Keuangan, Dokumentasi, Sistem, Admin, Umum).
- Nav items: 19px line icons, 13px labels, active = soft pill `#cfdeee` + 3px navy bar, count badges (all brand navy), hover tint.
- Desktop collapse toggle: 42px icon rail, labels/section text hidden, badges repositioned, icons centered, custom tooltip on hover; state persisted in `localStorage`. The toggle is a circular handle centered on the sidebar’s right edge.
- Brand mark, module icons, notification bell, and profile avatar share the same left icon column when expanded and the same center line when collapsed.
- Sidebar auto-scrolls to the active nav item on load, drawer open, and after collapse/expand.
- Notification card: grid-aligned tabular counts and category hover/active states. The compact (216px) popover opens only from the collapsed bell trigger with header “Notifikasi {total}” and vertical rows (Tugas/Keuangan/Laporan) + “Lihat semua” (no subtitle/latest list). Popover closes via outside click/Esc (no close icon).
- Sidebar scrollbar is a custom overlay (native scrollbar hidden), appearing only while hovering module items, navy thumb, no arrow buttons: 2px when expanded, 1px when collapsed.
- Collapse/expand uses a 280ms width animation (Web Animations API with a 340ms final-state fallback), plus brand/nav/toggle/chevron transitions.
- Bottom: notification card (total unread + Tugas/Keuangan/Laporan counts + “Lihat semua”) and user block with role + logout.
- Topbar: search pill centered on desktop (3-column grid, max 520px) with Ctrl/Cmd+K hint, avatar with name/email; no notification icon (notifications live in the sidebar card/mini bell); the burger button only appears below 1024px.
- Dealer portal uses red-tinted surface/sidebar tokens; semantic success/warning/danger colors stay consistent.

## 19. Searchable inputs

- Tom Select is applied to every `<select>` (including short enums and table filters) and to autocomplete inputs.
- Creatable selects for categories, units, invoice terms; remote lookups for events, users, vendors, cities, vehicle models, and invoices.
- Dropdown is appended to the nearest dialog so it paints above modal top layer; native select value stays in sync for form/offline submission.
- Required selects validate on submit with a friendly message instead of relying on hidden native validation.

## 20. Dashboard v2

- Layout: KPI row (4: Event Aktif, Task Perlu Perhatian, Total PnL, Outstanding Client), then independent main/side columns so cards keep their natural height instead of stretching to the tallest card.
- Main column: performance chart (with avg progress/absorption/event stats), progress per event + status donut, finance comparison chart, activity log. Side column: agenda, active events, deadlines, team activity.
- Charts use Chart.js (lazy-loaded only on dashboard): vertical grouped bars for progress vs budget absorption with percentage labels above each bar (y-axis capped at 100%), doughnut for task status, and a finance comparison chart (bars for Pendapatan/Expenses, lines for PnL/Piutang) with toggle chips per series; toggle state persists in `localStorage`. Finance summary shows Nilai Project (omzet), Sudah Dibayar, Total Expense, and Total PnL.
- Deadline card lists the four nearest open tasks with countdown badges (`Terlambat X hari` danger, `Hari ini` / `Besok` / `≤3 hari` warning, `>3 hari` muted). No time tracker.
- Realtime via Laravel Reverb + Echo: each user subscribes to `private-App.Models.User.{id}`; new notifications update sidebar/popover counts instantly and show a dismissible navy-accent toast bottom-right (8s, clickable to the notification list). Broadcasts are queued after commit and sent to the local Reverb API (`127.0.0.1`), while the browser uses the public WSS endpoint; without a Reverb connection the UI behaves as before.
- All figures are scoped to events the user can see; dealer users are redirected to the portal.

## 21. Preview metadata panel

- Dialog split: preview stage (left) + metadata column (right); stacks on mobile.
- Metadata groups: File, Waktu & lokasi, Unggahan, Konteks; optional OpenStreetMap iframe with marker + attribution when GPS exists.
- Values are inserted with `textContent` to avoid XSS.

## 22. Three-way merge UX

- Base / Dikirim / Server / Hasil columns with badges: "Pakai dikirim", "Pakai server", or radio choice for disputed fields.
- Auto-resolved rows are read-only; disputed rows highlight and must be chosen before apply.
- Conflicts without base snapshot show the legacy two-column diff and a reload-only explanation.


## 23. Filter toolbars and notification categories

### Filter toolbar
- One row at >=1280px: search (flex 2, min 180px), selects (min 120px), date fields (130px), actions pinned right (Filter + Reset).
- Below 1280px fields wrap into tidy rows with equal heights (38px) and aligned labels; Activity Log uses two explicit rows (main filters, then date range + actions).
- Controls use 12px text and 38px height so native inputs, Tom Select controls, and buttons align on one baseline.
- Tom Select wrapper resets border/padding/background (Tom Select adds an `.input` state class) so only `.ts-control` draws the border.
- Placeholder hides on focus/open when the value is empty and returns on close; empty `<option value="">` items are treated as placeholders, not selected values.
- Remote selects/autocomplete inputs preload options on focus and show loading/empty states.

### Notifications
- Sidebar card replaces the install-app promo: total unread + Tugas/Keuangan/Laporan counts, each linking to `/notifications?type=...`.
- Counts are an **action inbox** derived from domain state (pending expense approvals, tasks due within 7 days, draft reports ready to publish) plus stored unread notifications.
- Notifications page tabs: Semua, Tugas, Keuangan, Laporan, each with count; a “Perlu tindakan” section lists actionable rows with Review/Buka/Publish links.
- Expense approval/rejection notifies the creator; expense submission is surfaced through the action inbox instead of a duplicate stored notification. Preferences still filter stored notifications.

### Sidebar scrollbar
- Custom overlay scrollbar (native scrollbar hidden), navy thumb, no arrow buttons.
- Track appears only while hovering module items; thumb is absolutely positioned and follows `scrollTop` (2px expanded, 1px collapsed).
- `min-height: 0` on the nav keeps it as the internal scroll container.

## 24. Developer role and impersonation

- Role `dev` (label “Developer”) is a dev-only superuser: bypasses event/module authorization and immutable guards (task status, final RAB, published report, approved expense) for testing.
- Env gates: `DEV_TOOLS_ENABLED`, `DEV_IMPERSONATION_ENABLED`, `DEV_ALLOW_RESET`; when disabled the `/dev/*` routes return 404 and the dev account cannot log in.
- Real impersonation: Developer selects any active user, session switches to that user, sticky banner shows “Mode simulasi” with a return button. Impersonation start/stop is stored in `dev_sessions` (developer-only), not in the activity log.
- Developer Console: sectioned subnav (Ringkasan, Masuk Sebagai, Maintenance, Data, Sinkronisasi, Log, Fitur, SQL, Nomor, Watermark) with environment/drivers, table counts, failed jobs, latest log tail, route list, impersonation panel, dev sessions, read-only SQL runner, numbering/watermark settings, force task status, unpublish report, hard delete by type/ID, cache/reconcile actions, reseed demo, and empty reset. Below 768px the subnav becomes one horizontal scroll row with a persistent navy scrollbar.
- Reset modes: `RESEED` runs `dev:reset --demo`; `DELETE ALL` runs `dev:reset --empty` (schema + dev account + lookups only). Both force logout and show a notice on the login page.
- Audit visibility: activity log rows whose actor is `dev` are hidden from non-developers; developers see them. Impersonated actions are attributed to the target user (and remain visible as that user). Event/workspace/dashboard activity feeds apply the same scope.

## 25. Auto-numbering

- Settings ? Penomoran Otomatis (EO Admin/Super Admin/dev) controls formats for event, invoice, SPK, and report.
- Tokens: `{prefix}`, `{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{seq}`, `{seq:4}`, `{event}`, `{dealer}`, `{random:4}`; format must contain `{seq}`.
- `number_sequences` holds locked counters per type + scope; existing numbers are skipped on collision.
- Invoice/report sequences are per event when the format includes `{event}`, otherwise per period; event codes are per month, SPK per event.
- Report numbers appear in the reports list, preview/PDF cover, portal, and downloaded filenames; old reports fall back to `v{version}`.

## 26. Dynamic watermark

- Developer Console controls `watermark.enabled`, `watermark.login_enabled`, `watermark.line1`, `watermark.line2`, `watermark.opacity`, `watermark.color`, `watermark.speed`, and `watermark.font_size` in `app_settings`, plus Halus/Standar/Tegas presets.
- Line 1 defaults to `Copyright of Alenkosa - Your IT Solutions`; line 2 supports `{ip}`, `{device}`, `{timestamp}`, `{user}` (plus `$IP_Address`, `$Devices`, `$Timestamps`, `$User` aliases).
- App UI renders 7 diagonal bands of repeating two-line tiles, animated as a continuous train; fixed overlay, configurable opacity/color/speed, no pointer events, z-index below sidebar/topbar/modals.
- PDF reports render a static diagonal repeating watermark on every page using the watermark context captured when the report is generated (stored in the report snapshot); PDF uses the configured opacity and color (speed is UI-only).
