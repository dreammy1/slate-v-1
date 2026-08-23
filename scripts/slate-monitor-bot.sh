#!/usr/bin/env bash
set -Eeuo pipefail

MCP_URL="${SLATE_MCP_URL:-https://greenlightinduction.rakibhasaan.com/slate/mcp.php}"
REPOSITORY="${GITHUB_REPOSITORY:-dreammy1/slate-v-1}"
WORKFLOW_FILE="${SLATE_WORKFLOW_FILE:-main.yml}"
ALERT_WEBHOOK="${SLATE_MONITOR_WEBHOOK_URL:-${DEPLOY_ALERT_WEBHOOK:-}}"
TMP_DIR="${RUNNER_TEMP:-/tmp}/slate-monitor"
mkdir -p "$TMP_DIR"

summary_file="$TMP_DIR/summary.md"
health_file="$TMP_DIR/health.json"
run_file="$TMP_DIR/latest-run.json"
jobs_file="$TMP_DIR/latest-jobs.json"
mkdir -p "$(dirname "$summary_file")"

failures=()
now_utc="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

printf '# Slate production monitor\n\n' > "$summary_file"
printf -- '- Checked at (UTC): `%s`\n' "$now_utc" >> "$summary_file"
printf -- '- MCP endpoint: `%s`\n' "$MCP_URL" >> "$summary_file"
printf -- '- Repository: `%s`\n\n' "$REPOSITORY" >> "$summary_file"

rpc_body='{"jsonrpc":"2.0","id":"slate-monitor-health","method":"tools/call","params":{"name":"slate_admin_health","arguments":{}}}'

if [[ -n "${SLATE_MCP_TOKEN:-}" ]]; then
  if curl --fail-with-body --silent --show-error --max-time 30 \
      -H 'Content-Type: application/json' \
      -H "Authorization: Bearer ${SLATE_MCP_TOKEN}" \
      --data "$rpc_body" "$MCP_URL" > "$health_file"; then
    if jq -e '.result // empty' "$health_file" >/dev/null 2>&1; then
      health_status="healthy"
      tenant_id="$(jq -r '.result.structuredContent.tenant_id // .result.content[0].text? // "unknown"' "$health_file" 2>/dev/null || printf 'unknown')"
      printf '## MCP health\n\n- Status: **healthy**\n- Response received over HTTPS with bearer authentication.\n' >> "$summary_file"
    else
      health_status="failed"
      failures+=("MCP returned no successful JSON-RPC result")
      printf '## MCP health\n\n- Status: **failed**\n- The endpoint responded, but no successful JSON-RPC result was present.\n' >> "$summary_file"
    fi
  else
    health_status="failed"
    failures+=("MCP health request failed")
    printf '## MCP health\n\n- Status: **failed**\n- The HTTPS health request failed.\n' >> "$summary_file"
  fi
else
  health_status="not_configured"
  failures+=("SLATE_MCP_TOKEN is not configured")
  printf '## MCP health\n\n- Status: **not configured**\n- Add the bearer token as the GitHub Actions secret `SLATE_MCP_TOKEN`; the token is never printed.\n' >> "$summary_file"
fi

if [[ -n "${GITHUB_TOKEN:-}" ]]; then
  if curl --fail-with-body --silent --show-error --max-time 30 \
      -H 'Accept: application/vnd.github+json' \
      -H "Authorization: Bearer ${GITHUB_TOKEN}" \
      "https://api.github.com/repos/${REPOSITORY}/actions/workflows/${WORKFLOW_FILE}/runs?per_page=20" > "$run_file"; then
    run_status="$(jq -r '[.workflow_runs[] | select((.head_branch // "") | startswith("v"))][0].status // "not_found"' "$run_file")"
    run_conclusion="$(jq -r '[.workflow_runs[] | select((.head_branch // "") | startswith("v"))][0].conclusion // "not_run"' "$run_file")"
    run_id="$(jq -r '[.workflow_runs[] | select((.head_branch // "") | startswith("v"))][0].id // ""' "$run_file")"
    run_url="$(jq -r '[.workflow_runs[] | select((.head_branch // "") | startswith("v"))][0].html_url // ""' "$run_file")"
    deployment_status="attention"
    deployment_job_status="unknown"
    deployment_job_conclusion="pending"
    if [[ -n "$run_id" ]] && curl --fail-with-body --silent --show-error --max-time 30 \
        -H 'Accept: application/vnd.github+json' \
        -H "Authorization: Bearer ${GITHUB_TOKEN}" \
        "https://api.github.com/repos/${REPOSITORY}/actions/runs/${run_id}/jobs?per_page=100" > "$jobs_file"; then
      deployment_job_status="$(jq -r '[.jobs[] | select(.name == "Deploy version tag to production")][0].status // "not_found"' "$jobs_file")"
      deployment_job_conclusion="$(jq -r '[.jobs[] | select(.name == "Deploy version tag to production")][0].conclusion // "pending"' "$jobs_file")"
    fi
    if [[ "$run_status" == "completed" && "$run_conclusion" == "success" && "$deployment_job_status" == "completed" && "$deployment_job_conclusion" == "success" ]]; then
      deployment_status="healthy"
    else
      failures+=("Latest version-tag workflow is ${run_status}/${run_conclusion}; production FTPS job is ${deployment_job_status}/${deployment_job_conclusion}")
    fi
    printf '\n## CI/CD and FTPS status\n\n- Latest version-tag workflow status: **%s**\n- Latest version-tag workflow conclusion: **%s**\n- Production FTPS job: **%s/%s**\n- Run: %s\n' "$run_status" "$run_conclusion" "$deployment_job_status" "$deployment_job_conclusion" "${run_url:-unavailable}" >> "$summary_file"
  else
    deployment_status="failed"
    failures+=("GitHub Actions status request failed")
    printf '\n## CI/CD and FTPS status\n\n- Status: **failed**\n- GitHub Actions status request failed.\n' >> "$summary_file"
  fi
else
  deployment_status="not_configured"
  failures+=("GITHUB_TOKEN is not configured")
  printf '\n## CI/CD and FTPS status\n\n- Status: **not configured**\n- The workflow must provide the built-in GitHub token.\n' >> "$summary_file"
fi

if ((${#failures[@]} > 0)); then
  printf '\n## Attention\n\n' >> "$summary_file"
  for failure in "${failures[@]}"; do
    printf -- '- %s\n' "$failure" >> "$summary_file"
  done
  overall_status="attention"
else
  printf '\n## Overall status\n\n**healthy**\n' >> "$summary_file"
  overall_status="healthy"
fi

cat "$summary_file"

if [[ -n "$ALERT_WEBHOOK" && "$overall_status" != "healthy" ]]; then
  alert_text="Slate monitor alert (${now_utc} UTC): ${#failures[@]} issue(s) detected. See ${GITHUB_SERVER_URL:-https://github.com}/${REPOSITORY}/actions/runs/${GITHUB_RUN_ID:-unknown}."
  curl --fail-with-body --silent --show-error --max-time 15 \
    -H 'Content-Type: application/json' \
    --data "$(jq -cn --arg text "$alert_text" '{text:$text}')" \
    "$ALERT_WEBHOOK" >/dev/null || echo 'Webhook alert delivery failed; monitor result remains available in the job summary.' >&2
fi

if [[ "${SLATE_MONITOR_FAIL_ON_ALERT:-false}" == "true" && "$overall_status" != "healthy" ]]; then
  exit 1
fi
