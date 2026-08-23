# Slate Next-Generation Platform Plan

**Prepared by Manus AI · 23 August 2026**

## Executive direction

Slate should evolve in two coordinated tracks. The first track upgrades every existing business module so that it becomes more intelligent, connected, observable, and easier to operate. The second track establishes a platform-level **Theme, Template, and Visual Editing System** so tenants can install, reuse, customize, and safely publish front-end experiences without rebuilding HTML manually.

The most important architectural decision is to avoid treating the visual editor as a free-form HTML text editor. A raw HTML editor is easy to start but difficult to make safe, responsive, undoable, reusable, SEO-aware, and compatible with future components. Slate should instead import HTML into a normalized, versioned **page document model**, preserve the source where possible, and render the edited document through the existing Template Engine, Theme Engine, Content Builder, SEO Manager, Asset Manager, and public rendering pipeline.

> **Target product promise:** Build a complete branded website or landing page from a reusable template, visually edit every permitted element, preserve responsive behavior and SEO structure, and publish safely with version history and rollback.

## 1. Current platform baseline

The repository already contains the key foundations required for this direction. The plugin inventory includes content-builder, SEO, forms, media-library, booking, booking-plus, membership, shop, Stripe payment, and small-business-kit modules. Slate’s rendering documentation already defines a separation between the **Template Engine**, which owns the document frame and named regions, and the **Theme Engine**, which owns tokens, font pairing, chrome, and component defaults.[1]

That existing boundary is valuable. The new platform should extend it rather than create a separate page system. Themes should change values and variants; templates should define structure and regions; pages should contain content and layout; blocks should provide reusable behavior; and the SEO and asset pipelines should remain responsible for head metadata and front-end bundles.

## 2. Upgrade all current features into next-generation capabilities

| Existing feature | Current likely value | Next-generation upgrade | Missing capability to add | Priority |
|---|---|---|---|---:|
| Admin dashboard | Snapshot of users, customers, plugins, and activity | Growth command center with operational health, activation, revenue, and exception signals | Cross-module KPI model and role-specific dashboards | P0 |
| Forms | Collect structured requests and contact data | Intelligent intake that qualifies, routes, scores, and starts a workflow | Schema-driven fields, conditional logic, spam protection, partial saves, consent, versioning, webhook retries | P0 |
| Contacts / customers | Store identity and relationships | Unified customer profile and timeline | Consent history, source attribution, tags, lifecycle stage, duplicate resolution, portal activity | P0 |
| Booking | Schedule appointments | Availability-aware service orchestration | Waitlist, timezone intelligence, buffers, rescheduling policy, reminders, no-show automation, capacity exceptions | P0 |
| Booking Plus | Extended booking workflows | Multi-resource and package scheduling | Group bookings, recurring events, resource allocation, deposits, staff substitution, conflict explanations | P1 |
| Membership | Plans and subscriptions | Lifecycle membership engine | Entitlements, usage tracking, renewal rescue, dunning, pause/freeze, member portal, churn signals | P1 |
| Payments / Stripe | Checkout and charge records | Revenue operations layer | Payment links, deposits, installment plans, failed-payment recovery, refunds, reconciliation, revenue attribution | P0 |
| Shop | Products, coupons, shipping, storefront | Unified service-plus-commerce catalog | Bundles, subscriptions, inventory alerts, tax/shipping rules, abandoned checkout, fulfillment status | P1 |
| Content Builder | CMS posts and blocks | Structured content and reusable page composition | Page document model, block schemas, dynamic data bindings, localization, scheduled publishing, content experiments | P0 |
| SEO plugin | SEO metadata and render hooks | SEO operating system | Sitewide defaults, schema graph, sitemap, redirects, canonicals, robots rules, image SEO, internal-link suggestions, SEO health checks | P0 |
| Media Library | Store and select assets | Asset intelligence and delivery pipeline | Folders, tags, focal point, responsive variants, compression, WebP/AVIF, alt-text assistant, usage tracking, CDN-ready URLs | P0 |
| Notifications | Send operational notices | Event-driven communication center | Templates, channels, preferences, delivery logs, retries, quiet hours, localization, lifecycle campaigns | P1 |
| Audit log | Record administrative actions | Explainable activity and compliance layer | Search, filters, before/after diff, export, retention, actor/device context, automation trace | P1 |
| Roles / permissions | Protect admin features | Policy-based access control | Object-level rules, field-level protection, approval policies, temporary access, access review | P1 |
| MCP / AI connection | Tenant-scoped AI endpoint and confirmed mutations | Governed AI work concierge | Read-only summaries, action previews, policy checks, tool scopes, explainability, rollback, usage log | P0 |
| Plugins | Modular extensions | Capability marketplace | Dependency checks, version compatibility, signed packages, health checks, upgrade/rollback, extension permissions | P1 |
| Settings | Configure tenant options | Global control plane | Environment-aware settings, import/export, settings search, defaults, audit, validation, safe reset | P1 |
| Customer portal | Customer access and self-service | Customer operating inbox | Timeline, messages, files, invoices, booking, payment, status, consent, support handoff | P0 |

### Recommended upgrade pattern for each module

Every module should be upgraded through the same product pattern. First, define the module’s core records and lifecycle states. Second, emit stable domain events when records are created, changed, paid, cancelled, published, or completed. Third, expose role-specific views for owner, operator, staff, customer, and technical evaluator. Fourth, add automation recipes and exception rules. Fifth, add measurable outcomes. Sixth, provide a safe API or MCP surface with tenant scope, permission checks, audit logging, and idempotency.

This pattern prevents a collection of disconnected feature upgrades. It creates the platform behavior needed by Growth Lab: a form submission can become a customer, a booking, a payment request, an exception, an audit entry, a notification, and a customer timeline event without every integration being hand-coded separately.

## 3. SEO-first global settings system

### Global SEO control plane

Create a tenant-scoped SEO settings layer that resolves values in the following precedence order:

1. Page or record override.
2. Content-type or template rule.
3. Sitewide global setting.
4. Safe system fallback.

The global settings should include site name, default title pattern, default meta description, canonical host, default social image, language, locale, timezone, robots default, organization details, publisher details, logo, contact points, social profiles, verification tokens, breadcrumb behavior, and schema defaults. Sensitive verification tokens should be permission-protected and audited.

### Required SEO features

| SEO area | Required behavior |
|---|---|
| Title and description | Character guidance, preview, fallback pattern, duplicate warning |
| Canonical URL | Automatic canonical generation with explicit override and validation |
| Robots | Sitewide and page-level rules with conflict warnings |
| Sitemap | XML sitemap index for pages, posts, products, services, and media where appropriate |
| Structured data | Organization, WebSite, WebPage, BreadcrumbList, Article, Product, Service, Event, FAQ, and LocalBusiness schemas with JSON-LD validation |
| Social metadata | Open Graph and Twitter/X cards with image fallback and preview |
| Headings | Heading hierarchy warning and semantic HTML checks |
| Images | Alt text, dimensions, lazy-loading, responsive source selection, and decorative-image control |
| Internal links | Suggested contextual links and orphan-page detection |
| Redirects | Versioned 301/302 management, conflict detection, import/export, and loop protection |
| Indexability | Noindex, password/private state, draft preview, and publish-state alignment |
| Performance | Asset size warnings, critical CSS strategy, font loading guidance, and layout-shift checks |
| Accessibility | Contrast, labels, keyboard focus, landmark, and form error checks that overlap with SEO quality |

### SEO-safe theme requirements

A theme must declare its semantic regions, heading behavior, schema defaults, font loading policy, image handling, and supported template types. A theme upload should be rejected or quarantined if it contains unsafe scripts, invalid markup, broken asset references, missing viewport behavior, or inaccessible navigation patterns.

## 4. Theme library architecture

### Theme versus template versus page

| Object | Owns | Reusable by | Example |
|---|---|---|---|
| Theme | Tokens, fonts, chrome, component defaults, semantic style rules | Tenant and all pages using the theme | “Slate Horizon” color and type system |
| Template | Document frame, named regions, page schema, starter content | Multiple pages and tenants if allowed | Landing, blog, service, product, booking, storefront |
| Page | Page-specific content, block tree, SEO overrides, publication state | One route or a page family | `/about`, `/services`, `/contact` |
| Block | Reusable semantic component with schema and rendering | Pages and templates | Hero, feature grid, pricing, FAQ, booking CTA |
| Section | Layout group and responsive rules | Pages and templates | Two-column content section |
| Asset | Image, icon, font, video, document and metadata | Themes, templates, pages, messages | Hero image with focal point and alt text |
| Global part | Shared header, footer, announcement, cookie notice | Many pages within a tenant | Primary header version 2 |

### Theme library capabilities

The library should support unlimited tenant-owned themes and templates subject to storage and permission policy. Each item needs a stable ID, name, slug, description, preview image, version, author, source, license, supported content types, required plugins, design tokens, asset manifest, schema version, status, and compatibility range.

The user should be able to upload a package, import a ZIP, duplicate an existing theme, create from a blank starter, or save the current page as a reusable template. The package should be inspected before activation, with a preview and dependency report. Reuse should be possible within the tenant, and optional marketplace distribution should be a later phase.

### Import pipeline for arbitrary HTML templates

1. Upload package through a CSRF-protected admin form.
2. Store the original archive and calculate a checksum.
3. Unpack into an isolated staging directory outside the public execution path.
4. Scan HTML, CSS, JavaScript, SVG, fonts, and URLs.
5. Sanitize or quarantine executable scripts and unsafe attributes.
6. Resolve local asset references into the Media Library.
7. Parse HTML into a normalized document tree.
8. Detect semantic regions such as header, navigation, main, aside, footer, forms, cards, and repeated components.
9. Generate an import report showing warnings and unsupported features.
10. Render a sandbox preview at desktop, tablet, and mobile widths.
11. Let the user map regions and content fields.
12. Publish only after validation and explicit activation.

The original source should remain available as an immutable import artifact. The editable document is a separate versioned representation so that a future parser improvement does not silently alter a published page.

## 5. Premium visual HTML editor

### Editor experience

The editor should feel like a professional design tool while remaining understandable to nontechnical users. Use a three-panel responsive layout: a left panel for layers and blocks, a central canvas for the live page, and a right inspector for content, layout, style, responsive behavior, SEO, accessibility, and advanced attributes. A compact top bar should provide undo, redo, responsive preview, zoom, preview, save draft, compare versions, and publish.

| Editor area | Capabilities |
|---|---|
| Layers panel | DOM-like tree, semantic labels, search, reorder, lock, hide, duplicate, group, rename, multi-select |
| Block panel | Reusable blocks, imported sections, global parts, tenant library, drag-and-drop insertion |
| Canvas | Select, move, resize where safe, inline text editing, drop zones, hover outlines, keyboard navigation |
| Inspector | Content, spacing, display, colors, typography, borders, effects, layout, responsive overrides, custom classes |
| Asset picker | Search, upload, crop, focal point, alt text, dimensions, responsive variants, replace/remove |
| Responsive controls | Desktop/tablet/mobile previews, breakpoint visibility, stacking, gap, width, typography, image behavior |
| SEO panel | Slug, title, description, canonical, robots, social image, schema type, heading outline, SEO score |
| Accessibility panel | Contrast, labels, landmarks, focus order, alt text, keyboard and form checks |
| History | Undo/redo, autosave, named revisions, diff, restore, draft/published comparison |
| Publish flow | Validation report, preview URL, scheduled publish, approvals, rollback, cache purge |

### Editing every HTML tag safely

The editor should expose a controlled property model for supported semantic and layout tags rather than allowing arbitrary destructive DOM mutations. The supported model should cover text, links, buttons, images, videos, lists, tables, forms, sections, articles, cards, navigation, and custom wrappers. Advanced users may access an “Advanced” area for tag name, ID, classes, data attributes, and custom CSS, but unsafe tags, event-handler attributes, external script injection, and server-side code must be blocked or require a trusted developer role.

The editor must preserve a mapping between the normalized node and its source HTML location where feasible. Each node should have a stable editor ID so that copy/paste, undo, analytics, personalization, and future migrations do not depend on brittle CSS selectors.

### Drag and drop model

Drag and drop should operate on valid drop zones rather than arbitrary coordinates. Each block declares allowed parents, allowed children, minimum and maximum children, and responsive behavior. The editor should show an insertion line, a drop-zone highlight, and a reason when a drop is invalid. Keyboard move commands must be available for accessibility and precision.

### Copy, paste, duplicate, and delete

Copy and paste should work inside the editor using a versioned Slate clipboard payload that includes the node tree, referenced assets, token references, and source metadata. Pasting external HTML should pass through the same sanitizer and importer used by template upload. Delete should be reversible, warn when deleting a global part, and preserve the node in the undo history until the revision is discarded.

### Style system

Styles should be stored as structured declarations linked to design tokens wherever possible. The editor should support class-based styles, component variants, local overrides, responsive overrides, pseudo-state previews, CSS custom properties, and token creation. It should discourage one-off inline styles by showing whether a value is local, inherited, token-based, or class-based.

The style inspector should cover display, position, flex, grid, width, max-width, min-height, spacing, typography, color, background, border, radius, shadow, transform, opacity, overflow, object-fit, and transitions. Positioning controls should use safe presets first, with arbitrary positioning restricted to advanced mode because it can create fragile responsive layouts.

### Responsive behavior

Responsive editing must be explicit. Store breakpoint rules in the document model, show inherited values, and warn when a desktop change breaks a mobile constraint. Use a small controlled set of platform breakpoints, allow tenant-defined breakpoints later, and include a “reset to inherited” control for every property.

## 6. Proposed data model

| Table / object | Purpose |
|---|---|
| `themes` | Tenant-owned theme metadata, status, version, package checksum, compatibility, and activation state |
| `theme_tokens` | Structured color, typography, spacing, radius, shadow, and motion tokens |
| `theme_assets` | Asset manifest and role mapping for logos, fonts, icons, and starter media |
| `templates` | Reusable template metadata, content type, regions, schema, preview, and dependencies |
| `template_versions` | Immutable template documents, source archive reference, normalized tree, and validation report |
| `pages` | Route, template, theme override, publication state, and current version pointers |
| `page_versions` | Immutable page document tree, SEO values, asset references, author, and revision metadata |
| `global_parts` | Reusable headers, footers, announcements, navigation, and shared sections |
| `editor_assets` | Upload/import metadata, dimensions, variants, focal point, alt text, and usage count |
| `editor_drafts` | Autosave snapshots and collaborative or recovery state |
| `editor_publish_jobs` | Validation, scheduled publish, cache purge, and result state |
| `redirects` | Tenant-scoped redirect rules with conflict and loop validation |
| `seo_audits` | Page and site audit results, score, warnings, and run metadata |
| `editor_permissions` | Optional object-level permissions for template and global-part editing |

All tenant-owned rows need `tenant_id`, creation and update metadata, and indexes for route, status, slug, and active version. Version tables should be append-only except for retention cleanup. Published versions must never be mutated in place.

## 7. Security and safety requirements

The visual editor is a high-risk feature because it handles HTML, CSS, JavaScript, uploads, and public rendering. The first release must therefore enforce the following controls:

| Risk | Required control |
|---|---|
| Stored XSS | Sanitize imported and pasted HTML; remove event handlers, unsafe URLs, script tags, and dangerous SVG content |
| Tenant escape | Enforce tenant scope at repository and service layers; never trust client-provided tenant IDs |
| File abuse | Validate MIME and content signatures, size limits, image re-encoding, quarantine, and non-executable storage |
| CSS escape | Scope custom CSS, limit external imports, reject unsafe constructs, and provide a trusted developer capability separately |
| Broken public pages | Staged preview, validation, versioned publish, health check, and one-click rollback |
| Data loss | Autosave, immutable revisions, undo/redo, named checkpoints, and restore tests |
| Unsafe AI changes | Read-only default, action preview, permission checks, single-use confirmation, audit trail, and rollback where possible |
| SEO regressions | Pre-publish title, canonical, robots, heading, schema, link, and performance checks |
| Accessibility regressions | Contrast, labels, landmarks, keyboard path, focus, and responsive checks |

## 8. Phased implementation roadmap

### Phase 0 — Contract and audit

Document the current Content Builder, SEO API, public router, rendering pipeline, asset behavior, migration conventions, and plugin hooks. Define the normalized page document schema and version it before building the editor. Add fixtures for a simple landing page, a service page, a blog page, and an imported arbitrary HTML page.

**Exit criteria:** schema reviewed, rendering boundary agreed, unsafe-import policy documented, baseline tests green, and no existing plugin behavior changed.

### Phase 1 — SEO foundation and global settings

Build tenant-scoped global SEO settings, fallback resolution, structured-data generation, sitemap and robots handlers, redirect management, SEO preview, and page-level overrides. Integrate the SEO head stage with the Template Engine instead of adding a second head renderer.

**Exit criteria:** public pages have deterministic titles, descriptions, canonicals, robots behavior, social tags, and JSON-LD; conflicts generate clear admin warnings.

### Phase 2 — Theme library and reusable templates

Build theme and template metadata, versioning, activation, duplication, preview, dependency checks, package import, and library browsing. Migrate existing content-builder branding presets into platform-wide theme tokens without removing backward compatibility.

**Exit criteria:** a tenant can install, preview, activate, duplicate, and roll back a theme; a template can be reused across multiple pages without sharing mutable content accidentally.

### Phase 3 — Normalized page document and safe HTML importer

Implement HTML/CSS parsing, sanitization, asset extraction, semantic-region detection, source preservation, normalized node tree, import report, and sandbox preview. Start with static HTML and CSS. Defer arbitrary JavaScript behavior and server-side template code until a trusted extension model exists.

**Exit criteria:** representative templates import without unsafe content, assets are mapped, warnings are actionable, and the output renders consistently at target breakpoints.

### Phase 4 — Visual editor MVP

Build the layer tree, canvas selection, inline text editing, block insertion, basic drag-and-drop, asset replacement, delete/duplicate, class editing, structured style inspector, responsive preview, undo/redo, autosave, draft preview, and publish validation.

**Exit criteria:** a nontechnical user can edit a supplied template, replace text and images, change layout and styles, preview desktop/tablet/mobile, save a draft, publish, and roll back.

### Phase 5 — Premium editor capabilities

Add reusable global parts, multi-select, copy/paste payloads, component variants, token editing, advanced layout controls, CSS states, keyboard navigation, command palette, search, version compare, scheduled publishing, approval workflows, and optional collaboration.

**Exit criteria:** the editor supports a professional workflow without requiring raw HTML for common tasks and retains stable responsive behavior after repeated edits.

### Phase 6 — Upgrade all business modules

Apply the event, automation, exception, timeline, analytics, and permissions pattern to forms, contacts, booking, membership, payments, shop, notifications, audit, MCP, and customer portal. Connect these modules to page blocks and dynamic data bindings such as service lists, booking widgets, product cards, testimonials, pricing, and customer-specific status.

**Exit criteria:** a page can safely render dynamic business data, a form can start a workflow, a booking can create payment and portal events, and cross-module exceptions appear in the Growth Lab command center.

### Phase 7 — Marketplace and optimization

Add theme/template ratings, search, tags, compatibility checks, install analytics, licensing metadata, partner submissions, A/B page variants, conversion analytics, and template performance dashboards. Keep marketplace distribution separate from the first tenant-owned library release.

**Exit criteria:** library usage, template activation, page performance, SEO health, and conversion outcomes can be measured without exposing tenant data.

## 9. Acceptance criteria for the editor

| Area | Acceptance test |
|---|---|
| Import | Upload a valid HTML template with local CSS and images; import report identifies assets and warnings |
| Edit | Change any supported text, link, image, class, spacing, color, typography, and layout value visually |
| Layout | Drag supported blocks into valid zones; invalid drops are rejected with an explanation |
| Responsive | Make a desktop change, inspect tablet/mobile, override a mobile value, and reset to inherited |
| Assets | Upload, replace, remove, crop, set focal point, add alt text, and preserve responsive variants |
| History | Undo, redo, autosave recovery, named revision, compare, restore, and rollback work reliably |
| SEO | Publish is blocked or warned when required metadata, headings, canonical, schema, or accessibility checks fail |
| Security | Pasted/imported script and event-handler attributes are removed; unsafe files are quarantined |
| Tenancy | A user cannot view or reference another tenant’s themes, templates, pages, assets, or revisions |
| Publishing | Draft preview is isolated; publish creates an immutable version and rollback is one action |
| Performance | Editor remains responsive on a realistic page with many nodes and assets; public pages do not ship editor code |
| Compatibility | Existing Content Builder pages and current themes continue to render during migration |

## 10. Recommended build order for business value

The highest-value sequence is not to build the entire editor first. Begin with the SEO control plane and theme/template model because they define the contracts that the editor will consume. Then build the importer and document model, followed by the editor MVP. In parallel, upgrade forms, contacts, booking, payments, and portal events so that the first reusable templates can contain meaningful dynamic workflows rather than static visuals.

The first public template packs should target three high-value use cases: an appointment-led service site, a professional-services site, and a service-plus-commerce site. Each pack should ship with SEO defaults, accessible sections, a form, booking or product calls to action, responsive behavior, and a conversion-oriented content structure.

## 11. Progress tracker

| Workstream | Status target | First proof point |
|---|---|---|
| Module event contracts | Planned | Stable events emitted from forms, contacts, booking, payments, content, and portal |
| SEO global settings | Planned | One sitewide settings page and deterministic head output |
| Theme library | Planned | Upload, preview, duplicate, activate, rollback |
| Template library | Planned | Reusable landing, service, and storefront templates |
| HTML importer | Planned | Safe import report and normalized document tree |
| Visual editor MVP | Planned | Edit text, style, layout, media, and responsive overrides |
| Editor history | Planned | Autosave, undo/redo, revisions, rollback |
| Dynamic blocks | Planned | Booking, forms, products, services, testimonials, and pricing blocks |
| Exception intelligence | Planned | Cross-module operator queue |
| Governed AI | Planned | Read-only summary plus approved action preview |
| SEO and accessibility audits | Planned | Pre-publish validation report |
| Marketplace | Later | Searchable tenant library and partner-ready package format |

## 12. Important scope boundaries

Do not permit arbitrary PHP, server-side template execution, unrestricted JavaScript, or cross-tenant asset references in the first editor release. Do not mutate existing published HTML in place. Do not migrate every existing renderer at once; use an adapter and compatibility layer while the new pipeline is proven. Do not promise pixel-perfect import for every framework-generated template before measuring parser coverage. Do not make AI-generated design changes publishable without the same validation and confirmation gates as human changes.

The visual editor should be ambitious in experience but conservative in execution. A premium editor is defined by speed, clarity, stable undo, predictable responsive behavior, excellent asset handling, strong keyboard support, and trustworthy publishing—not by exposing every possible CSS property on the first screen.

## References

[1]: ../docs/05-Rendering/theme-and-template-engine.md "Slate Theme & Template Engine"
[2]: ../docs/05-Rendering/seo-rendering.md "Slate SEO Rendering"
[3]: ../STUDIO_BUILD_BRIEF.md "Slate Studio Build Brief and Platform Constraints"
