# ANTIGRAVITY REGRESSION PROTECTION STANDARD

## 1. PURPOSE & MANDATE

Every improvement, refactor, visual enhancement, or feature addition must pass the **7-Point Regression Protection Gate** before it can be considered complete.

> **THE NON-REGRESSION INVARIANT:**  
> An improvement is never successful if it degrades, damages, or silently breaks existing capabilities. Any regression immediately blocks completion.

---

## 2. THE 7 NON-NEGOTIABLE REGRESSION VERIFICATION INVARIANTS

Before declaring any task or improvement complete, Antigravity must systematically verify:

| # | Verification Gate | Non-Regression Invariant Requirement |
| :---: | :--- | :--- |
| **1** | **Existing Functionality Still Works** | All buttons, forms, interactive components, filters, modals, workflows, state managers, and business logic continue functioning exactly as designed without errors or broken states. |
| **2** | **Existing Routes Still Work** | All internal page links, route parameters, sub-routes, navigation breadcrumbs, canonical URLs, back-button history, and redirects resolve properly. Zero 404s or broken navigation paths. |
| **3** | **Existing Data Is Preserved** | Local storage, sessions, database records, schema integrity, JSON structures, mock datasets, user settings, and state stores remain 100% uncorrupted and intact. Zero data loss. |
| **4** | **Existing Permissions Remain Intact** | Role-based access control (RBAC), authentication barriers, guest vs. authenticated user boundaries, and sensitive data access rules remain strictly enforced without permission bleed. |
| **5** | **Existing Integrations Remain Intact** | External API endpoints, webhooks, third-party libraries (mapping, payment, identity, storage, analytics), environment variables, and SDK connections remain unbroken and functional. |
| **6** | **Existing Responsive Behavior Isn't Broken** | Layouts remain fully responsive across Mobile (320px–480px), Tablet (768px–1024px), Desktop (1024px–1440px), and 4K displays. Zero horizontal viewport spill, text clipping, or overlapping navigation. |
| **7** | **Performance Hasn't Materially Degraded** | Bundle size, First Contentful Paint (FCP), Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS), and frame rates (target: 60 FPS) remain stable. Zero multi-megabyte unoptimized asset bloat or GPU thrashing. |

---

## 3. PRE-FLIGHT & POST-FLIGHT VERIFICATION PROTOCOL

```
             START IMPROVEMENT
                    ↓
        [ PRE-FLIGHT BASELINE ]
  1. Record working routes and features
  2. Note state/data structures & permissions
  3. Verify baseline responsiveness & performance
                    ↓
        [ IMPLEMENT SURGICAL CHANGE ]
  (Targeted diffs, zero destructive rewrites)
                    ↓
        [ POST-FLIGHT REGRESSION GATE ]
  1. Functionality Check   [ PASS / FAIL ]
  2. Route Integrity       [ PASS / FAIL ]
  3. Data Preservation     [ PASS / FAIL ]
  4. Permission Security   [ PASS / FAIL ]
  5. Integration Health    [ PASS / FAIL ]
  6. Responsive Layout     [ PASS / FAIL ]
  7. Performance Budget    [ PASS / FAIL ]
                    ↓
           ANY GATE FAILED?
        ↙                    ↘
     YES                      NO
      ↓                        ↓
  [ ROLLBACK / REMEDIATE ]   [ APPROVED ]
```

---

## 4. STANDARDIZED REGRESSION CHECKLIST

When presenting any completed work, Antigravity renders this verification table:

```markdown
### 🛡️ Regression Protection Gate

| Invariant | Status | Verification Evidence |
| :--- | :---: | :--- |
| **1. Functionality** | Pass | Tested interactive elements, forms, and workflows; zero console errors. |
| **2. Routes** | Pass | Tested canonical routes, back navigation, and deep links; zero dead links. |
| **3. Data** | Pass | State stores, localStorage, and DB records preserved; zero schema mutations. |
| **4. Permissions** | Pass | Access controls and session boundaries verified; zero permission leaks. |
| **5. Integrations** | Pass | API calls, SDKs, and external libraries verified; zero network errors. |
| **6. Responsive** | Pass | Tested at 320px, 768px, 1024px, 1440px+; zero horizontal viewport scroll. |
| **7. Performance** | Pass | Fast LCP, zero layout shifts, optimized modern asset delivery. |

**Regression Status**: APPROVED (7/7 Passed, Zero Regressions)
```
