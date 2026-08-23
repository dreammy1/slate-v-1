# Slate MCP server

Slate now includes a remote HTTPS MCP endpoint at `/mcp.php`. For the configured deployment base URL, the endpoint will be:

```text
https://greenlightinduction.rakibhasaan.com/slate/mcp.php
```

The endpoint speaks JSON-RPC over HTTPS and supports MCP initialization, tool discovery, and tool calls. It is designed for Manus, Claude Desktop, Cursor, and other MCP-compatible clients.

## One-time setup

1. Sign in to Slate as an administrator with the `settings.view` permission.
2. Open **AI / MCP** in the admin navigation.
3. Choose **Generate / rotate token**.
4. Copy the token immediately. Slate stores only a password hash and never displays the raw token again.
5. Add the endpoint URL and bearer token to the AI client’s secure MCP configuration.
6. Call `slate_admin_health`, then `slate_admin_capabilities`, to verify the connection.

The token is long-lived for convenience, but it is revocable. Rotating a token revokes the previous active token for the tenant. Revocation is available from the same admin page.

## Security model

Every request requires `Authorization: Bearer <token>`. Tokens are tenant-scoped and are not stored in plaintext. The server does not expose raw SQL, arbitrary PHP execution, unrestricted filesystem access, or direct forwarding of arbitrary admin POST requests.

Read/list/test/create/edit/write/delete operations use a preview-then-execute flow. First call `slate_admin_preview` with `module`, `action`, `resource`, and an optional payload. Slate returns a short-lived, single-use `confirmation_token`. Pass the same operation and token to `slate_admin_execute`. Confirmation tokens expire after five minutes and are bound to the exact operation arguments.

Token issuance, revocation, confirmation creation, and executed MCP operations are written to the tenant audit log. Keep the MCP endpoint behind HTTPS, restrict administrator permissions, and rotate the token if it may have been exposed.

## Available modules

The allow-list covers `users`, `roles`, `settings`, `plugins`, `media`, `forms`, `shop`, `bookings`, `memberships`, `seo`, and `content`. The current foundation includes health/capability discovery and a settings adapter. Feature-specific adapters should be added through reviewed module handlers before enabling mutations for each plugin; unsupported module/resource combinations fail closed rather than falling back to SQL.

## Connection test

```bash
curl -sS -X POST https://greenlightinduction.rakibhasaan.com/slate/mcp.php \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"slate_admin_health","arguments":{}}}'
```

The repository changes must be deployed to the server before this URL becomes available. The live URL currently serves a directory index at `/slate/` and returns 404 for `/slate/admin/login.php`, so the endpoint cannot be tested against production until the updated repository is deployed and the Slate installation is configured.


## MCP test suite

The integration suite is located at `tests/integration/McpTest.php` and runs automatically through `bash tests/run.sh` when PHP and the test database are available. It verifies module/resource discovery, read/list/test actions, settings create/edit/write/delete behavior, confirmation-token binding and single-use enforcement, sensitive-field redaction, and tenant scoping. Optional plugin resources that are not installed in the test database are reported as explicit skips rather than silently treated as passing coverage.

Run the suite only against a disposable CI or staging database:

```bash
bash tests/run.sh
```

The suite creates uniquely named settings probes and cleans them up in `finally` blocks. It must never be pointed at the live production database.
