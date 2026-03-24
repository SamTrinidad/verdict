# GitHub Actions — Required Secrets

Configure these secrets under **Settings → Secrets and variables → Actions** in the
GitHub repository before running CI/CD pipelines in production.

---

## Application

| Secret | Description | Example |
|---|---|---|
| `APP_KEY` | Laravel application encryption key (`base64:...`). Generate with `php artisan key:generate --show`. | `base64:abc123...` |

---

## Database

| Secret | Description | Example |
|---|---|---|
| `DB_PASSWORD` | MySQL 8.0 root/application user password for the **production** database. The CI test job uses a hardcoded throwaway password (`verdict_ci`) and does **not** consume this secret. | `s3cr3t!` |

---

## Cache / Queue

| Secret | Description | Example |
|---|---|---|
| `REDIS_PASSWORD` | Redis 7 authentication password for the **production** instance. The CI test job spins up an unauthenticated Redis service and does **not** consume this secret. | `r3d1s!` |

---

## Deployment

> These secrets are consumed by the **deploy** job, which runs only on pushes to
> `main` after the `build` job passes.  The deploy job is currently a stub — add
> real values once the production server is provisioned.

| Secret | Description | Example |
|---|---|---|
| `DEPLOY_SSH_KEY` | Private SSH key (PEM/OpenSSH format) whose public key is authorised on the production server. | `-----BEGIN OPENSSH PRIVATE KEY-----\n...` |
| `DEPLOY_HOST` | Hostname or IP address of the production server. | `verdict.example.com` |
| `DEPLOY_USER` | SSH username used to connect to the production server. | `deploy` |

---

## How to add a secret

```bash
# Using the GitHub CLI
gh secret set SECRET_NAME --body "secret-value"

# Or pipe from a file (e.g. for SSH keys)
gh secret set DEPLOY_SSH_KEY < ~/.ssh/id_ed25519
```

---

## Notes

* **CI test environment**: MySQL and Redis services in the `test` job use
  hard-coded throwaway credentials (`verdict_ci` / no Redis password) so the
  pipeline remains green on fork PRs where secrets are unavailable.
* **Production secrets** (`DB_PASSWORD`, `REDIS_PASSWORD`, `APP_KEY`) should
  be rotated periodically and never committed to source control.
* The `deploy` job targets the `production` GitHub Environment — configure
  required reviewers and deployment branch rules there for additional
  protection.
