# Slate CI/CD

The repository includes `.github/workflows/main.yml`, a GitHub Actions pipeline adapted to Slate's actual architecture. Slate is a server-rendered PHP/MySQL application with custom dependency-free tests, not a WordPress plugin with a `wp-env` or Node-based asset build by default.

## Workflow behavior

| Event | Quality checks | PHP matrix tests | E2E job | Package/release | Deployment |
|---|---:|---:|---:|---:|---:|
| Pull request to `main` or `develop` | Yes | PHP 8.1, 8.2, 8.3 | Runs if an E2E suite exists; otherwise reports a deliberate skip | No | No |
| Push to `main` | Yes | Yes | Yes or deliberate skip | Clean ZIP artifact | Staging |
| Tag matching `v*.*.*` | Yes | Yes | Yes or deliberate skip | Clean ZIP plus GitHub Release | Production |

The quality job validates Composer metadata, lints PHP files, runs PHPStan if a PHPStan configuration is added, and runs `npm run lint` and `npm run build` only when a `package.json` exists. The test job starts MySQL 8, initializes `db/schema.sql`, and runs `bash tests/run.sh` across PHP 8.1–8.3.

The package job excludes credentials, runtime uploads, logs, tests, scratch files, development dependencies, Git metadata, and plugin distribution archives. It retains `.env.example` as documentation while excluding `.env` and other environment files. Version tags produce artifacts named `slate-vX.Y.Z.zip` and publish a GitHub Release with generated notes.

## Required GitHub configuration

Create two protected GitHub Environments named `staging` and `production`. Environment protection rules are recommended for production, such as required reviewers and deployment branch restrictions.

Set the following **environment variables**:

| Environment | Variable | Meaning |
|---|---|---|
| `staging` | `STAGING_HOST` | SSH hostname or IP address |
| `staging` | `STAGING_USER` | SSH deployment user |
| `staging` | `STAGING_PATH` | Absolute application directory on the staging server |
| `production` | `PRODUCTION_HOST` | SSH hostname or IP address |
| `production` | `PRODUCTION_USER` | SSH deployment user |
| `production` | `PRODUCTION_PATH` | Absolute application directory on the production server |

Set the following **environment secrets**:

| Environment | Secret | Meaning |
|---|---|---|
| `staging` | `STAGING_SSH_KEY` | Private Ed25519 key authorized on the staging server |
| `production` | `PRODUCTION_SSH_KEY` | Private Ed25519 key authorized on the production server |

The workflow uses the automatically provided `GITHUB_TOKEN` for GitHub Release creation. It does not require a manually created `GH_TOKEN`. The CI database uses disposable credentials defined inside the workflow; production database credentials must remain on the target server and must not be placed in GitHub Actions source.

## Deployment assumptions

The target server must provide SSH access, `rsync`, and `unzip`. The deployment uses `rsync --delete`, so each deployment directory must be dedicated to the Slate application. Do not point `STAGING_PATH` or `PRODUCTION_PATH` at a shared directory containing unrelated files.

The workflow deliberately fails rather than silently skipping a deployment when the relevant environment variables or SSH key are missing. The release artifact is a source deployment bundle; the target server still needs its own `.env`, database, PHP extensions, web-server configuration, and writable runtime directories.

## E2E tests

The repository now includes a Playwright suite under `tests/e2e/` and a `playwright.config.js` configuration. The initial smoke coverage verifies the admin login surface, customer login surface, password visibility behavior, recovery-link wiring, internal database-file protection, and branded handling of an unknown direct page.

CI provisions MySQL, initializes `db/schema.sql`, starts Slate through PHP's built-in server, installs Chromium, and runs `npm run test:e2e`. Local execution requires PHP with the Slate extensions, a reachable MySQL database configured through environment variables, Node.js, and Chromium. Run the browser suite with `npm ci`, `npx playwright install --with-deps chromium`, and `npm run test:e2e` after starting a local Slate server.

## Security notes

Never add `.env`, private SSH keys, database passwords, or runtime uploads to the repository. Use GitHub Environment secrets for deployment credentials and keep application secrets on the destination server. For production, enable environment approvals and restrict the production environment to signed or reviewed version tags where practical.
