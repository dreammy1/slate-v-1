# GitHub-to-cPanel Automatic Deployment

This deployment path uses the selected cPanel-native model. Every push to `main` first passes the repository’s existing quality, PHP test, security, E2E, and release-package jobs. When those jobs succeed, the `Deploy validated main to cPanel` job connects to the existing cPanel-managed repository and requests a cPanel deployment task. The deployment task pulls the GitHub `main` branch with cPanel’s fast-forward safeguards and executes the top-level `.cpanel.yml` manifest. [1] [2]

> **Target directory:** `/home/rakilluy/greenlightinduction.rakibhasaan.com`.
>
> The active site responds at `https://greenlightinduction.rakibhasaan.com/`. Do not change this target unless the domain’s document root is changed in cPanel.

## One-time activation

Create a dedicated **Ed25519** key for this deployment only. Add the public key in cPanel under **SSH Access → Manage SSH Keys**, then authorize it. Store the private key only as the GitHub environment secret named `CPANEL_DEPLOY_SSH_PRIVATE_KEY`. Never commit either key to the repository.

Add the following production environment configuration in GitHub. The defaults shown in the workflow already match the known server layout, so only the two secrets are required for the initial activation.

| GitHub configuration                   | Required value                                               | Purpose                                           |
| -------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------- |
| Secret `CPANEL_DEPLOY_SSH_PRIVATE_KEY` | Dedicated deployment key private half                        | Allows the workflow to reach cPanel over SSH.     |
| Secret `CPANEL_DEPLOY_SSH_KNOWN_HOSTS` | Pinned SSH host entry for `premium106.web-hosting.com:21098` | Prevents host-key substitution during deployment. |
| Variable `CPANEL_HOST`                 | `premium106.web-hosting.com`                                 | Hosting endpoint.                                 |
| Variable `CPANEL_PORT`                 | `21098`                                                      | Hosting SSH port.                                 |
| Variable `CPANEL_USER`                 | `rakilluy`                                                   | cPanel account user.                              |
| Variable `CPANEL_REPOSITORY_ROOT`      | `/home/rakilluy/repositories/slate-v-1`                      | cPanel-managed repository.                        |
| Variable `CPANEL_DEPLOY_HEALTH_URL`    | `https://greenlightinduction.rakibhasaan.com/`               | Public post-deploy verification endpoint.         |

Configure any reviewer or wait-timer rules you want under the GitHub `production` environment. Without required reviewers, a passed push to `main` deploys automatically.

## Deferred command-center callback

The workflow contains a best-effort signed deployment callback after its cPanel deployment and public health check complete successfully. It remains disabled until both of the following `production` environment values are explicitly configured: secret `DASHBOARD_DEPLOY_WEBHOOK_SECRET` and variable `DASHBOARD_DEPLOY_WEBHOOK_URL`.

Do not configure the endpoint variable until the command-center public readiness URL consistently returns HTTP `204` and identifies the current runtime. When it is safe to activate, set `DASHBOARD_DEPLOY_WEBHOOK_URL` to `https://slateops-lkwg6pc5.manus.space/api/events/deployment` and make the secret value exactly match the command-center project secret `GITHUB_DEPLOY_WEBHOOK_SECRET`. Callback delivery is intentionally best-effort: a telemetry failure emits a workflow warning but never rolls back an already-verified cPanel deployment.

## Deployment behavior and preservation rules

The manifest copies only deployable application source into the live target. During the first root promotion, it migrates the existing nested Slate `.env`, runtime data, and uploads only if their root counterparts do not already exist, then changes the preserved `APP_URL` to the root URL. It sets the document-root directory to `755` so LiteSpeed can traverse it and process Slate’s root `.htaccess`; the application’s own hardening rules then disable directory indexes and block sensitive paths, including preserved ZIP archives. It deliberately preserves `.env` files, `uploads/`, `data/`, nested legacy copies, backup directories, ZIP archives, `.claude/`, and logs on subsequent deployments. It excludes tests, internal documents, package artifacts, Git metadata, and underscore-prefixed maintenance scripts.

The active deployment job fails safely if the cPanel repository has uncommitted changes, the cPanel deployment task reports failure, or the public URL does not return a successful response. cPanel also requires a clean repository and a valid checked-in `.cpanel.yml` before it will execute deployment. [1]

## Rollback

To roll back safely, revert the faulty GitHub commit on `main`; the same validated deployment path redeploys the prior code. Do not edit the cPanel working tree directly, because local changes make the repository dirty and block cPanel deployment. If the server itself is unavailable, restore the known good archive only through cPanel’s backup tools, then reconcile the Git repository before re-enabling automatic deployment.

## Public-root hardening

The application now deploys to the confirmed domain document root. Its root `.htaccess` disables directory indexes and blocks direct access to preserved archive files, while the deployment manifest preserves runtime data and backups.

## References

[1] [cPanel, _Guide to Git — Deployment_](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/)

[2] [cPanel, _Guide to Git — Set Up Deployment_](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-set-up-deployment/)
