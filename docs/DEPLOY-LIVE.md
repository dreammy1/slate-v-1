# Deploy Slate to the live server

This guide deploys the repository to:

```text
https://greenlightinduction.rakibhasaan.com/slate
```

The live URL currently exposes a LiteSpeed directory index rather than the Slate application, so confirm the hosting document root and target directory with the hosting provider before copying files.

## 1. Prepare access and maintenance details

Use an SSH-capable deployment account with access to the Slate document root. The account must be able to write the application files, create or alter the Slate database tables, and restart PHP or clear the PHP opcode cache if the hosting panel requires it. Do not place database passwords, `.env`, SSH keys, or runtime uploads in Git.

Record these values locally before starting:

| Value | Example | Source |
|---|---|---|
| SSH host | `greenlightinduction.rakibhasaan.com` | Hosting panel |
| SSH user | `deploy` | Hosting panel |
| Application path | `/home/ACCOUNT/public_html/slate` | Hosting panel/document root |
| Database name | `...` | Current Slate `.env` or hosting panel |
| Database user | `...` | Current Slate `.env` or hosting panel |
| Backup directory | `/home/ACCOUNT/backups/slate-YYYYMMDD-HHMMSS` | Choose outside web root |

Do not guess the filesystem path from the URL. A URL path and a LiteSpeed document-root path are not interchangeable.

## 2. Put the site into maintenance mode

Before deployment, prevent concurrent admin writes using the hosting panel’s maintenance mode or a temporary web-server rule. If maintenance mode is unavailable, deploy during a low-traffic window and take the database backup immediately before file replacement.

## 3. Back up the current application and database

Run the following commands on the server after replacing the placeholders. Use the hosting panel’s database export tool if `mysqldump` is not available.

```bash
STAMP="$(date +%Y%m%d-%H%M%S)"
APP="/home/ACCOUNT/public_html/slate"
BACKUP="/home/ACCOUNT/backups/slate-$STAMP"
mkdir -p "$BACKUP"

# Preserve the current application, environment, uploads, runtime data, and logs.
tar -czf "$BACKUP/application.tar.gz" -C "$(dirname "$APP")" "$(basename "$APP")"

# Export the active database. Do not put the password in shell history.
mysqldump --single-transaction --routines --triggers \
  -u 'DB_USER' -p 'DB_NAME' > "$BACKUP/database.sql"

sha256sum "$BACKUP/application.tar.gz" "$BACKUP/database.sql" > "$BACKUP/SHA256SUMS"
```

Keep the backup until the new installation has passed smoke tests and at least one normal business cycle.

## 4. Download and verify the release

From a trusted workstation, clone the exact commit and create a deployment archive that excludes secrets and runtime data:

```bash
git clone https://github.com/dreammy1/slate-v-1.git slate-release
git -C slate-release checkout 9d4848b   # replace with the approved release commit
tar --exclude='.git' --exclude='.env' --exclude='uploads' \
    --exclude='data' --exclude='*.log' -czf slate-release.tar.gz -C slate-release .
sha256sum slate-release.tar.gz
```

Verify that the archive contains `config.php`, `mcp.php`, `includes/Mcp.php`, `admin/mcp.php`, `db/schema.sql`, and the plugin directories. Never upload `.env` from a developer machine.

## 5. Upload to a staging directory on the server

Upload the archive to a temporary directory outside the public web root, extract it, and verify the contents before replacement:

```bash
scp slate-release.tar.gz deploy@greenlightinduction.rakibhasaan.com:/home/ACCOUNT/tmp/
ssh deploy@greenlightinduction.rakibhasaan.com
mkdir -p /home/ACCOUNT/tmp/slate-release
 tar -xzf /home/ACCOUNT/tmp/slate-release.tar.gz -C /home/ACCOUNT/tmp/slate-release
find /home/ACCOUNT/tmp/slate-release -maxdepth 2 -type f | sort | sed -n '1,80p'
```

If the server uses a hosting panel rather than SSH, use its file manager to upload and extract the archive into an equivalent temporary directory.

## 6. Preserve the live environment configuration

Copy the existing live `.env` into the new release directory, or configure the same values through the hosting panel. At minimum, verify:

```text
APP_URL=https://greenlightinduction.rakibhasaan.com/slate
DB_HOST=...
DB_NAME=...
DB_USER=...
DB_PASS=...
DB_CHARSET=utf8mb4
APP_SECRET=<existing stable secret>
CRON_SECRET=<existing stable secret>
TENANT_ID=1
```

Keep the existing `APP_SECRET`; changing it may invalidate encrypted settings or sessions. Ensure the web server maps the URL prefix `/slate` to the directory containing `index.php`, `config.php`, `admin/`, and `mcp.php`.

## 7. Run the database schema step

If this is a fresh Slate installation, use the application installer and follow its database setup process. If this is an existing installation, do not run `install.php` blindly. First compare the repository’s SQL files with the live schema, take the backup from step 3, and apply only the approved migrations.

The MCP service creates `mcp_tokens` and `mcp_confirmations` lazily on first use. You may also pre-create them through a reviewed migration. Confirm that the live database user can create these tables or apply the schema migration with an administrative database account.

## 8. Replace the application atomically

After verifying the temporary release, keep the existing `.env`, `uploads/`, and runtime data, then switch the document-root contents. A conservative approach is:

```bash
APP="/home/ACCOUNT/public_html/slate"
NEW="/home/ACCOUNT/tmp/slate-release"
OLD="/home/ACCOUNT/backups/slate-pre-release-$(date +%Y%m%d-%H%M%S)"

mv "$APP" "$OLD"
mkdir -p "$APP"
cp -a "$NEW"/. "$APP"/
cp "$OLD/.env" "$APP/.env"
cp -a "$OLD/uploads" "$APP/uploads" 2>/dev/null || true
cp -a "$OLD/data" "$APP/data" 2>/dev/null || true
chmod 755 "$APP"
```

Adjust ownership and permissions to match the existing LiteSpeed/PHP process. Do not make the whole application world-writable. Runtime directories should be writable only where the application requires it.

## 9. Apply web-server protection and clear caches

Confirm that `.htaccess` is active and that directory listing is disabled. The current live directory index indicates this should be checked explicitly. Confirm that direct requests to `.env`, SQL files, logs, internal directories, and `mcp_tokens` data are denied. Clear PHP OPcache through the hosting panel or PHP-FPM restart if stale bytecode is suspected.

## 10. Run smoke tests

From a workstation, verify the public and protected paths without entering credentials in automation:

```bash
curl -I https://greenlightinduction.rakibhasaan.com/slate/
curl -I https://greenlightinduction.rakibhasaan.com/slate/admin/login.php
curl -I https://greenlightinduction.rakibhasaan.com/slate/mcp.php
curl -i https://greenlightinduction.rakibhasaan.com/slate/mcp.php \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"slate_admin_health","arguments":{}}}'
```

Expected results are a Slate response for the admin login page, `401 Unauthorized` for MCP without a bearer token, and no directory listing. A `404` for `mcp.php` means the release is not in the active document root or URL rewriting/path mapping is wrong.

Log in manually as an administrator and check the following: the dashboard loads, the AI / MCP navigation item appears, the existing settings and plugins pages still work, and the audit log is readable. Do not generate an MCP token until the live installation and database have passed these checks.

## 11. Complete one-time MCP setup

Open:

```text
https://greenlightinduction.rakibhasaan.com/slate/admin/mcp.php
```

Choose **Generate / rotate token**, copy the raw token once, and store it in the AI client’s secure secret storage. Configure the server URL as:

```text
https://greenlightinduction.rakibhasaan.com/slate/mcp.php
```

Use the HTTP header:

```text
Authorization: Bearer YOUR_TOKEN
```

Test with the health call in [`docs/MCP.md`](MCP.md), then call `tools/list`. Revoke and rotate the token immediately if it is copied into a chat transcript, shell history, ticket, or log.

## 12. Rollback procedure

If the smoke tests fail, enable maintenance mode, preserve the failed release for diagnosis, and restore the previous application directory and database only if the release included database changes:

```bash
mv /home/ACCOUNT/public_html/slate /home/ACCOUNT/tmp/slate-failed-$(date +%Y%m%d-%H%M%S)
cp -a /home/ACCOUNT/backups/slate-pre-release-TIMESTAMP /home/ACCOUNT/public_html/slate
mysql -u 'DB_USER' -p 'DB_NAME' < /home/ACCOUNT/backups/slate-TIMESTAMP/database.sql
```

Database restoration can overwrite legitimate writes made after deployment, so confirm the incident window and obtain approval before importing the backup. If the only issue is application files and the schema is backward-compatible, restore files first and defer database restoration. After rollback, clear OPcache, repeat the smoke tests, and inspect the web/PHP error logs.

## 13. Post-deployment security checks

Confirm that HTTPS is active, directory listing is disabled, `.env` and logs are not downloadable, the MCP endpoint rejects missing and revoked tokens, confirmation tokens expire and cannot be reused, and MCP operations appear in the tenant audit log. Configure server-side backups and monitor PHP/LiteSpeed error logs during the first deployment window.
