# Code reduction: measurements and plan

Measured on 2026-10-09 with [`reduction.py`](reduction.py) (re-runnable).
Detail is in the CSVs next to this file. Another session is adding features
while this was measured, so treat totals as a snapshot.

## Size

| Area | Lines |
|---|---:|
| `app/` (PHP) | 63,536 |
| `resources/js/` (excluding the vendored shadcn kit, 3,958) | 57,578 |
| `tests/` | 37,415 |

## What the measurements say

The size is **not** coming from copy-paste or thin wrappers. It comes from
parallel stacks, demo modules, overlapping features and very large files.

| Measure | Result | Reading |
|---|---:|---|
| Copy-pasted code (12+ identical lines) | ~1,500 lines | Small. Two real cases: `VisitorController` vs `VisitorRepository`, `edit-renter-form` vs `register-renter-form`. |
| Methods that only forward to another class | 12 | Negligible; no class is a pure pass-through. |
| Classes nothing references | 24 (3,180 lines) | Includes an unwired visitor layer (Actions, Requests, Repository) the controller duplicates. |
| Files over the size limit (PHP 500, TS 600) | 49 (51,068 lines) | **40% of the code is in 49 files.** The worst: `Delegation/Index.tsx` 3,614, `Company.tsx` 2,083, `Occupancy.tsx` 2,031, `DelegatedAccessController` 1,701, `BillingController` 1,506. |
| Removable route aliases and versioned copies | 18 routes | `/p` and `/pay`, two export paths, two receipt paths, docs/health/version at three API prefixes, `admin`/`filament`/`portal`. |
| Resource-controller candidates | 12 controllers | Blocklist, Calendar, Guidelines, Household, Renter, Vehicle, Visitor, Warning, Fundraising, Tokens, Webhooks, auth. |
| Broken model relationships | 1 | `Tenant::community()` points at `tenants.community_id`, which does not exist (half-wired tenancy). |
| Write routes no test touches | 14 | Listed in `untested-writes.csv`; includes the API visitor check-in/out, boundary replace/validate, profile avatar and the new ownership household-member routes. |

## Duplicate concepts (models)

The model count is not the problem; these overlaps are:

| Concept | Models | Evidence |
|---|---|---|
| A person allowed onto a property | `Renter`, `HouseholdMember`, `DelegatedAccess`, `Staff`, `AuthorizedPerson` | Five tables, three screens to add people |
| Notifications | `Notification`, `InAppNotification` | `in_app_notifications` has 0 rows locally; four notification code paths |
| Audit trail | `AccessLogEntry`, `AccessAuditTimelineEvent`, `Activity`, `ActivityLogEntry` | Four tables (1,291 / 167 / 45 / 5 rows) |
| Payments ledger | `Payment` and `Transaction` (plus `PaymentEvent`, `StripeEvent`, `TransactionEvent`) | Both heavily used (43 and 44 references); 28 payment models in all |

## Plan, in tiers

### Tier 1: remove with no feature loss (about 12,000-14,000 lines, ~10%)

| Candidate | Lines (app + tests) |
|---|---:|
| Filament showcase hub (role simulator) | 2,347 |
| `Notifications/Universal` engine (Slack, Teams, SES, SendGrid, Postmark, Mailgun, Firebase, OneSignal, Ably, Pusher) | 2,377 |
| `Payments/Modular` (10 gateways the estate will not use) | 2,085 |
| Observability API and dashboard | 1,212 |
| Feature-flag API | 979 |
| Generic PDF API | 612 |
| `AccessRelationshipEngine` (unused; grants permissions by name) | 602 |
| Queue dashboards and dispatch API | 563 |
| Query-builder playground | 512 |
| Octane benchmark | 504 |
| Performance/benchmark | 417 |
| Other unreferenced classes (`MapService`, `PaymentQrCode`, `TransactionResource`, ...) | ~700 |
| 18 route aliases and versioned copies | routes |

Callers checked: no screen calls any of these APIs. `Payments/Modular` is
reached only by its own API and the unlinked gateway hub; `Notifications/Universal`
only by its own API and the unlinked notification hub. **`Payments/Drivers`
is not in this tier**: the public payment links (`/p/{token}`) run through it
via `UniversalPaymentLinkController` and `PaymentOrchestratorService`.

Each removal: confirm no caller (the inventories list them), delete, run the
suite. Removing a module removes its tests too; that is lost coverage of
removed code only.

#### Tier 1 result (done 2026-10-09, commits 21c993c..071e0b3)

| Step | Commit | Removed |
|---|---|---:|
| Showcase hubs: queues + 11 sample jobs, Octane, observability, feature flags, generic PDFs, query-builder playground, "Filament" simulator | 32bc030 | 11,588 lines |
| `Notifications/Universal` engine, its API, hub and config | a267c61 | 3,757 lines |
| `Payments/Modular` gateways, API and hub | c4539c5 | 2,964 lines |
| Unreferenced `MapService`, `PaymentQrCode`, `TransactionResource`, `CreateGatePassApiRequest` | 9a5b7ea | 355 lines |
| Four dashboard route aliases | 071e0b3 | 4 routes |

Net: **18,547 lines** (16,137 in `app/` and `resources/`, 2,107 in tests),
routes ~350 → 308. Committed state: 1,320 tests, 0 failures, after each step.
`RemovedShowcaseModulesTest` pins every removed page and endpoint as a 404.

Found along the way: `/api/v1/metrics` always returned 500 for System Admin
(wrong column); fixed in 21c993c.

Deliberately not removed:
- `/p/{token}` and `/pay/{token}`: both are on payment links and posters already issued.
- Versioned API copies (`/api`, `/api/v1`, `/api/v2` docs, health, version): a public contract; `/api/health` is the load-balancer probe.
- `AccessRelationshipEngine`: never committed (untracked work from another session).
- Classes referenced only by tests (`EnterpriseReconciliationService`, `BackupManagerService`, media services): may be features awaiting wiring; decide in Tier 2.
- The Octane, Pennant and Spatie query-builder packages and their config: removing a package is a dependency change.

### Tier 2: consolidate (fewer lines, same features)

1. **Wire `VisitorController` to the existing Actions** (`RegisterVisitorAction`,
   `CheckInVisitorAction`, `UpdateVisitorAction`, their Form Requests) and
   delete the inline copy; drop `VisitorRepository` or use it.
2. **One renter form** for register and edit.
3. **Resource routes** for the 12 CRUD controllers.
4. **One notification path:** `NotificationEngine` and one table.
5. **One audit trail:** `activity_log` for actions, `access_log_entries` for gate events.
6. **One People & Access model** behind one screen, replacing three screens.
7. **Split the 49 oversized files**: pages into sections and components,
   fat controllers into Form Requests and Actions. This does not cut
   lines much, but it is what makes further reduction safe.

### Tier 3: rebuild (plan separately)

- **One payments ledger** (`Payment` + `Transaction`), with one event log,
  and one payment stack: fold `Payments/Drivers` (used by payment links)
  and the duplicate Stripe code into `Payments/Providers`.
- **Tenancy**: finish (fix `Tenant::community`) or remove the package.
- **Gate hardware**: an authenticated device API instead of the simulators.

## Tests

Full run on 2026-10-09: **1,567 passed, 15 failed** (up from 3 on 2026-10-07).

| Failures | Cause | Action |
|---:|---|---|
| 4 | `ArchitectureBaselineTest` (a ratchet over these rules: thin actions, Form Requests, policy-based authorization, nothing new in the `Services` root) — code added since 10-07 introduced new violations, including the Safety controllers | Fix the new violations; never re-record the baseline to make it pass |
| 2 + 1 | Incoming-webhook secrets and tenancy | Uncommitted community middleware; fix or remove tenancy |
| 6 | Notification inbox/hub and Universal notification API (403s, a missing route) | The uncommitted notification work; resolves with the "one notification path" consolidation |
| 1 | `ErrorPageNavigationTest` | Uncommitted change to error messages; investigate |
| 1 | `NotificationAndTokenApiAccessTest` (2 cases) | Same notification change |

- Preserve the suite; remove only the tests of removed modules.
- Keep the architecture ratchet: it is the guard that stops this code
  growing back. Its baseline should only ever shrink.
- Add coverage for the 14 untested write routes, starting with the
  permission checks (a forbidden case for each).

## Reduction target

Set after Tier 1, from what it actually removes. On these measurements,
Tier 1 is about 10% with no feature change; Tiers 2 and 3 are where the
remaining overlap is, and their size depends on the decisions above.
