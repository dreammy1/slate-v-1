# Visual Editor Performance Benchmark and Bundle Review

**Benchmark date:** 2026-08-23  
**Target:** Slate Visual Editor local benchmark fixture generated from `admin/visual_editor.php`  
**Live route status:** The deployed route `https://greenlightinduction.rakibhasaan.com/slate/admin/visual_editor.php` returned HTTP 404, so the benchmark used an isolated local HTTP fixture rather than production.

## Executive summary

The Visual Editor’s current MVP is small enough to render quickly in a local browser fixture, but it is not yet production-ready as a performance surface. The measured inline editor payload is approximately **17.4 KiB** before transport compression, consisting of **12,228 bytes of JavaScript** and **5,188 bytes of CSS**. The rendered document contained **14 canvas nodes**, and the measured layout sample was **0 ms at P50 and 67.3 ms at P95** in the captured run. The P95 result is not a statistically complete field benchmark, but it indicates that repeated synchronous layout reads and full-canvas rerenders should be removed before large imported templates are supported.

The most important current finding is functional: the local fixture rendered the canvas nodes but the layer panel reported **zero layer buttons**, so editor correctness should be fixed before interpreting scalability results. The production page also remains unavailable at the provided deployment URL, preventing a production-network benchmark. The bundle review shows that the repository is primarily a PHP server-rendered application with no frontend bundling pipeline for the editor; the MVP is delivered as a large inline script and style block.

## Measured live results

| Metric | Result | Interpretation |
|---|---:|---|
| Canvas node count | 14 | Small seeded document rendered successfully. |
| Layer button count | 0 | Functional issue in the benchmark fixture/runtime path; must be corrected. |
| Inline JavaScript | 12,228 bytes | Main editor runtime is embedded directly in the PHP page. |
| Inline CSS | 5,188 bytes | Editor shell and canvas styling are embedded directly in the PHP page. |
| HTML document size | 22,397 bytes | Includes the rendered shell, inline assets, and benchmark overlay. |
| Layout-read P50 | 0 ms | Fast for the small seeded canvas. |
| Layout-read P95 | 67.3 ms | Tail latency is high for a simple canvas and may worsen with large imported documents. |
| Browser route status | 404 | Production route is not deployed or not mapped at the tested URL. |

The benchmark was run in a connected Chromium session against a local PHP HTTP server. It measured actual DOM rendering and layout reads rather than estimating performance from source size. Because the benchmark is a single local run without CPU throttling, network shaping, or repeated statistical samples, the timings should be treated as engineering signals, not user-facing SLA commitments.

## Bundle and delivery review

The repository has no application build script for the Visual Editor. `package.json` only defines Playwright end-to-end testing, so there is currently no minification, code splitting, tree shaking, source-map pipeline, or hashed asset delivery for the editor runtime. The page therefore ships its JavaScript and CSS inline on every request.

The repository-wide JavaScript and CSS inventory is approximately **361,425 bytes** before compression, although most of that payload belongs to unrelated plugins and is not loaded by the Visual Editor page. The largest reusable assets identified were `plugins/forms/assets/css/public.css` at 62,670 bytes, `plugins/membership/assets/js/qrcode-generator.js` at 56,694 bytes, `plugins/small-business-kit/assets/css/sb.css` at 46,753 bytes, `plugins/content-builder/assets/css/public.css` at 28,819 bytes, and the legacy content builder assets at 25,580 bytes JavaScript and 25,084 bytes CSS.

The Visual Editor itself should not inherit the entire legacy content-builder bundle. Its current inline payload is smaller than the largest plugin assets, but inline delivery prevents caching across editor visits and makes every PHP response carry the same code. The editor also performs full `innerHTML` replacement after most changes, which is likely to become the primary cost as imported documents grow.

## Priority optimization backlog

| Priority | Recommendation | Expected benefit | Acceptance criterion |
|---|---|---|---|
| P0 | Fix the layer-panel rendering path and add an automated smoke assertion for canvas-node and layer-button parity. | Restores core editor navigation and makes later performance measurements trustworthy. | A 14-node fixture produces 14 selectable layer entries, and a browser smoke test confirms selection. |
| P0 | Move editor JavaScript and CSS into versioned static assets with cache headers and compression. | Removes repeated inline bytes and enables browser caching. | Subsequent editor visits reuse immutable assets; Brotli/gzip transfer size is recorded in CI. |
| P0 | Debounce server saves and coalesce rapid input events. | Prevents a network request on every keystroke/style change. | No more than one save request per 500 ms editing burst; save state remains accurate. |
| P1 | Replace full-canvas `innerHTML` rerenders with targeted node updates or a keyed renderer. | Reduces layout, event rebinding, and DOM churn for large templates. | A 250-node fixture keeps median interaction work below 16 ms on a throttled desktop profile. |
| P1 | Use event delegation for canvas and layer interactions rather than rebinding listeners to every node after each render. | Cuts listener allocation and rerender overhead. | One delegated canvas listener and one delegated layer listener handle selection, drag, and drop. |
| P1 | Add a document-size benchmark matrix for 14, 50, 100, and 250 nodes. | Establishes scalability thresholds before arbitrary HTML import is marketed. | CI records node count, render time, interaction time, and memory proxy for each fixture. |
| P2 | Lazy-load import parsing, history UI, and advanced inspector controls. | Reduces initial editor JavaScript for users who only edit existing pages. | Initial editor payload excludes optional modules and loads them only when opened. |
| P2 | Sanitize and normalize imported CSS server-side before persistence. | Reduces malicious or excessive style payloads and protects published rendering. | Imports reject active CSS behavior and cap stylesheet/document sizes with actionable warnings. |
| P2 | Add performance marks for load, first canvas render, selection, drag-drop, save, and publish validation. | Enables production telemetry and regression detection. | Marks appear in a privacy-safe performance event stream with tenant/user identifiers omitted or hashed. |

## Recommended next implementation sequence

First, correct the layer-panel rendering issue and add a deterministic browser smoke test. Second, extract the inline editor CSS and JavaScript into static, versioned assets and add compression/cache headers. Third, debounce save requests and replace full-document rerenders with delegated, targeted updates. Fourth, build the 14/50/100/250-node benchmark matrix and run it under a throttled browser profile. Only after those steps should arbitrary large HTML templates and premium interaction polish be evaluated against formal responsiveness targets.

## Benchmark limitations

The production route was unavailable with HTTP 404, so this is not a production RUM or network waterfall. The fixture uses the seeded editor document and does not measure authenticated server save latency, database persistence time, asset upload time, or publish validation time. The layer count of zero is a correctness finding, not a valid scalability baseline. A follow-up benchmark should run after the deployed route is available and after the layer-panel issue is fixed.
