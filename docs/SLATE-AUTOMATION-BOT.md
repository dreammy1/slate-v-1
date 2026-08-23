# Slate production automation bot

The repository includes a read-only GitHub Actions bot for monitoring the live Slate MCP endpoint and the production FTPS deployment job. It runs every six hours and can also be started manually from the GitHub Actions interface.

## What the bot checks

The bot calls the live MCP endpoint over HTTPS using the `slate_admin_health` JSON-RPC tool and records the tenant context, version, and module availability without performing any mutation. It then queries the GitHub Actions API for the latest `Slate CI/CD` workflow run and the specific `Deploy version tag to production` job. The bot writes a Markdown report to the job summary and uploads a 14-day report artifact.

When an issue is detected, the bot can send a short alert to an HTTPS webhook. The alert contains only the status, timestamp, and workflow URL; it never includes tokens, passwords, or raw secret values. Webhook delivery is best-effort and cannot deploy or alter Slate data.

## Required GitHub environment secrets

Configure these secrets under **Settings → Environments → production → Environment secrets**:

| Secret | Purpose |
|---|---|
| `SLATE_MCP_TOKEN` | Bearer token for the live MCP endpoint. Store the token only in GitHub’s encrypted secret storage. |
| `SLATE_MONITOR_WEBHOOK_URL` | Optional alert destination for failures. Use an HTTPS webhook URL and never commit it to the repository. |

The built-in `GITHUB_TOKEN` is supplied automatically by GitHub Actions and is used only with read permission to inspect workflow status. The workflow requests `contents: read` and `actions: read` permissions.

## Schedule and manual execution

The workflow file is `.github/workflows/slate-monitor-bot.yml`. Its schedule is:

```cron
0 */6 * * *
```

This runs at six-hour intervals in UTC. The workflow also supports `workflow_dispatch` for an immediate manual check. The existing Manus monitor may remain enabled; it is independent from this repository-native bot and should not be used to perform deployments.

## Daily summaries

Each execution produces a Markdown job summary and an artifact named `slate-monitor-<run-id>`, retained for 14 days. A daily digest can be added later by querying the workflow’s completed runs and aggregating the artifacts or job summaries. The current bot is intentionally read-only and does not make administrative changes, trigger deployments, or modify credentials.

## Safety controls

The bot never calls `slate_admin_preview` or `slate_admin_execute`, never runs SQL or PHP, and never reads server files. It fails visibly in the report when the MCP token is missing, while avoiding secret output. Alerts are sent only when the overall monitor status is not healthy.
