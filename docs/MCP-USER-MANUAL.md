# Slate MCP User Manual

## Overview

Slate MCP connects an authorized AI client to the Slate administration system through a remote HTTPS endpoint. It is intended for Manus, Claude Desktop, Cursor, and other MCP-compatible clients. The connection is tenant-scoped and exposes only the resources and actions returned by `slate_admin_capabilities`.

> Slate MCP is a controlled administration interface, not a remote shell. It does not expose arbitrary SQL, PHP execution, filesystem access, secrets, or unrestricted administrator form submission.

**Production endpoint:**

```text
https://greenlightinduction.rakibhasaan.com/slate/mcp.php
```

## One-time setup

1. Sign in to Slate with an administrator account permitted to manage MCP settings.
2. Open **Admin → AI / MCP**.
3. Select **Generate / rotate token**.
4. Copy the raw token immediately. Slate stores only a password hash and does not display the raw token again.
5. In the AI client, add a custom HTTPS MCP server using the endpoint above and store the token in the client’s secure secret field as a bearer credential.
6. Test with `slate_admin_health`.
7. Call `slate_admin_capabilities` and confirm that the expected modules are available.

Rotate the token immediately if it has been copied into a public issue, document, screenshot, log, or chat. Rotation revokes the previous active token for the tenant.

## Available tools

| Tool | Use |
|---|---|
| `slate_admin_health` | Verify authentication, tenant context, Slate version, and connection availability. |
| `slate_admin_capabilities` | Discover all allowed modules, resources, actions, redaction rules, and unsupported access categories. |
| `slate_admin_preview` | Validate an operation and create a five-minute, operation-bound confirmation token without changing data. |
| `slate_admin_execute` | Execute exactly the operation represented by a valid, single-use confirmation token. |

## Supported modules

| Module | Typical work |
|---|---|
| `users` | Review and manage tenant users and customers; inspect authentication-token records with sensitive values redacted. |
| `roles` | Review and manage tenant roles and role permissions. |
| `settings` | Read, test, create, edit, write, and delete tenant settings using schema-allowed fields. |
| `plugins` | Inspect installed plugin records. Arbitrary PHP execution and unreviewed plugin installation are not supported. |
| `media` | Inspect media-library records and usage relationships. Arbitrary file upload or filesystem operations are not exposed. |
| `forms` | Manage form definitions, submissions, webhooks, spam logs, webhook logs, and contact-form records when installed. |
| `shop` | Manage categories, products, variants, coupons, orders, order items, customers, carts, and cart items. |
| `bookings` | Manage services, categories, providers, hours, breaks, appointments, customers, locations, resources, add-ons, custom fields, and booking configuration. |
| `memberships` | Manage plans, profiles, subscriptions, wallets, and wallet transactions. |
| `seo` | Test and inspect the `seo_settings` resource. Post SEO metadata is available only where the installed adapter exposes it. |
| `content` | Manage content posts, post metadata, post types, menus, taxonomies, terms, and term relationships when installed. |

The common action vocabulary is `read`, `list`, `test`, `create`, `edit`, `write`, and `delete`. An optional plugin resource is usable only if its table and adapter are installed in the live tenant.

## Safe task workflow

### Health and discovery

Call `slate_admin_health` first. Then call `slate_admin_capabilities`. Do not assume that a resource exists because it is present in documentation; use the live capability response.

### Read and reporting

For a report, select an advertised module and resource, prepare a `read`, `list`, or `test` preview with exact filters and limits, then execute it with the returned token. Treat returned values as tenant-scoped operational data. Do not expose sensitive fields in reports.

### Create, edit, write, and delete

Describe the intended change before preparing a preview. Call `slate_admin_preview` with the exact module, action, resource, and payload. Review the returned operation. Execute it only with the same arguments and its confirmation token. Request a new preview if any value changes. A token expires after five minutes and can be used only once.

### Content publishing

Create content as a draft by default. Review the title, slug, excerpt, layout, links, and SEO metadata. Use a separate preview and execution request to change the status to `published`. Publishing creates a public-site side effect and should require an explicit user instruction.

### Plugin work

Use MCP to inspect plugin records and plugin-related settings only where supported. Build or install a new plugin through the GitHub and deployment lifecycle: manifest, bootstrap, permissions, tenant-aware migration, admin UI, tests, CI, artifact, approval, and live verification. Do not ask MCP to execute PHP or modify arbitrary server files.

## Security controls

The server requires `Authorization: Bearer <token>` over HTTPS. Tokens are tenant-scoped and stored as password hashes. Returned rows redact passwords, password hashes, tokens, token hashes, secrets, API keys, private keys, SMTP passwords, Stripe secret keys, access tokens, and refresh tokens.

All MCP token issuance, revocation, confirmation creation, and executed operations are written to Slate’s tenant audit log. Resources containing `tenant_id` are filtered to the authenticated tenant. Role permissions are scoped through tenant-owned roles. Resources without a safe tenant scope fail closed.

## Example AI tasks

| User request | Expected behavior |
|---|---|
| “Check the MCP connection.” | Call `slate_admin_health`. |
| “List everything this AI can manage.” | Call `slate_admin_capabilities`. |
| “How many published posts do we have?” | Preview and execute a filtered content `test` or `list`. |
| “Create a draft blog post about MCP.” | Preview and execute content `create` with a draft status. |
| “Publish post 232.” | Preview and execute a content status update only after explicit approval. |
| “Find pending shop orders.” | Preview and execute an allow-listed shop `list`. |
| “Update the site tagline.” | Preview and execute a settings `edit` or `write`. |
| “Check SEO configuration.” | Preview and execute an SEO `test` or `list`. |
| “Remove an obsolete form submission.” | Preview and execute a forms `delete` for a specified ID. |

## Unsupported operations

MCP cannot run arbitrary SQL, execute PHP, read or write arbitrary files, retrieve passwords or secrets, upload files directly, install server packages, bypass Slate permissions, act against another tenant, or guarantee a business workflow that has not been implemented as an adapter. Payment capture, sending email, changing OAuth connections, and other plugin side effects require dedicated reviewed tools.

## Troubleshooting

| Symptom | Resolution |
|---|---|
| HTTP 401 | Generate a new token, copy it completely, update the AI client, and retry health. |
| HTTP 400 during initialization | Confirm the current `mcp.php` is deployed and supports JSON-RPC notifications without an `id`. |
| Tool schema parsing error | Deploy the current endpoint where empty JSON Schema `properties` values are encoded as objects. |
| Resource rejected | Call `slate_admin_capabilities`; use only advertised resources installed in the live tenant. |
| Mutation rejected | Request a new preview; do not reuse an expired, used, or mismatched token. |
| Records are empty | Check tenant context, filters, plugin installation, and whether records exist in the live database. |
| Public content is not visible | Confirm the post is published, the slug is correct, routing is active, and the public URL returns successfully. |

## Operational checklist

Use health and capabilities after deployment. Start with read-only resource tests. Keep tokens only in secure secret storage. Use drafts for content. Review audit entries after mutations. Maintain a rollback copy before production deployment. Never use a production database for automated test probes.
