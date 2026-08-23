# Live Slate security audit — 2026-08-23

## Scope

This report records a conservative, non-destructive audit of the live Slate site at `https://greenlightinduction.rakibhasaan.com/slate` and its MCP endpoint at `/slate/mcp.php`. The checks covered HTTPS behavior, security headers, common sensitive paths, unauthenticated MCP behavior, authenticated MCP health, and live capability discovery. No brute force, fuzzing, exploit delivery, destructive request, authenticated mutation, or dependency exploitation was performed.

## Results

| Check | Result | Assessment |
|---|---|---|
| HTTPS site availability | HTTP 200; final URL normalized to `/slate/` | Pass |
| HSTS | `max-age=31536000; includeSubDomains` | Pass |
| `X-Content-Type-Options` | `nosniff` | Pass |
| `X-Frame-Options` | `SAMEORIGIN` | Pass |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Pass |
| `.env` exposure | HTTP 403 | Pass |
| Database schema exposure | `/db/schema.sql` returned HTTP 403 | Pass |
| Internal PHP exposure | `/includes/Mcp.php` returned HTTP 403 | Pass |
| Git metadata exposure | `/.git/config` returned HTTP 403 | Pass |
| Log exposure | `/error_log` returned HTTP 403 | Pass |
| Composer manifest exposure | `/composer.json` returned HTTP 403 | Pass |
| MCP preflight | `OPTIONS` returned HTTP 204 | Pass |
| Unauthenticated MCP request | HTTP 401 with `Bearer token required` | Pass |
| Authenticated MCP health | `ok: true`, tenant ID 1, version 1.0.0 | Pass |
| MCP capability discovery | 11 modules and 7 actions returned | Pass |

## Live MCP capability result

The authenticated live endpoint currently advertises these modules:

```text
users, roles, settings, plugins, media, forms, shop, bookings, memberships, seo, content
```

The action vocabulary is:

```text
read, list, test, create, edit, write, delete
```

The live mutation policy is preview first, followed by execution with a single-use confirmation token. The advertised direct-access restrictions are SQL, filesystem, and PHP execution.

## Findings and limitations

The sampled perimeter checks passed. This is not a substitute for a full authenticated application penetration test, server configuration review, dependency audit, source-code audit, or database privilege review. The audit did not attempt password testing, rate-limit testing, authenticated privilege escalation, file upload abuse, payment actions, webhook replay, or destructive CRUD operations.

The site exposes a public directory landing page at `/slate/`; this is not automatically a vulnerability, but production hardening may replace it with a deliberate landing page or redirect if directory discovery is not desired. The current sampled headers did not include a Content-Security-Policy or Permissions-Policy header; consider adding them after validating compatibility with the site’s inline scripts, embeds, forms, and third-party assets.

The MCP token was used only through the configured secure connector. It was not written into this report, the repository, or any public artifact. Rotate the token if it is ever exposed in a command, screenshot, issue, or chat transcript.

## Recommended follow-up

Maintain the existing security workflow with secret scanning, PHP and JavaScript dependency audits, CodeQL where configured, and E2E security-boundary tests. Add staging-only authenticated permission tests for each admin module, verify rate limiting at the reverse proxy, review database credentials and least privilege, and consider a CSP report-only rollout before enforcing a policy.
