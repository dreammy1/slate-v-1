# Slate production deployment over explicit FTPS

This guide documents the encrypted FTP deployment path for the Slate application at `https://greenlightinduction.rakibhasaan.com/slate`.

## Why FTPS is used

The original SSH/rsync deployment could not resolve the hosting endpoint from GitHub-hosted runners. The production workflow now uses explicit FTPS, which provides TLS encryption over the standard FTP control connection. Plain FTP is intentionally not used because it does not protect credentials or file contents in transit.

The workflow uses `SamKirkland/FTP-Deploy-Action@v4.4.0` with `protocol: ftps` and strict certificate verification. The action documents `ftps` as explicit FTPS, `ftps-legacy` as implicit FTPS, and port `21` as the usual default; the hosting provider’s connection instructions are authoritative for the actual host and port.[1]

## Create the dedicated cPanel account

In cPanel, open **Files → FTP Accounts → Add FTP Account**. Create a new account solely for Slate deployment. Select the domain associated with the live application, generate a strong password, and restrict the account’s directory to the Slate document root. The confirmed filesystem path for this installation is:

```text
/home/rakilluy/greenlightinduction.rakibhasaan.com/slate
```

Do not use the primary cPanel FTP account because it can access the whole account. Do not grant the deployment account access to the home directory or unrelated sites. cPanel defines an FTP account’s directory as its top-level access directory and recommends using **Configure FTP Client** to obtain the correct server hostname and connection details.[2]

Use cPanel’s **Configure FTP Client** screen to determine the exact FTPS hostname, username format, port, and server directory. cPanel notes that SSL FTP connections require the server hostname rather than the website domain.[2]

## Add GitHub production secrets

Open the repository’s **Settings → Environments → production → Environment secrets** page and create these secrets:

| Secret | Required value |
|---|---|
| `PRODUCTION_FTPS_HOST` | The FTP hostname shown by cPanel’s Configure FTP Client screen. |
| `PRODUCTION_FTPS_USER` | The complete dedicated FTP username, including `@domain` if cPanel requires it. |
| `PRODUCTION_FTPS_PASSWORD` | The dedicated FTP account password. |
| `PRODUCTION_FTPS_SERVER_DIR` | The remote root visible to the restricted account, commonly `.` or `./`; the workflow normalizes the value with a trailing slash. |

The password must be entered only into GitHub’s encrypted secret field. Never commit it, place it in a workflow file, put it in a normal GitHub variable, or send it in chat. The workflow pins the provider-confirmed explicit FTPS port to numeric port `21`, so no port secret is required. The production preflight checks that the host, username, password, and remote directory are present and fails before making a network connection if any required value is empty.

The older SSH secrets may remain temporarily, but they are no longer used by the FTPS deployment job. Remove them only after the FTPS deployment has succeeded and the rollback plan is confirmed.

## What the workflow does

For a version tag such as `v1.0.3`, GitHub first runs the PHP matrix, linting, dependency checks, security scans, E2E tests, and release packaging. Only if those gates pass does the production job start. It downloads the release ZIP, validates that FTPS values exist, extracts the artifact in the runner, and synchronizes the application over explicit FTPS.

The deployment upload excludes `.env`, `.env.*`, uploads, tests, logs, backups, `.git`, `.github`, `node_modules`, generated distribution folders, and plugin build archives. The live environment configuration and runtime uploads therefore remain on the server. The action’s sync-state file is stored as `.slate-ftp-deploy-sync-state.json` in the configured remote directory so subsequent deployments can synchronize changes efficiently.

The workflow does not run database migrations automatically. Apply reviewed database migrations separately, after taking a backup, and preserve the production `.env` and stable application secrets.

## Safe first deployment

For the first FTPS test, use a manual workflow dispatch in **dry-run** mode to validate the release artifact without connecting to the server. After the FTP account and secrets are confirmed, use an approved version tag to run the production deployment. Review the GitHub Actions summary for the FTPS preflight and deployment result.

After a successful deployment, verify the public home page, admin login, MCP endpoint authentication, protected-file boundaries, and the MCP health check. Also review the cPanel and application logs for connection or permission errors.

## Troubleshooting

| Symptom | Likely cause | Corrective action |
|---|---|---|
| FTPS preflight reports a missing secret | A required production secret is absent or empty | Recreate the named secret in the `production` environment, not as a repository variable. |
| DNS or connection failure | Incorrect FTP hostname or port | Copy the values from cPanel’s Configure FTP Client screen; do not use the website URL. |
| TLS certificate verification failure | Hostname does not match the FTP certificate or the provider requires a different endpoint | Use the provider’s canonical FTP hostname with `security: strict`; do not disable verification as a first response. |
| Login failure | Incorrect full FTP username or password | Use the dedicated FTP account’s complete username and reset its password in cPanel if necessary. |
| Permission denied | FTP account root or directory permissions are wrong | Restrict the account to the Slate directory and confirm it has write permission. |
| Files appear in the wrong directory | Remote server directory is incorrect | Set `PRODUCTION_FTPS_SERVER_DIR` to the directory shown by the FTP client configuration. |
| Existing uploads disappear | An unsafe server directory or broad account root was configured | Stop deployment, restore from backup if necessary, and correct the account root before retrying. |

## Rollback

Keep a server-side backup before the first FTPS deployment. If the release is faulty, use cPanel File Manager or the hosting backup system to restore the previous application files while preserving `.env`, uploads, and runtime data. Restore the database only when the release included an approved schema change and the incident window has been reviewed.

## References

1. [SamKirkland FTP-Deploy-Action documentation](https://github.com/SamKirkland/FTP-Deploy-Action)
2. [cPanel FTP Accounts documentation](https://docs.cpanel.net/cpanel/files/ftp-accounts/)

Saved by Manus AI on 2026-08-23.
