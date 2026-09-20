# ANTIGRAVITY INCREMENTAL IMPROVEMENT STANDARD

## 1. CORE DIRECTIVE: NEVER "REDESIGN EVERYTHING"

AI agents must **NEVER** execute or propose sweeping "Redesign everything" overhauls. 

Unconstrained rewrites introduce regressions, break subtle business logic, destroy existing integrations, and create cascading secondary defects.

Instead, the standing operational directive is:

> **"Inspect the project, identify the highest-impact visual and UX improvements, implement them without breaking existing functionality, then re-audit."**

---

## 2. THE 4-PHASE INCREMENTAL CADENCE

```
               EXISTING PROJECT
                      ↓
           1. SURGICAL INSPECTION
    (Map features, routes, dependencies, tokens)
                      ↓
         2. HIGHEST-IMPACT SELECTION
        (Focus on top 1-3 targeted wins;
          maximize visual/UX ROI, minimize risk)
                      ↓
       3. NON-DESTRUCTIVE IMPLEMENTATION
    (Surgical diffs; preserve working logic;
      never break existing props or APIs)
                      ↓
            4. IMMEDIATE RE-AUDIT
        (Verify 11-point Visual Scorecard;
          confirm 0 regressions; then repeat)
```

---

## 3. THE "THREE NEW PROBLEMS" PREVENTION PROTOCOL

To prevent the classic failure mode where an attempted improvement spawns multiple new bugs:

1. **Inward Dependency Audit Before Any Touch**:
   - Before modifying a component, stylesheet, or asset, map every file that imports or references it.
   - If an element is shared across 5 screens, do not refactor its core contract to fix 1 screen.

2. **Surgical, Minimal-Diff Implementations**:
   - Make the smallest possible code change that achieves the visual or UX goal.
   - Refactor styling with modular classes or design tokens rather than rewriting entire DOM trees.
   - Never remove existing HTML IDs, data attributes, accessibility labels, or event handlers.

3. **Preserve Working Logic & Invariants**:
   - Never touch business logic, state management, or backend API queries during a visual/UX pass.
   - If improving an image or icon, ensure existing aspect ratios and container dimensions are preserved so surrounding layouts do not shift (Zero Cumulative Layout Shift).

4. **Verify Existing Functionality First (Regression Check)**:
   - Immediately test adjacent buttons, navigation items, modals, and forms on the modified screen.
   - Ensure what worked 5 minutes ago continues to work flawlessly.

5. **Categorical Re-Audit**:
   - Run the 11-point Enterprise Visual Scorecard (`Pass` / `Needs Improvement`).
   - If any previously working area drops to `Needs Improvement`, immediately roll back or correct the targeted change before moving forward.

---

## 4. HIGHEST-IMPACT VISUAL & UX TARGET PRIORITIZATION

When deciding what to improve incrementally, prioritize by impact vs. risk:

| Priority Rank | High-Impact Visual / UX Improvement | Risk Profile | Why It Wins |
| :---: | :--- | :---: | :--- |
| **1** | **Hero & Prominent Media Upgrades** | Low Risk | Replacing blurry/low-res imagery with crisp, enhanced assets instantly transforms perceived quality with zero logic risk. |
| **2** | **Typography & Contrast Refinements** | Low Risk | Tightening line-heights, letter-spacing, and ensuring 4.5:1 WCAG contrast dramatically improves legibility without breaking layouts. |
| **3** | **Interactive Feedback & Micro-Animations** | Low-Medium Risk | Adding subtle, purposeful hover/focus transitions and loading states provides immediate responsiveness. |
| **4** | **Spacing Rhythm & Alignment Fixes** | Low Risk | Aligning ad-hoc margins/paddings to a consistent 4px/8px grid removes visual clutter without altering DOM structure. |
| **5** | **Icon Consistency Unification** | Low Risk | Harmonizing stroke weights and corner radiuses creates instant enterprise cohesion. |
| **6** | **Mobile Touch Target & Viewport Bounds** | Medium Risk | Expanding touch targets to 44px and eliminating horizontal scroll without changing desktop layouts. |

---

## 5. INCREMENTAL COMMITMENT RULE

- Improve 1 focused visual or UX surface at a time.
- Verify it completely in real browser viewports.
- Confirm zero regressions.
- Only then proceed to the next highest-impact improvement.
