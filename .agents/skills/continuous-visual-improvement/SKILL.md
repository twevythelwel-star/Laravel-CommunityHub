---
name: continuous-visual-improvement
description: Continuous Visual Improvement engine to automatically discover, audit, prioritize, improve incrementally, validate with the 11-point Enterprise Visual Scorecard, and enforce 7-point Regression Protection before and after changes.
---

# Continuous Visual Improvement Workflow & Engine

Automated workflow that continuously inspects a project before and after any modification to eliminate visual defects and elevate product quality to enterprise standards.

---

## Standing Principles

1. **The Incremental Improvement Mandate**:  
   *Never "Redesign everything."*  
   *Always "Inspect the project, identify the highest-impact visual and UX improvements, implement them without breaking existing functionality, then re-audit."*

2. **The 7-Point Regression Protection Gate**:  
   No task is complete if existing capabilities are harmed or broken. Every change must verify all 7 Non-Regression Invariants:
   - Existing functionality still works
   - Existing routes still work
   - Existing data is preserved
   - Existing permissions remain intact
   - Existing integrations remain intact
   - Existing responsive behavior isn't broken
   - Performance hasn't materially degraded

---

## The 7-Stage Continuous Loop

```
Discover → Audit → Prioritize → Improve → Validate → Monitor → Repeat
```

### 1. Discover
Identify all visual elements in the project:
- Images (raster, vector, photos, icons, logos, avatars, illustrations)
- Videos & background loops
- 3D assets, scenes, WebGL canvases
- Maps and geographic visualizations
- Animations and interactive transitions
- Typography, color systems, design tokens
- UI components, layouts, dialogs, cards, forms

### 2. Audit (14 Inspection Categories)
Check systematically for:
1. **Poor/old images**: Low resolution, noise, pixelation, compression blur.
2. **Images that can be cleaned or enhanced**: Background clutter, poor contrast, wrong lighting.
3. **Missing imagery**: Dead image paths, absent avatars, empty product cards.
4. **Weak animations**: Stiff, jerky, excessive motion, missing reduced-motion alternatives.
5. **Outdated 3D**: Low-poly faceting, muddy textures, laggy frame rates, camera fights.
6. **Poor maps**: Fabricated coordinates, inaccurate borders, marker clutter, no clustering.
7. **Inconsistent icons**: Mixed stroke weights, conflicting corner radiuses, mismatched styles.
8. **Visual inconsistencies**: Off-brand colors, irregular padding, ad-hoc font styles.
9. **Accessibility problems**: Low contrast (<4.5:1 text, <3:1 UI), missing alt text, missing focus rings.
10. **Slow-loading media**: Uncompressed media, missing WebP/AVIF, missing `srcset`, missing video posters.
11. **Mobile problems**: Horizontal overflow, pinched text, unnavigable touch targets (<44px).
12. **Broken/unused assets**: 404 links, unreferenced media in project bundles.
13. **Generic placeholders**: "Lorem ipsum", gray placeholder rectangles, stock watermarks.
14. **Components that no longer meet the design system**: Hardcoded CSS, deprecated classes.

### 3. Prioritize: Highest Impact, Lowest Risk
Sort all audit findings and focus on the highest-impact visual and UX improvements:
- **P0 (Blocker)**: Broken links, mobile horizontal overflow, broken accessibility.
- **P1 (High)**: Low-res hero media, generic placeholders, unoptimized heavy assets, bad map data.
- **P2 (Medium)**: Inconsistent icon sets, missing WebP/AVIF formats, weak micro-animations.
- **P3 (Polish)**: Subtler hover transitions, improved shadow depths, refined 3D materials.

### 4. Improve (Surgical & Non-Destructive)
- **Zero Collateral Damage Protocol**:
  1. Map inward dependencies before editing any component or token.
  2. Use minimal diffs; preserve existing HTML IDs, data attributes, and accessibility tags.
  3. Never alter business logic, state management, or backend API queries during a visual/UX pass.
  4. Preserve aspect ratios and dimensions to prevent layout shifts.
- Follow: `Original → Inspect → Clean → Restore → Enhance → Upscale → Compare → QA → Integrate`.

### 5. Validate: The Enterprise Visual Scorecard
Completion is governed strictly by the **Enterprise Visual Scorecard**. Numerical scores are forbidden as completion criteria. Every area must be evaluated as `Pass` or `Needs Improvement`:

| Area | Status | Criteria |
| :--- | :---: | :--- |
| **Images** | `Pass` / `Needs Improvement` | All assets present, contextual, zero 404s/placeholders. |
| **Image Quality** | `Pass` / `Needs Improvement` | High resolution, balanced contrast, zero compression noise. |
| **Animation** | `Pass` / `Needs Improvement` | Purposeful transitions, `prefers-reduced-motion` supported. |
| **3D** | `Pass` / `Needs Improvement` | Clean topology, optimized materials, 60 FPS, intuitive controls. |
| **Maps** | `Pass` / `Needs Improvement` | Strict geographic accuracy, zero fake coordinates, clear hierarchy. |
| **Video** | `Pass` / `Needs Improvement` | High fidelity, modern compression, poster frames, smooth playback. |
| **Accessibility** | `Pass` / `Needs Improvement` | WCAG 2.1 AA contrast, alt-text, touch targets >= 44x44px. |
| **Performance** | `Pass` / `Needs Improvement` | WebP/AVIF formats, responsive srcset, lazy-loading, sub-second LCP. |
| **Responsive Design** | `Pass` / `Needs Improvement` | Verified at 320px, 768px, 1024px, 1440px+; zero horizontal spill. |
| **Brand Consistency** | `Pass` / `Needs Improvement` | Exact token adherence, consistent icon weights and typography. |
| **Enterprise UI** | `Pass` / `Needs Improvement` | Clean visual hierarchy, consistent spatial rhythm, refined elevation. |

> **Completion Gate**: APPROVED only when all 11 areas are verified as `Pass` (or `N/A - Pass`). Any `Needs Improvement` blocks completion until resolved.

### 6. Monitor: The 7-Point Regression Protection Gate
Verify the 7 non-negotiable invariants before concluding work:
1. **Existing Functionality**: Verified zero errors on buttons, forms, and workflows.
2. **Existing Routes**: Verified zero dead links, 404s, or broken back navigation.
3. **Existing Data**: Verified state stores, local storage, and database integrity preserved.
4. **Existing Permissions**: Verified authentication barriers and RBAC boundaries intact.
5. **Existing Integrations**: Verified external APIs, SDKs, and third-party tools healthy.
6. **Existing Responsive Behavior**: Verified zero horizontal scroll or layout breaking.
7. **Performance**: Verified sub-second LCP, zero CLS, and smooth 60 FPS rendering.

### 7. Repeat
Continuously loop through the 7 stages as new features, screens, or assets are added.
