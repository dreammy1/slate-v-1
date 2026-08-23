# Slate MCP administration guide

## Purpose

Slate includes a remotely hosted HTTPS Model Context Protocol (MCP) endpoint for connecting Manus, Claude Desktop, Cursor, and other MCP-compatible AI clients to tenant-scoped administration tools. The endpoint gives an AI assistant a controlled interface for discovering Slate capabilities, reading operational data, testing resources, preparing changes, and executing approved changes.

> MCP does not give an AI unrestricted access to the server. It exposes only the explicitly allow-listed Slate resources and actions described in this document.

The production endpoint is:

```text
https://greenlightinduction.rakibhasaan.com/slate/mcp.php
```

## What an AI connected through Slate MCP can do

An authorized AI client can inspect the connected tenant, discover available modules, list or read records, test resource availability, create records, edit existing records, write records, and delete records. All operations are routed through the Slate MCP service rather than arbitrary SQL, direct PHP execution, unrestricted filesystem access, or raw admin form submission.

| Task category | Examples of supported work |
|---|---|
| Discovery | Check connection health, identify tenant context, list modules, inspect available resources and supported actions. |
| Reporting | Count records, list records, retrieve a specific record by ID, filter records by allow-listed columns, and summarize operational data. |
| Diagnostics | Test whether a resource exists and return its tenant-scoped record count. |
| Content administration | Create, read, list, edit, write, and delete content-builder posts, menus, post types, taxonomies, terms, metadata, and relations when the corresponding resource exists in the database. |
| User administration | Read, list, test, create, edit, write, and delete tenant-scoped users and customer records. Customer authentication-token resources can be inspected only through redacted, allow-listed data. |
| Access control | Inspect and manage tenant-scoped roles and role permissions, subject to the resource schema and tenant checks. |
| Site configuration | Read, create, edit, write, test, and delete tenant-scoped settings using allow-listed fields. |
| Plugin operations | Inspect and manage installed plugin records through the `plugins` resource; MCP does not execute plugin PHP or install arbitrary server code. |
| Media operations | Inspect media-library records and usage relationships through the available media resources. File transfer or arbitrary filesystem manipulation is not exposed. |
| Forms operations | Inspect and manage form definitions, submissions, webhooks, spam logs, webhook logs, and contact-form records where tables are installed. |
| Shop operations | Manage catalog, categories, products, variants, coupons, orders, order items, customers, carts, and cart items through tenant-scoped resources. |
| Booking operations | Inspect and manage booking services, categories, providers, schedules, breaks, appointments, customers, locations, resources, add-ons, custom fields, and booking configuration tables. |
| Membership operations | Manage membership plans, profiles, subscriptions, wallets, and wallet transactions through the available tenant-scoped resources. |
| SEO operations | Inspect and test the SEO settings resource. SEO metadata attached to individual content posts is available only when exposed by the installed Slate adapter and schema; the MCP service does not invent unsupported SEO fields. |

## Complete module and resource allow-list

The following resources are currently exposed. A resource is usable only when its corresponding table exists in the connected Slate database. If an optional plugin has not been installed, the server fails closed or reports the resource as unavailable rather than falling back to arbitrary SQL.

| Module | Permitted resources |
|---|---|
| `users` | `users`, `customers`, `customer_auth_tokens` |
| `roles` | `roles`, `role_permissions` |
| `settings` | `settings` |
| `plugins` | `plugins` |
| `media` | `media_files`, `media_usage`, `medialibrary_files` |
| `forms` | `forms_definitions`, `forms_submissions`, `forms_webhooks`, `forms_spam_log`, `forms_webhook_log`, `contact_forms`, `contact_form_submissions` |
| `shop` | `shop_categories`, `shop_products`, `shop_product_variants`, `shop_coupons`, `shop_orders`, `shop_order_items`, `shop_customers`, `shop_carts`, `shop_cart_items` |
| `bookings` | `booking_services`, `booking_categories`, `booking_providers`, `booking_provider_services`, `booking_provider_hours`, `booking_provider_breaks`, `booking_date_overrides`, `booking_appointments`, `booking_customers`, `booking_locations`, `booking_resources`, `booking_service_resources`, `booking_service_addons`, `booking_custom_fields`, `bookingplus_appointment_meta`, `bookingplus_service_config`, `bookingplus_slot_restrictions` |
| `memberships` | `membership_plans`, `membership_profiles`, `membership_subscriptions`, `membership_wallet`, `membership_wallet_txns` |
| `seo` | `seo_settings` |
| `content` | `contentbuilder_posts`, `contentbuilder_post_meta`, `contentbuilder_post_types`, `contentbuilder_menus`, `contentbuilder_taxonomies`, `contentbuilder_terms`, `contentbuilder_term_relations` |

Every exposed resource supports the common action vocabulary advertised by `slate_admin_capabilities`:

```text
read, list, test, create, edit, write, delete
```

The practical fields accepted by an operation are derived from the live table schema. The server accepts only allow-listed columns and ignores or rejects arbitrary column names. Fields such as `id`, `tenant_id`, timestamps, and sensitive credentials receive additional protection.

## Tool reference

### `slate_admin_health`

Returns a read-only availability response containing the connection state, current tenant ID, Slate version, and exposed module names. Use this as the first connection test.

### `slate_admin_capabilities`

Returns the complete module/resource allow-list, action list, mutation policy, sensitive-column redaction list, and unsupported direct-access categories. AI clients should call this before planning administration work because optional plugins may change which tables are available.

### `slate_admin_preview`

Prepares an operation without changing application data. It validates the module, action, resource, and payload against the server’s allow-list, then returns an operation-bound confirmation token. The token expires after five minutes and must be used for exactly the same operation arguments.

Example preview arguments:

```json
{
  "module": "content",
  "action": "create",
  "resource": "contentbuilder_posts",
  "payload": {
    "type": "post",
    "title": "Example article",
    "slug": "example-article",
    "status": "draft",
    "excerpt": "A concise search-friendly summary.",
    "layout": []
  }
}
```

### `slate_admin_execute`

Executes an operation only when it includes a valid, unexpired, single-use confirmation token created for the same operation and payload. A token cannot be reused for a second execution or repurposed for a different record, action, module, resource, or payload.

Example execution arguments:

```json
{
  "module": "content",
  "action": "create",
  "resource": "contentbuilder_posts",
  "payload": {
    "type": "post",
    "title": "Example article",
    "slug": "example-article",
    "status": "draft",
    "excerpt": "A concise search-friendly summary.",
    "layout": []
  },
  "confirmation_token": "TOKEN_RETURNED_BY_PREVIEW"
}
```

## Standard AI task workflow

### Read-only work

For health checks, reporting, and diagnostics, the AI client should first call `slate_admin_capabilities`, select an allow-listed module and resource, request a preview for the intended `read`, `list`, or `test` operation, and then execute that preview with its single-use token. This keeps even read operations visible in the audit trail and ensures the request is bound to a specific resource and filter set.

### Creating or changing data

For create, edit, write, or delete work, the AI client must describe the intended change, call `slate_admin_preview`, present or record the returned confirmation token, and call `slate_admin_execute` only with the unchanged operation arguments. If the user changes the requested title, ID, status, slug, or payload, the AI client must request a new preview instead of reusing the old token.

### Publishing content

Content can be created with a published status when the connected tenant and database schema permit it. A safer editorial workflow is to create a draft, review the resulting admin record and public preview, then issue a separate preview and execution request to change the post status to `published`. Publishing is a real public-site mutation and should always be explicitly requested by the user.

### SEO-aware content work

An AI can create structured content with a search-focused title, readable slug, concise excerpt, descriptive headings, internal-link suggestions, and a clear call to action. Where the installed SEO integration exposes supported post metadata, the AI should use those allow-listed metadata resources or fields. It must not claim that a keyword ranking, backlink, search-indexing result, or analytics outcome has been achieved unless that separate data source is connected and verified.

## Security and governance

Every request requires a bearer token over HTTPS. Tokens are tenant-scoped, stored as password hashes, and managed from the Slate **AI / MCP** administration page. Generating or rotating a token revokes the previous active token for that tenant. Never commit a token to Git, place it in a public document, or include it in an issue, screenshot, browser URL, or chat message.

Slate records token issuance, token revocation, confirmation creation, and executed MCP administration actions in the tenant audit log. Sensitive columns are redacted in returned records. The current redaction list includes passwords, password hashes, tokens, token hashes, secrets, API keys, private keys, SMTP passwords, Stripe secret keys, access tokens, and refresh tokens.

Tenant isolation is enforced by adding the current tenant scope to resources that contain `tenant_id`. Role permissions are scoped through their related tenant-owned role. The service rejects resources without a safe tenant scope rather than guessing how to isolate them.

> An AI connected through MCP can perform only the allow-listed, tenant-scoped operations implemented by Slate. It cannot run SQL, execute PHP, browse arbitrary server files, bypass permissions, or directly submit unreviewed administrator forms.

## One-time connection setup

1. Sign in to Slate as an administrator with permission to manage settings and MCP access.
2. Open **AI / MCP** in the Slate admin navigation.
3. Select **Generate / rotate token** and copy the raw token immediately.
4. Add a custom MCP server to the AI client using the endpoint below and store the token in the client’s secure secret storage.

```text
https://greenlightinduction.rakibhasaan.com/slate/mcp.php
```

5. Run `slate_admin_health`.
6. Run `slate_admin_capabilities`.
7. Start with a read-only `test`, `list`, or `read` operation before enabling data mutations.

The token is long-lived for convenience but remains revocable. Rotate it immediately if it may have been exposed.

## Example task prompts for connected AI clients

The following are valid examples of tasks an authorized AI can fulfill through the exposed tools:

| Prompt | Expected MCP workflow |
|---|---|
| “Check whether the Slate MCP connection is healthy.” | Call `slate_admin_health`. |
| “List all modules and resources I can manage.” | Call `slate_admin_capabilities`. |
| “Count published content posts.” | Preview and execute a content `test` or filtered `list` operation. |
| “Find the latest three draft posts.” | Preview and execute a content `list` operation with an allow-listed status filter and limit. |
| “Create a draft blog post about secure AI administration.” | Preview and execute a content `create` operation with title, slug, excerpt, and layout. |
| “Publish post 232.” | Preview and execute a content `edit` or `write` operation changing its status to `published`. |
| “Find shop orders for a customer.” | Preview and execute an allow-listed shop `list` operation with supported filters. |
| “Check how many SEO settings are configured.” | Preview and execute an SEO `test` operation. |
| “Update a tenant setting.” | Preview and execute a settings `edit` or `write` operation. |
| “Review booking availability records.” | Preview and execute a booking `list` or `test` operation where the table is installed. |
| “Remove an obsolete form submission.” | Preview and execute a forms `delete` operation for a specified record ID. |
| “Audit recent MCP administration actions.” | Use Slate’s audit-log administration interface or a separately exposed audit resource if one is added to the allow-list. |

## What is not currently supported

The MCP server is not a general-purpose remote shell or unrestricted admin proxy. It does not support arbitrary SQL queries, PHP execution, filesystem reads or writes, server package installation, browser automation, direct file uploads, arbitrary plugin installation, unreviewed bulk deletion, password retrieval, secret retrieval, or actions against another tenant.

The generic adapter also does not automatically understand every business rule in every plugin. It enforces table and column allow-lists, tenant scope, and confirmation tokens, but specialized workflows such as payment capture, sending email, changing a provider’s OAuth connection, or triggering a plugin-specific side effect should be implemented as dedicated reviewed tools before being advertised as supported.

Optional resources are available only when their plugin migrations and tables are installed. A successful capability response confirms what the server advertises; it does not guarantee that every optional resource is populated with records.

## Testing and operations

The repository integration suite is located at `tests/integration/McpTest.php` and runs through:

```bash
bash tests/run.sh
```

The suite verifies module and resource discovery, read/list/test behavior, settings CRUD, confirmation-token binding and single-use enforcement, sensitive-field redaction, and tenant scoping. Run it only against a disposable CI or staging database; never point it at the production database.

For a safe operational rollout, test the endpoint with `slate_admin_health`, verify capabilities, perform a read-only resource test, create a draft rather than a published record, review the result, and rotate the bearer token if it was exposed during setup.

## Troubleshooting

| Symptom | Likely cause | Corrective action |
|---|---|---|
| HTTP 401 | Token is invalid, revoked, truncated, or associated with another tenant. | Generate a new token in Slate and update the client’s secure credential. |
| HTTP 400 during initialization | The deployed endpoint is missing JSON-RPC notification handling or is an older deployment. | Confirm the current `mcp.php` is deployed and accepts `notifications/initialized` without an `id`. |
| Tool discovery cannot parse schemas | The endpoint returns invalid JSON Schema types, such as an array for an empty `properties` object. | Deploy the current MCP endpoint and retry discovery. |
| Resource is rejected | Module/resource is not allow-listed or the optional plugin table is absent. | Call `slate_admin_capabilities` and use only advertised resources. |
| Mutation is rejected | Confirmation token is missing, expired, already used, or does not match the exact operation. | Request a new preview and execute it without changing any arguments. |
| A field is ignored or rejected | The field is not a writable, non-sensitive column in the live table schema. | Use the resource’s supported schema or add a reviewed adapter. |
| Content appears as draft | The create request used `status: draft`, which is the safer default. | Review it in Slate, then explicitly preview and execute a publish update. |

## Deployment checklist

Before enabling the endpoint for regular administration, confirm that the production site uses HTTPS, the current `mcp.php`, `includes/Mcp.php`, and `admin/mcp.php` are deployed, the MCP token was generated on the live tenant, the token is stored only in secure client configuration, and the audit log is recording token and operation events. Validate health and capabilities first, then begin with read-only tests.
