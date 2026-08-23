# Research working notes

## Repository review
- Slate is a lean, multi-tenant PHP 8.4/MySQL application shell with a WordPress-style plugin system.
- Core includes admin shell, auth/roles, settings, customer portal, audit log, hooks, public router, cron, notifications, and upload helpers.
- Bundled plugins include shop, Stripe payment, forms, booking, content builder, SEO, media, shipping, and emails.
- Design language: dark sidebar, off-white canvas, white cards, blue accent, responsive admin shell, card-row lists, right-rail detail pages.
- Existing strategic gaps noted in README/AUDIT include calendar sync, waitlist, multi-timezone slot computation, membership plans, revisions, a more advanced forms builder, and storefront coupon/total unification. These are source-repository claims, not independently verified product analytics.
- Existing quality baseline in the repository says 89 unit, 23 integration, and 21 smoke tests; must verify before committing.

## Provided MCP page
- URL opened: https://greenlightinduction.rakibhasaan.com/slate/mcp.php
- Response: {"error":"Bearer token required"}
- Configured Slate MCP health check succeeded: tenant_id 1, version 1.0.0, modules users, roles, settings, plugins, media, forms, shop, bookings, memberships, seo, content.
- Available MCP operations are health, capabilities, preview, and execute; mutations require preview plus a one-time token. No mutation has been attempted.

## External research checkpoints
- G2 category page opened but returned no extractable content in this browser session; do not treat it as verified data.
- Search result signals indicate Jira, Notion, Asana, and monday.com are commonly used/adopted in category comparisons, but search snippets are not sufficient as final evidence.
- Search result signals indicate official pricing pages exist for all ten candidate vendors; exact prices must be verified from official pages and date-stamped.

## Deployment verification
- The live route https://greenlightinduction.rakibhasaan.com/slate/admin/growth_lab.php currently returns the site’s branded 404 page because the local repository changes have not been deployed to production. No login or mutation was attempted.
