# Slate MCP API Reference

## Endpoint and transport

Slate MCP is a JSON-RPC 2.0 service over HTTPS:

```text
POST https://greenlightinduction.rakibhasaan.com/slate/mcp.php
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

The endpoint supports MCP initialization, tool discovery, and tool calls. The bearer token is tenant-scoped and must be stored in the client’s secure credential store.

## JSON-RPC lifecycle

### Initialize

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2024-11-05",
    "capabilities": {},
    "clientInfo": {
      "name": "example-client",
      "version": "1.0.0"
    }
  }
}
```

The server returns its protocol version, tool capability, server name, version, and mutation-safety instructions.

### Initialized notification

After initialization, send the MCP notification without an `id`:

```json
{
  "jsonrpc": "2.0",
  "method": "notifications/initialized"
}
```

The server accepts the notification and returns HTTP 202 without a JSON-RPC response body.

### Tool discovery

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/list",
  "params": {}
}
```

The result contains the four available tools: `slate_admin_health`, `slate_admin_capabilities`, `slate_admin_preview`, and `slate_admin_execute`.

### Tool call envelope

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "slate_admin_health",
    "arguments": {}
  }
}
```

Successful tool calls return MCP content text plus `structuredContent`. The text value contains a JSON representation of the structured result.

## Tool specifications

### `slate_admin_health`

Check authenticated availability and tenant context.

**Arguments:** `{}`

**Result example:**

```json
{
  "ok": true,
  "tenant_id": 1,
  "version": "1.0.0",
  "modules": ["users", "roles", "settings", "plugins", "media", "forms", "shop", "bookings", "memberships", "seo", "content"]
}
```

### `slate_admin_capabilities`

Return the live module/resource allow-list, action vocabulary, mutation policy, redacted columns, and unsupported access categories.

**Arguments:** `{}`

**Result fields:**

| Field | Meaning |
|---|---|
| `modules` | Module names currently exposed by the server. |
| `resources` | Module-to-table/resource allow-list. |
| `actions` | Supported actions: `read`, `list`, `test`, `create`, `edit`, `write`, `delete`. |
| `mutation_policy` | Preview first, then execute with a single-use confirmation token. |
| `redacted_columns` | Sensitive field names masked in returned rows. |
| `unsupported_direct_access` | Direct SQL, filesystem access, and PHP execution are not exposed. |

### `slate_admin_preview`

Validate an operation and issue a five-minute confirmation token. Preview never changes application data.

**Arguments:**

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
    "excerpt": "A concise summary.",
    "layout": []
  }
}
```

**Result:**

```json
{
  "ok": true,
  "requires_confirmation": true,
  "operation": {
    "name": "content.contentbuilder_posts",
    "module": "content",
    "action": "create",
    "resource": "contentbuilder_posts",
    "arguments": {}
  },
  "confirmation_token": "TOKEN_RETURNED_BY_SERVER",
  "expires_in": 300
}
```

The confirmation token is bound to the complete operation arguments. Request a new preview whenever any argument changes.

### `slate_admin_execute`

Execute exactly the operation represented by a valid confirmation token.

**Arguments:**

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
    "excerpt": "A concise summary.",
    "layout": []
  },
  "confirmation_token": "TOKEN_RETURNED_BY_PREVIEW"
}
```

The token is single-use, expires after five minutes, and cannot be reused for a different operation. Successful mutation results include `ok`, `action`, `resource`, and the created or affected `id` where applicable.

## Action semantics

| Action | Purpose | Typical payload |
|---|---|---|
| `read` | Retrieve one record or a limited filtered result. | `{ "id": 123 }` or filters and limit. |
| `list` | Retrieve a bounded collection of records. | `{ "limit": 20, "filters": { "status": "draft" } }` |
| `test` | Check resource availability and return a tenant-scoped count. | `{}` |
| `create` | Insert a new tenant-scoped record. | Writable non-sensitive columns excluding `id`, `tenant_id`, and timestamps. |
| `edit` | Update an existing tenant-scoped record. | `{ "id": 123, "field": "new value" }` |
| `write` | Create when no ID is supplied; edit when an ID is supplied. | Create or edit payload. |
| `delete` | Delete a specified tenant-scoped record. | `{ "id": 123 }` |

Read, list, and test operations also use the preview-and-execute flow so the exact requested operation is auditable and confirmation-bound.

## Common payload rules

The server accepts only fields present in the live resource schema and rejects unsupported module/resource/action combinations. For reads and lists, `limit` is bounded to a safe range and filters are accepted only for allow-listed, non-sensitive columns. For edit and delete, `payload.id` is required. For create and write, `tenant_id` is assigned by the server when the table has a tenant column; clients must not supply it.

Sensitive fields are never accepted as writable values when their names match the server’s redaction policy. The current policy covers `password`, `password_hash`, `token`, `token_hash`, `secret`, `api_key`, `private_key`, `smtp_pass`, `stripe_secret_key`, `access_token`, and `refresh_token` patterns.

## Module/resource map

| Module | Resources |
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

Optional plugin resources are available only when their database tables are installed. Always use the live `slate_admin_capabilities` result as the authoritative map.

## Content API examples

### List recent posts

```json
{
  "module": "content",
  "action": "list",
  "resource": "contentbuilder_posts",
  "payload": {
    "limit": 10,
    "filters": {
      "status": "published"
    }
  }
}
```

### Create a draft post

```json
{
  "module": "content",
  "action": "create",
  "resource": "contentbuilder_posts",
  "payload": {
    "type": "post",
    "title": "Secure AI Administration with MCP",
    "slug": "secure-ai-administration-with-mcp",
    "status": "draft",
    "excerpt": "Learn how governed MCP tools connect AI assistants to secure, auditable administration workflows.",
    "layout": [
      {
        "type": "hero",
        "props": {
          "eyebrow": "AI OPERATIONS",
          "title": "Secure AI Administration with MCP",
          "subtitle": "A practical guide to governed automation"
        }
      },
      {
        "type": "paragraph",
        "props": {
          "text": "Begin with read-only discovery, then introduce confirmation-protected mutations."
        }
      }
    ]
  }
}
```

### Publish an existing post

Use an `edit` or `write` preview with the target post ID and `status: published`. Never publish by silently changing a draft during an unrelated edit.

```json
{
  "module": "content",
  "action": "edit",
  "resource": "contentbuilder_posts",
  "payload": {
    "id": 232,
    "status": "published"
  }
}
```

## Error behavior

| Condition | Behavior |
|---|---|
| Missing or invalid bearer token | HTTP 401 with a bearer-authentication error. |
| Invalid JSON-RPC request | HTTP 400 with JSON-RPC error `-32600`. |
| Unknown method | JSON-RPC error `-32601`. |
| Invalid module, action, resource, or payload | JSON-RPC error `-32602`. |
| Missing, expired, reused, or mismatched confirmation | JSON-RPC error `-32000`; no operation is executed. |
| Database or adapter failure | JSON-RPC error `-32000`; details remain in server logs and audit context. |
| Missing optional resource table | Resource fails closed or is reported unavailable. |

## Security requirements for clients

Use HTTPS only. Store bearer tokens in a secret manager or secure client configuration. Do not place tokens in source code, public documentation, screenshots, query strings, logs, or prompts. Call capabilities before operating on a resource. Use narrow filters and limits. Prefer drafts for content. Require an explicit user request before publishing, deleting, changing settings, or modifying access control. Never attempt to bypass the preview token or tenant scope.

## Minimal curl health test

```bash
curl -sS -X POST 'https://greenlightinduction.rakibhasaan.com/slate/mcp.php' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"slate_admin_health","arguments":{}}}'
```

Replace `YOUR_TOKEN` locally without committing or sharing the resulting command. A successful response has `ok: true` and identifies the authenticated tenant.
