# Architecture baseline

Measured on 2026-10-07 from `feat/laravel-conversion`. Every number here
comes from the code; `inventory.py` regenerates the four inventories below.

| File | What it lists |
|---|---|
| [route-inventory.csv](route-inventory.csv) | All 350 routes: purpose, role, permission, controller, services, status, used?, duplicate?, demo?, production?, **KEEP / MERGE / REBUILD / REMOVE** and the basis for it |
| [service-inventory.csv](service-inventory.csv) | All 156 classes in `app/Services`: what kind of class each really is, size, references, recommendation |
| [controller-inventory.csv](controller-inventory.csv) | All 70 controllers: size, queries, inline validation |
| [model-inventory.csv](model-inventory.csv) | All 92 models: domain and references |

## The rules

**Controllers are thin.**

```
Request → Form Request (validation) → Policy/Gate (authorization)
        → Action or domain service → Model → Response
```

No business logic, queries or `$request->validate()` in controllers.

**Services are grouped by domain**, and a service that only wraps one query
is not a service:

Access · People · Properties · Credentials · Visitors · Safety · Security ·
Billing · Communications · Reporting · Administration

**`app/Services` holds services only.** Contracts, data objects, enums and
exceptions live with their domain, not in `Services`.

**Every route has a classification** in the route inventory, and a new route
gets one when it is added.

## What the baseline shows

### Routes: 350

| Classification | Routes | Meaning |
|---|---:|---|
| KEEP | 273 | Reachable, permissioned, not duplicated |
| MERGE | 29 | Duplicate paths to one action, or overlapping features |
| REBUILD | 8 | Simulated hardware or half-wired features |
| REMOVE | 40 | 32 demo/showcase routes, plus 8 with no caller found (verify each) |

- **Demo surface:** Octane benchmarks, observability "simulate error",
  on-demand queue dispatch, feature-flag purge/simulate, the Filament
  showcase hub with a role simulator, a query-builder playground and a UI
  kit: 32 routes that serve no estate need and widen the attack surface.
- **Duplicates:** API docs, health and version each served at three paths
  (`/api`, `/api/v1`, `/api/v2`); `/p/{token}` and `/pay/{token}`;
  `export-transactions` and `export/transactions`; two donation receipt
  paths; `/delegation` and `/access-and-people`; `notifications/send` and
  `notifications/dispatch`.
- **Rebuild:** the NFC tap, gate sensors and wallet passes are simulated;
  tenancy is half-wired (its test fails).
- **Authorization:** every route that changes data is either behind a gate
  or checks in its controller (verified by hand; see Permission column).

### Services: 156 files, 63 actual services

| Kind | Count |
|---|---:|
| Service | 63 |
| Driver / channel | 55 |
| Contract (interface) | 21 |
| Data object | 17 |

The count is inflated less by thin services than by **parallel stacks**:

- **Payments: three stacks.** `Payments/Drivers` (9), `Payments/Modular/Drivers`
  (11 gateways: Adyen, Authorize.Net, Braintree, Cashier, Flutterwave,
  Mollie, PayPal, Paystack, Square, Stripe) and `Payments/Providers`
  (Office, Stripe, Stripe Terminal, Wallet, WiPay), plus
  `StripePaymentService` and `PaymentOrchestratorService`. **Stripe is
  implemented four times.** The estate needs Stripe, WiPay and office
  payments.
- **Notifications: two engines** (`NotificationEngine` and
  `Notifications/Universal`), plus `SmsService`/`WhatsAppService` at the
  root: three ways to send an SMS. Universal ships Slack, Teams, SES,
  SendGrid, Postmark, Mailgun, Firebase, OneSignal, Ably and Pusher
  providers and is used by 4 files outside itself.
- **Large services to split** (over 600 lines): `StripePaymentService`
  (1,320), `PropertyOccupancyService` (1,098), `SilentAssistanceService`
  (1,049), `GatePassEngine` (1,045), `PaymentOrchestratorService`,
  `UniversalPaymentWebhookService`, `GateScanner`, `ObservabilityService`,
  `EnterpriseReconciliationService`, `AccessRelationshipEngine` (unused; it
  grants permissions by a person's name).

### Controllers: 70

- 7 are fat: `DelegatedAccessController` (1,680 lines),
  `BillingController` (1,506, 55 query calls), `VisitorController` (716),
  `FundraisingController`, `BoundaryController`, `DocsApiController`,
  `OccupancyController`.
- **122 inline `$request->validate()` calls** where the rule is a Form
  Request.

### Models: 92

All are referenced. The review is per domain, not by usage: several sets
overlap (gate passes, parking passes, wallet credentials; renters,
household members, delegated access).

## Consolidation backlog, in order

1. **Remove the 32 demo routes** and the code behind them (Octane,
   observability, queue, feature-flag and PDF APIs; Filament hub;
   query-builder; UI kit). Verify, then remove the 8 uncalled routes.
2. **One payment stack.** Keep `Payments/Providers` (Stripe, WiPay,
   Office, Wallet, Stripe Terminal); retire `Payments/Drivers`,
   `Payments/Modular` and the duplicate Stripe code.
3. **One notification engine.** Keep `NotificationEngine` with in-app, SMS,
   WhatsApp, email and push; retire `Notifications/Universal`.
4. **Collapse duplicate routes** to one path each, with redirects where a
   path is public (`/pay/{token}`).
5. **Merge overlapping features:** one People & Access (household, renters,
   delegation); one My Credential (gate pass, wallet, credential help); one
   Emergency (safety alerts, silent assistance, panic, broadcasts).
6. **Thin the 7 fat controllers**: Form Requests for the 122 inline
   validations, queries into domain services or Actions.
7. **Move data objects and contracts** out of `app/Services` into their
   domains.
8. **Rebuild simulated hardware** as an authenticated gate-device API (NFC
   readers, gate sensors, ANPR cameras), and finish or remove tenancy.

## Re-running

```bash
cd laravel
php artisan route:list --json --except-vendor > ../docs/architecture/routes.json
cd ..
python docs/architecture/inventory.py
```

Decisions the code cannot show (a demo module, a known duplicate) are in
`OVERRIDES` at the top of `inventory.py`, each with its reason.
