# Slate Next-Generation Development Task Register

**Program:** SEO Control Plane → Theme/Template Contracts → HTML Importer → Normalized Document Model → Visual Editor MVP  
**Branch:** `next-gen-platform-dev`  
**Owner:** Slate product and engineering  
**Tracker:** `/admin/development_progress.php`

## Working model

Independent workstreams may be prepared in parallel, but shared contracts and database migrations must be integrated sequentially. The repository is a custom flat PHP application on PHP 8.4/MySQL with tenant-scoped data, positional hooks, migrations under `db/migrations/`, and a dependency-free test harness. Every logical batch must remain additive and backward-compatible. The existing baseline must remain green before each commit.

> **Gating rule:** do not build the visual editor against ad-hoc HTML. The SEO and theme/template contracts, importer, normalized document model, rendering adapter, and revision model must be stable first.

## Progress formula

Program completion is calculated from weighted workstreams rather than raw task count:

| Phase | Weight |
|---|---:|
| 0. Contracts and audit | 10% |
| 1. SEO control plane | 20% |
| 2. Theme and template library | 20% |
| 3. Safe importer | 15% |
| 4. Normalized document model | 15% |
| 5. Visual editor MVP | 15% |
| 6. QA, migration, and rollout | 5% |
| **Total** | **100%** |

A phase is complete only when its implementation, tests, security checks, documentation, and acceptance criteria are complete. The UI tracker supports local task completion for planning; release status must ultimately be driven by repository evidence and CI/deployment checks.

## Phase 0 — Contracts and audit · 10%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| C-01 | Audit Content Builder, SEO, public router, Template Engine, Theme Engine, Asset Manager, tenancy, hooks, and migrations | Architecture | — | Audit note with extension points and compatibility risks | Complete |
| C-02 | Define versioned page document schema and node identity rules | Data model | C-01 | Schema document plus JSON fixtures | Not started |
| C-03 | Define theme/template/page/block/global-part boundaries | Platform contracts | C-01 | Contract interfaces and lifecycle diagram | In progress |
| C-04 | Define HTML/CSS/asset security policy | Security | C-01 | Sanitization allowlist, quarantine rules, threat cases | Not started |
| C-05 | Define event taxonomy for page, SEO, asset, form, booking, payment, and portal events | Platform events | C-01 | Event names, payloads, tenant scope, idempotency rules | Not started |

## Phase 1 — SEO control plane · 20%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| SEO-01 | Add tenant-scoped global SEO settings and validation | Backend | C-01 | Migration, repository/service tests, settings UI | Complete* |
| SEO-02 | Implement precedence resolver: page → content type → global → fallback | Rendering | C-02 | Unit tests for every precedence branch | Complete* |
| SEO-03 | Add title, description, canonical, robots, social metadata output | Rendering | SEO-02 | Public HTML fixtures and snapshot checks | Complete* |
| SEO-04 | Add JSON-LD schema graph for Organization, WebSite, WebPage, Breadcrumb, Article, Product, Service, Event, FAQ, and LocalBusiness | SEO | SEO-02 | Valid JSON-LD fixtures and warning states | Not started |
| SEO-05 | Add sitemap, robots, redirects, and canonical conflict detection | SEO | SEO-02 | Route tests, loop tests, tenant isolation tests | Not started |
| SEO-06 | Add SEO/accessibility/performance pre-publish validation | QA | SEO-03, C-04 | Validation report with blocking vs warning rules | Not started |

*Complete in the current additive foundation batch; database-backed migration, structured fixtures, and full integration coverage remain release-gate work.*

## Phase 2 — Theme and template library · 20%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| THM-01 | Add theme metadata, activation, compatibility, version, and rollback model | Backend | C-03 | Migration and lifecycle tests | Not started |
| THM-02 | Add theme tokens, font pairing, chrome presets, and component defaults | Design system | THM-01 | Token resolution and contrast tests | Not started |
| THM-03 | Add template metadata, regions, content-type mapping, and dependencies | Backend | C-03 | Template selection and dependency tests | Not started |
| THM-04 | Add reusable template duplication and immutable versions | Backend | THM-03, C-02 | Duplicate isolation and restore tests | Not started |
| THM-05 | Build theme/template library UI with preview, search, filter, install, duplicate, activate, and rollback | Admin UI | THM-01, THM-03 | Admin smoke flow | Not started |
| THM-06 | Migrate existing Branding presets through a compatibility adapter | Compatibility | THM-02 | Existing pages render unchanged | Not started |

## Phase 3 — Safe HTML importer · 15%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| IMP-01 | Implement upload/quarantine/checksum pipeline | Security/storage | THM-03, C-04 | Unsafe archive and file tests | Not started |
| IMP-02 | Implement HTML parser and source artifact preservation | Importer | C-02, C-04 | Representative fixtures retained and parsed | Not started |
| IMP-03 | Implement CSS extraction, scoping, and unsupported-rule warnings | Importer | IMP-02 | Scoped output and warning fixtures | Not started |
| IMP-04 | Map local images/fonts/icons into Media Library | Media | IMP-01, IMP-02 | Asset manifest and tenant isolation tests | Not started |
| IMP-05 | Detect semantic regions and repeated components | Importer | IMP-02 | Region mapping report | Not started |
| IMP-06 | Build staging preview and import report | Admin UI | IMP-01–05 | Desktop/tablet/mobile preview and actionable warnings | Not started |

## Phase 4 — Normalized document model · 15%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| DOC-01 | Implement stable node IDs, parent/child constraints, and schema versions | Data model | C-02, IMP-02 | Tree validation tests | Not started |
| DOC-02 | Implement structured styles, token references, classes, and responsive overrides | Design system | THM-02, DOC-01 | Inheritance and reset tests | Not started |
| DOC-03 | Implement page/template/global-part version storage | Backend | THM-04, DOC-01 | Immutable revision and tenant tests | Not started |
| DOC-04 | Implement document renderer adapter for existing public rendering | Rendering | DOC-01–03 | Existing page compatibility tests | Not started |
| DOC-05 | Implement dynamic bindings for forms, booking, services, products, testimonials, and pricing | Integrations | DOC-04 | Safe empty/error states and permission tests | Not started |
| DOC-06 | Implement autosave, draft preview, diff, restore, and rollback | Revision system | DOC-03, DOC-04 | Recovery and publish tests | Not started |

## Phase 5 — Visual editor MVP · 15%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| EDT-01 | Build editor shell: top bar, layers panel, canvas, inspector, responsive controls | UI | DOC-04 | Responsive admin smoke flow | Not started |
| EDT-02 | Implement selection, hover outlines, semantic labels, and keyboard navigation | UI | DOC-01, EDT-01 | Keyboard and focus tests | Not started |
| EDT-03 | Implement inline text, link, and attribute editing | UI | DOC-01, EDT-02 | Edit/persist/render tests | Not started |
| EDT-04 | Implement structured style inspector and token/class editing | UI | DOC-02, EDT-02 | Style inheritance and responsive tests | Not started |
| EDT-05 | Implement valid-zone drag/drop, reorder, duplicate, delete, and grouping | UI | DOC-01, EDT-02 | Parent/child constraint tests | Not started |
| EDT-06 | Implement asset picker: upload, replace, remove, crop, focal point, alt text | Media UI | IMP-04, EDT-01 | Asset lifecycle and accessibility tests | Not started |
| EDT-07 | Implement copy/paste using versioned Slate clipboard payload | UI | DOC-01, DOC-02 | Internal and sanitized external paste tests | Not started |
| EDT-08 | Implement undo/redo, autosave indicator, revision compare, and recovery | Revision UI | DOC-06, EDT-01 | 50-step history and recovery flow | Not started |
| EDT-09 | Implement SEO, accessibility, and publish validation panels | SEO UI | SEO-06, EDT-01 | Blocking/warning states visible in editor | Not started |
| EDT-10 | Implement draft preview, schedule, publish, and rollback controls | Publish | DOC-06, SEO-06 | Immutable publish and rollback test | Not started |

## Phase 6 — QA, migration, and rollout · 5%

| ID | Task | Parallel lane | Depends on | Acceptance evidence | Status |
|---|---|---|---|---|---|
| QA-01 | Keep existing unit/integration/smoke baseline green | Regression | Every phase | `bash tests/run.sh` result | Not started |
| QA-02 | Add importer security fixtures and public-rendering fixtures | Security | IMP-01–06 | No stored-XSS or unsafe-file regressions | Not started |
| QA-03 | Test tenant isolation across themes, templates, assets, pages, and revisions | Tenancy | THM/DOC/IMP | Cross-tenant denial tests | Not started |
| QA-04 | Test performance with large node trees and media-heavy templates | Performance | EDT-01–08 | Defined interaction and public-render budgets | Not started |
| QA-05 | Deploy to staging, migrate a pilot tenant, and verify rollback | Release | All prior | Staging checklist and rollback record | Not started |
| QA-06 | General availability release with feature flag and support runbook | Release | QA-01–05 | Release notes, monitoring, and incident path | Not started |

## Parallel execution lanes

| Lane | Can run in parallel | Must wait for |
|---|---|---|
| Architecture and contracts | Audit, schema, threat model, event taxonomy | None |
| SEO backend | Settings, resolver, schema generators, sitemap/redirect tests | Contract baseline |
| Theme/library backend | Metadata, versions, tokens, dependency checks | Theme/template contracts |
| Importer/security | Parser fixtures, sanitizer, asset mapping | Document schema and security policy |
| Admin UI | Library screens, tracker, editor shell prototypes | Stable API contracts before integration |
| QA/fixtures | Unit fixtures, security fixtures, compatibility fixtures | Each contract it validates |

Parallel lanes should use isolated worktrees or clearly named branches when multiple contributors are active. Shared migrations and renderer changes must be integrated by one owner in dependency order.

## Definition of done for the program

The program is ready for pilot release when a tenant can configure global SEO defaults, activate a theme, install and reuse a template, safely import a supported HTML package, edit text/styles/layout/assets responsively, preview a page, resolve SEO/accessibility warnings, publish an immutable version, and roll back. Existing pages must still render, all data must remain tenant-scoped, unsafe content must be rejected or quarantined, and the existing test baseline must remain green.
