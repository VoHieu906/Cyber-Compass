# infrastructure/aws

Everything needed to run CyberCompass on AWS, as AWS CLI commands and JSON templates (ARCHITECTURE D44). The step-by-step guide that uses these files is [docs/DEPLOYMENT_AWS.md](../../docs/DEPLOYMENT_AWS.md); start there.

| Path | What it is |
|---|---|
| `config.example.sh` | Every value the steps and scripts read. Copy it to `config.sh` (git-ignored) and fill it in as the guide goes. |
| `scripts/render.sh` | Fills a template's `${NAME}` placeholders from `config.sh` into `build/` (git-ignored) and prints the file's path. |
| `scripts/deploy.sh` | One deployment: build, push to ECR, register task definitions, run the release task, roll the services (creating them the first time). Used by hand (step 13) and by GitHub Actions (step 16). |
| `scripts/create-alarms.sh` | The queue metric filters, nine CloudWatch alarms and the dashboard (D48, step 14). |
| `scripts/sleep.sh`, `scripts/wake.sh` | Between demos: the asleep page, services at zero, the database stopped; and back (D49, step 18). |
| `templates/ecs/` | Task definitions: `api`, `web`, `worker` (queue worker and scheduler, D37, D38) and `release` (preflight, migrations, content). |
| `templates/iam/` | The ECS trust policy, the execution role's secret access, the api task role (one bucket, one mail domain), and the GitHub deployment role (D47). |
| `templates/cloudfront/` | The app distribution (pages uncached, `/_next/static` cached, secret origin header) and the uploads distribution with origin access control (D41). |
| `templates/s3/`, `templates/ecr/` | The uploads bucket policy (CloudFront only) and the keep-ten-images lifecycle. |
| `templates/monitoring/` | The budget and its alerts, and the dashboard. |
| `templates/waf/` | The web ACL's rules for the Count-mode trial (D50). |
| `templates/elb/` | The asleep page the load balancer shows (D49). |
| `templates/github/deploy.yml` | The deployment workflow, inactive until it is copied into `.github/workflows/` (step 16). |

Nothing here holds a secret or an account id: those live in `config.sh` and in AWS Secrets Manager. Change AWS resources through these files, not only in the console, so the repository keeps describing what runs.
