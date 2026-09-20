# ANTIGRAVITY CONTINUOUS VISUAL IMPROVEMENT ENGINE

## 1. PURPOSE & MANDATE

Antigravity must continuously and autonomously evaluate projects before and after every modification, refactor, upgrade, or visual enhancement. No visual change is complete without passing the **Discover → Audit → Prioritize → Improve → Validate → Monitor → Repeat** cycle.

Every project maintains an active visual baseline that continuously elevates:
- Visual fidelity
- Brand alignment
- User experience
- Accessibility compliance (WCAG 2.1 AA)
- Mobile responsiveness
- Asset delivery performance

---

## 2. THE 7-STAGE CONTINUOUS IMPROVEMENT LIFECYCLE

```
                 PROJECT STATE
                       ↓
               1. DISCOVER
        (Assets, media, DOM, CSS, 3D, maps)
                       ↓
                  2. AUDIT
  (14 Inspection Targets: Quality, a11y, perf)
                       ↓
               3. PRIORITIZE
       (P0 Blocker → P1 High → P2 Med → P3 Polish)
                       ↓
                 4. IMPROVE
   (Clean, enhance, restore, animate, optimize)
                       ↓
                5. VALIDATE
     (Browser QA, responsive, accessibility,
      Visual Quality Gate 21-point check)
                       ↓
                 6. MONITOR
   (Continuous regression watch before/after)
                       ↓
                 7. REPEAT
```

### Stage 1: Discover
Before making any changes, automatically discover and index:
- **Raster & Vector Media**: `.png`, `.jpg`, `.jpeg`, `.webp`, `.svg`, `.gif`, `.avif`
- **Video Assets**: `.mp4`, `.webm`, stream manifests, background loops
- **3D Assets & Canvases**: `.gltf`, `.glb`, `.obj`, `.splinecode`, Three.js, Babylon.js, WebGL canvas components
- **Cartographic Elements**: Leaflet, Mapbox, Google Maps, OpenStreetMap, SVG maps, boundary layers
- **Motion & Animations**: CSS `@keyframes`, Framer Motion, GSAP, Alpine transitions, Tailwind animation classes
- **Typography & Tokens**: Font families, font weights, color palettes, spacing units, elevation/shadows
- **Component Implementations**: Card layouts, navigation drawers, buttons, forms, modals, tables, empty states

### Stage 2: Audit (The 14 Defect Detection Pillars)
Systematically evaluate all discovered elements against the 14 inspection pillars:

1. **Poor/Old Images**:
   - Detect low resolution, pixelation, compression artifacts, noise, blur, or outdated visual treatment.
2. **Images that can be Cleaned or Enhanced**:
   - Identify assets with poor contrast, incorrect exposure, cluttered backgrounds, jagged edges, or lighting mismatches that can be restored.
3. **Missing Imagery**:
   - Detect missing hero graphics, absent avatars, empty product slots, or broken image paths.
4. **Weak Animations**:
   - Detect animations that lack purpose, feel jerky/uncalibrated, loop distractingly, cause layout shifts, or block user interaction.
5. **Outdated 3D**:
   - Detect faceted low-poly meshes where smooth geometry is expected, low-res textures, missing shadows, poor lighting, or camera fights.
6. **Poor Maps**:
   - Detect fabricated coordinates, inaccurate regional boundaries, excessive marker clutter, lack of clustering, or illegible styling.
7. **Inconsistent Icons**:
   - Detect mixed stroke weights (e.g. 1.5px mixed with 2px or solid fills), conflicting corner radiuses, disparate icon sets, or optical misalignments.
8. **Visual Inconsistencies**:
   - Detect off-palette hex codes, conflicting typography scales, mismatched card borders, random border radiuses, or inconsistent shadows.
9. **Accessibility Problems**:
   - Detect contrast ratios below WCAG 2.1 AA (4.5:1 text, 3:1 UI), missing `alt` attributes, unannounced icon buttons, unhandled `prefers-reduced-motion`, or touch targets < 44x44px.
10. **Slow-Loading Media**:
    - Detect multi-megabyte uncompressed PNG/JPEGs, absence of modern WebP/AVIF formats, missing `srcset` responsive sizes, missing video posters, or eager-loading offscreen assets.
11. **Mobile Problems**:
    - Detect horizontal viewport spill, text clipping, squished cards, overlapping navigation bars, and unoptimized touch interactions.
12. **Broken/Unused Assets**:
    - Detect 404 links, broken asset references, or orphaned image/video files bloating the project bundle.
13. **Generic Placeholders**:
    - Detect "Lorem Ipsum" text, gray wireframe placeholders, unbranded mock avatars, or temporary placeholder watermarks.
14. **Components that No Longer Meet Design System**:
    - Detect deprecated component markup, hardcoded inline CSS, detached tokens, or outdated layouts violating current design guidelines.

### Stage 3: Prioritize
Organize all audit findings into a prioritized action backlog:
- **P0 (Blockers)**: Broken assets (404), severe mobile horizontal layout overflow, unnavigable keyboard/screen reader states.
- **P1 (High)**: Blurry/pixelated hero imagery, generic placeholders in production views, heavy uncompressed assets degrading load times, inaccurate maps.
- **P2 (Medium)**: Inconsistent icon styles, lack of modern WebP/AVIF formats, weak micro-animations, ad-hoc hex colors.
- **P3 (Polish)**: Subtler hover transitions, refined blur placeholders, optimized 3D shader performance.

### Stage 4: Improve
Execute surgical, non-destructive enhancements:
- Never destroy or overwrite the original asset before an improved candidate is verified.
- Follow the verification pipeline: `Original → Inspect → Clean → Restore → Enhance → Upscale → Compare → QA → Integrate`.
- Replace placeholders with high-fidelity, production-grade assets aligned with brand identity.
- Refactor ad-hoc styling into unified design tokens and reusable components.

### Stage 5: Validate
Submit all improvements through the **Visual Quality Gate**:
- Test desktop, tablet, and mobile views.
- Verify `prefers-reduced-motion` compliance.
- Confirm zero horizontal overflow and sub-second visual load times.
- Validate interactive map controls, 3D camera bounds, and video playback.

### Stage 6: Monitor
- Verify the **Before vs After** state of the project.
- Conduct regression checks on dependent components, pages, and routes to ensure zero collateral damage.
- Record visual diffs and audit logs.

### Stage 7: Repeat
Continuous improvement is permanent. Every code edit, new route, or media addition automatically triggers this loop to maintain enterprise production excellence.

---

## 3. BEFORE & AFTER AUTOMATED PROTOCOL

Whenever an agent makes changes to a project:

### BEFORE CHANGES:
1. Run Discovery on target views and assets.
2. Log existing visual defects, layout constraints, and design tokens.
3. Establish the non-regression baseline.

### AFTER CHANGES:
1. Re-run the 14-pillar audit on modified views and connected components.
2. Confirm all prioritized defects have been resolved.
3. Verify that zero new visual regressions or performance degradation were introduced.
4. Validate in real browser viewports across Mobile, Tablet, and Desktop.
