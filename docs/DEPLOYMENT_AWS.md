# Deploying CyberCompass to AWS

A step-by-step guide to running CyberCompass on AWS with the AWS CLI and the JSON templates in [`infrastructure/aws/`](../infrastructure/aws/) (ARCHITECTURE D44: no infrastructure-as-code tool, so every resource is created by a command you can read and explain). The layout and every decision behind it are in [ARCHITECTURE §8](ARCHITECTURE.md#8-deployment-architecture-aws) and decisions D37 to D50.

> **Status:** written and checked locally in Phase 7 Part G. Nothing in this guide has been run against AWS yet; the real deployment is Part H, run by the project owner one step at a time. Every command that creates something says what it costs.

## Contents

| Brief §10 item | Where |
|---|---|
| 1. Local development setup | [README: Getting started](../README.md#getting-started) |
| 2. Docker-based development | [README: Everyday commands](../README.md#everyday-commands) |
| 3. Production build | [README: Production images](../README.md#production-images), step 13 |
| 4. Docker image creation | step 13 (`deploy.sh`) |
| 5. ECR image publishing | steps 5 and 13 |
| 6. ECS Fargate deployment | steps 12 and 13 |
| 7. RDS MySQL configuration | step 6 |
| 8. S3 integration | steps 8 and 11 |
| 9. Environment variable configuration | [Environment variables](#environment-variables), step 7 |
| 10. Logs and monitoring | step 14, [Logs](#logs) |

Steps 1 to 15 are Part H1 (the deployment by hand). Steps 16 to 18 are Part H2 (deployment from GitHub, the WAF trial, sleep and wake).

## What you will build

```text
Browser ──app.<domain>──▶ CloudFront ──origin-app.<domain> + secret header──▶ ┐
        ──api.<domain>──────────────────────────────────────────────────────▶ ALB (TLS) ─▶ ECS web (Next.js)
        ──media.<domain>─▶ CloudFront ──OAC──▶ S3 uploads (private)            └─▶ ECS api (Laravel)
                                                                                    ECS worker (queue + scheduler)
                                                         RDS MySQL (private) ◀──────┘  SES (mail), Secrets Manager, CloudWatch
```

### Monthly cost

Approximate on-demand prices in `ap-southeast-1` (Singapore) as of November 2026; confirm them in the [AWS Pricing Calculator](https://calculator.aws/) before step 1. A new account may have Free Tier credits that cover part of this.

| Resource | Size | Awake | Asleep (step 18) |
|---|---|---|---|
| ECS Fargate: api, web | 0.5 vCPU, 1 GB each | ~$45 | $0 |
| ECS Fargate: worker (two containers) | 0.25 vCPU, 1 GB | ~$13 | $0 |
| Public IPv4 addresses on the tasks (D40) | 3 | ~$11 | $0 |
| Application Load Balancer, with its addresses | 1 | ~$30 | ~$30 |
| RDS MySQL `db.t4g.micro`, 20 GB gp3, backups (D43) | 1 | ~$20 | ~$3 (storage) |
| CloudFront, Route 53 zone, Secrets Manager (1 secret), CloudWatch Logs, ECR | | ~$6 | ~$5 |
| SES | ~$0.10 per 1 000 emails | ~$0 | $0 |
| CloudWatch alarms and dashboard, SNS email (D48) | 9 alarms, 1 dashboard | ~$1 | ~$1 |
| **Total** | | **~$125** | **~$38** |
| One-off: the domain (Route 53, `.com`) | per year | ~$15 | |
| Optional: AWS WAF on the ALB (D50) | if kept after the trial | ~$10 | ~$10 |

## Before you start

1. **Tools on your machine:** Git Bash (every command here is Bash), Docker Desktop, the [AWS CLI v2](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html) (`aws --version`).
2. **An AWS account** with MFA on the root user. Do not use the root user day to day: create an IAM Identity Center user (or an IAM user) with administrator access for this setup, and sign in with `aws configure sso` (or `aws configure`). Check with `aws sts get-caller-identity`.
3. **Your settings file**, which every step and script reads and which stays out of git:

   ```bash
   cp infrastructure/aws/config.example.sh infrastructure/aws/config.sh
   # edit config.sh: AWS_ACCOUNT_ID, DOMAIN, ALERT_EMAIL
   source infrastructure/aws/config.sh
   ```

   Run `source infrastructure/aws/config.sh` again in every new terminal, and after each step that tells you to save a value in it.
4. **Templates** are filled in from `config.sh` by `infrastructure/aws/scripts/render.sh <template>`, which writes to `infrastructure/aws/build/` (also out of git) and prints the file's path.

## Step 1. A budget before anything else (D48)

A budget emails you when spending passes 50%, 80% and 100% of a monthly amount, and when the forecast passes 100%. It costs nothing and catches surprises, such as a stopped database restarting itself.

```bash
aws budgets create-budget --account-id "$AWS_ACCOUNT_ID" \
  --budget "file://$(infrastructure/aws/scripts/render.sh monitoring/budget.json)" \
  --notifications-with-subscribers "file://$(infrastructure/aws/scripts/render.sh monitoring/budget-notifications.json)"
```

The template sets $60 a month (awake about half the time); change `Amount` in `templates/monitoring/budget.json` before running it if you want another figure.

## Step 2. The domain and its DNS zone (D41)

Registering in Route 53 creates the hosted zone that ACM, SES and the aliases below use, so every DNS record is one command. **This step costs money** (about $15 a year for a `.com`, plus $0.50 a month for the zone).

1. Check that the name is free and its price: `aws route53domains check-domain-availability --region us-east-1 --domain-name "$DOMAIN"` and `aws route53domains list-prices --region us-east-1 --tld com`.
2. Register it in the console (Route 53, Registered domains, Register domain): the console fills in the contact details and privacy protection more clearly than the CLI. Registration takes up to a few hours; you get an email to confirm the contact address.
3. Save the hosted zone's id in `config.sh` as `HOSTED_ZONE_ID`:

   ```bash
   aws route53 list-hosted-zones-by-name --dns-name "$DOMAIN" --query 'HostedZones[0].Id' --output text
   ```

## Step 3. TLS certificates (ACM, free)

Two certificates, because CloudFront only uses certificates from `us-east-1`:

```bash
# For the ALB, in your region: api.<domain> and origin-app.<domain>
aws acm request-certificate --region "$AWS_REGION" --domain-name "api.$DOMAIN" \
  --subject-alternative-names "origin-app.$DOMAIN" --validation-method DNS \
  --query CertificateArn --output text          # save as CERT_ALB_ARN

# For CloudFront, in us-east-1: app.<domain> and media.<domain>
aws acm request-certificate --region us-east-1 --domain-name "app.$DOMAIN" \
  --subject-alternative-names "media.$DOMAIN" --validation-method DNS \
  --query CertificateArn --output text          # save as CERT_CDN_ARN
```

ACM proves you own the domain by asking for one CNAME record per name. Show them, then create them in the hosted zone:

```bash
aws acm describe-certificate --region "$AWS_REGION" --certificate-arn "$CERT_ALB_ARN" \
  --query 'Certificate.DomainValidationOptions[].ResourceRecord' --output table
aws acm describe-certificate --region us-east-1 --certificate-arn "$CERT_CDN_ARN" \
  --query 'Certificate.DomainValidationOptions[].ResourceRecord' --output table
```

For each `Name` and `Value` shown (CNAME):

```bash
aws route53 change-resource-record-sets --hosted-zone-id "$HOSTED_ZONE_ID" --change-batch '{
  "Changes": [{"Action": "UPSERT", "ResourceRecordSet": {"Name": "<Name>", "Type": "CNAME", "TTL": 300,
    "ResourceRecords": [{"Value": "<Value>"}]}}]}'
```

Wait until both are issued (a few minutes): `aws acm wait certificate-validated --region "$AWS_REGION" --certificate-arn "$CERT_ALB_ARN"`, and the same with `--region us-east-1` and `CERT_CDN_ARN`.

## Step 4. Network and security groups (D40, free)

The tasks run in the default VPC's public subnets with public IPs, so they reach ECR, Secrets Manager, CloudWatch, S3 and SES without a NAT gateway (about $43 a month saved). Security groups decide who may connect: nothing reaches a task except through the load balancer, and nothing reaches the database except the tasks.

```bash
VPC_ID=$(aws ec2 describe-vpcs --filters Name=is-default,Values=true --query 'Vpcs[0].VpcId' --output text)
aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" \
  --query 'Subnets[].[SubnetId,AvailabilityZone,CidrBlock]' --output table
```

Save `VPC_ID`, and in `PUBLIC_SUBNETS` two or more of those subnet ids in different availability zones, comma separated.

**Private subnets for the database.** Two new subnets in two zones, with a route table that has no route to the internet:

```bash
RT=$(aws ec2 create-route-table --vpc-id "$VPC_ID" --query RouteTable.RouteTableId --output text)
aws ec2 create-tags --resources "$RT" --tags Key=Name,Value=${APP}-private
for cidr_az in "172.31.96.0/20 ${AWS_REGION}a" "172.31.112.0/20 ${AWS_REGION}b"; do
  set -- $cidr_az
  SUBNET=$(aws ec2 create-subnet --vpc-id "$VPC_ID" --cidr-block "$1" --availability-zone "$2" \
    --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${APP}-private-$2}]" \
    --query Subnet.SubnetId --output text)
  aws ec2 associate-route-table --route-table-id "$RT" --subnet-id "$SUBNET" >/dev/null
  echo "$SUBNET"
done
```

Save the two ids as `PRIVATE_SUBNETS` (comma separated). If either CIDR overlaps an existing subnet, pick another free `/20` inside `172.31.0.0/16`.

**Security groups:**

```bash
SG_ALB=$(aws ec2 create-security-group --vpc-id "$VPC_ID" --group-name ${APP}-alb \
  --description "Load balancer: HTTP and HTTPS from anywhere" --query GroupId --output text)
aws ec2 authorize-security-group-ingress --group-id "$SG_ALB" --ip-permissions \
  'IpProtocol=tcp,FromPort=80,ToPort=80,IpRanges=[{CidrIp=0.0.0.0/0}],Ipv6Ranges=[{CidrIpv6=::/0}]' \
  'IpProtocol=tcp,FromPort=443,ToPort=443,IpRanges=[{CidrIp=0.0.0.0/0}],Ipv6Ranges=[{CidrIpv6=::/0}]'

SG_TASKS=$(aws ec2 create-security-group --vpc-id "$VPC_ID" --group-name ${APP}-tasks \
  --description "ECS tasks: only from the load balancer" --query GroupId --output text)
aws ec2 authorize-security-group-ingress --group-id "$SG_TASKS" --ip-permissions \
  "IpProtocol=tcp,FromPort=8080,ToPort=8080,UserIdGroupPairs=[{GroupId=$SG_ALB}]" \
  "IpProtocol=tcp,FromPort=3000,ToPort=3000,UserIdGroupPairs=[{GroupId=$SG_ALB}]"

SG_RDS=$(aws ec2 create-security-group --vpc-id "$VPC_ID" --group-name ${APP}-rds \
  --description "Database: only from the ECS tasks" --query GroupId --output text)
aws ec2 authorize-security-group-ingress --group-id "$SG_RDS" --ip-permissions \
  "IpProtocol=tcp,FromPort=3306,ToPort=3306,UserIdGroupPairs=[{GroupId=$SG_TASKS}]"
echo "$SG_ALB $SG_TASKS $SG_RDS"
```

Save `SG_ALB`, `SG_TASKS` and `SG_RDS`. The tasks' outbound traffic stays open (the security group default), which they need for the AWS services above.

## Step 5. Image repositories (ECR)

```bash
for repo in "$ECR_API" "$ECR_WEB"; do
  aws ecr create-repository --repository-name "$repo" --image-scanning-configuration scanOnPush=true \
    --image-tag-mutability IMMUTABLE --query repository.repositoryUri --output text
  aws ecr put-lifecycle-policy --repository-name "$repo" \
    --lifecycle-policy-text "file://$(infrastructure/aws/scripts/render.sh ecr/lifecycle.json)" >/dev/null
done
```

Images are tagged with the git commit and can never be overwritten (immutable tags), scanned for known vulnerabilities on push, and only the last ten are kept (about $0.10 per GB a month).

## Step 6. The database (RDS MySQL 8.4, D43)

Generate the passwords and keys first, keep them only in your terminal until step 7, and never paste them anywhere else:

```bash
DB_PASSWORD=$(openssl rand -hex 24)
APP_KEY="base64:$(openssl rand -base64 32)"
INTERNAL_API_TOKEN=$(openssl rand -hex 24)
```

```bash
aws rds create-db-subnet-group --db-subnet-group-name ${APP}-private \
  --db-subnet-group-description "Private subnets for the database" \
  --subnet-ids $(echo "$PRIVATE_SUBNETS" | tr ',' ' ')

# The newest 8.4 version on offer:
aws rds describe-db-engine-versions --engine mysql \
  --query "DBEngineVersions[?starts_with(EngineVersion,'8.4')].EngineVersion" --output text

aws rds create-db-instance --db-instance-identifier "$APP" \
  --engine mysql --engine-version <8.4.x from above> --db-instance-class db.t4g.micro \
  --allocated-storage 20 --storage-type gp3 --storage-encrypted \
  --db-name cybercompass --master-username cybercompass --master-user-password "$DB_PASSWORD" \
  --db-subnet-group-name ${APP}-private --vpc-security-group-ids "$SG_RDS" --no-publicly-accessible \
  --backup-retention-period 7 --no-multi-az --deletion-protection --copy-tags-to-snapshot
aws rds wait db-instance-available --db-instance-identifier "$APP"     # 5 to 15 minutes
aws rds describe-db-instances --db-instance-identifier "$APP" --query 'DBInstances[0].Endpoint.Address' --output text
```

Save the address as `RDS_ENDPOINT`. **This starts billing** (about $20 a month awake). The database is encrypted, backed up daily for 7 days, unreachable from the internet, and protected from accidental deletion (turn that off before tearing down). The application connects as the database's main user; a separate, narrower database user is a hardening step for later.

## Step 7. Secrets (Secrets Manager, one secret, ~$0.40 a month)

One secret holds the three values the containers must never see in plain configuration; the task definitions read each key from it (`valueFrom: <secret ARN>:<KEY>::`).

```bash
aws secretsmanager create-secret --name "$SECRET_NAME" --description "CyberCompass production secrets" \
  --secret-string "{\"APP_KEY\":\"$APP_KEY\",\"DB_PASSWORD\":\"$DB_PASSWORD\",\"INTERNAL_API_TOKEN\":\"$INTERNAL_API_TOKEN\"}" \
  --query ARN --output text
unset DB_PASSWORD APP_KEY INTERNAL_API_TOKEN
```

Save the ARN as `SECRET_ARN`. To read a value later: `aws secretsmanager get-secret-value --secret-id "$SECRET_NAME" --query SecretString --output text`. Changing `APP_KEY` signs everyone out; changing `DB_PASSWORD` also needs `aws rds modify-db-instance --master-user-password`.

## Step 8. The uploads bucket (S3)

```bash
aws s3api create-bucket --bucket "$UPLOADS_BUCKET" --region "$AWS_REGION" \
  --create-bucket-configuration LocationConstraint="$AWS_REGION"
aws s3api put-public-access-block --bucket "$UPLOADS_BUCKET" --public-access-block-configuration \
  BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
```

The bucket is private: new buckets have ACLs disabled and are encrypted by default, and nothing can make it public. Only the api task role writes to it (step 12) and only the media CloudFront distribution reads from it (step 11). This is why the app never sends a "public" visibility with an upload (Part F).

## Step 9. Mail (SES, D39)

Verify the domain with DKIM, so mail from `no-reply@<domain>` is signed and trusted:

```bash
aws sesv2 create-email-identity --email-identity "$DOMAIN" --query 'DkimAttributes.Tokens' --output text
```

For each of the three tokens, add a CNAME `<token>._domainkey.<domain>` pointing to `<token>.dkim.amazonses.com` (the same `change-resource-record-sets` command as in step 3). Check with `aws sesv2 get-email-identity --email-identity "$DOMAIN" --query VerifiedForSendingStatus`.

**The SES sandbox.** A new account can send only to addresses it has verified. For testing, verify your own: `aws sesv2 create-email-identity --email-identity "$ALERT_EMAIL"` and click the link you receive. Then ask for production access (reviewed by AWS, usually within a day):

```bash
aws sesv2 put-account-details --production-access-enabled --mail-type TRANSACTIONAL \
  --website-url "https://app.$DOMAIN" --contact-language EN \
  --use-case-description "Password reset emails and opt-in learning notifications for a graduation project's learning platform. Low volume, no marketing; every notification email can be turned off in the app."
```

## Step 10. The load balancer (ALB, ~$30 a month) and its DNS

```bash
ALB_ARN=$(aws elbv2 create-load-balancer --name "$APP" --type application --scheme internet-facing \
  --subnets $(echo "$PUBLIC_SUBNETS" | tr ',' ' ') --security-groups "$SG_ALB" \
  --query 'LoadBalancers[0].LoadBalancerArn' --output text)
aws elbv2 describe-load-balancers --load-balancer-arns "$ALB_ARN" \
  --query 'LoadBalancers[0].[DNSName,CanonicalHostedZoneId]' --output text
```

Save `ALB_ARN` and `ALB_DNS` (`ALB_DIMENSION` is derived from `ALB_ARN`). Then the two target groups (where the tasks register) and the listeners:

```bash
TG_API_ARN=$(aws elbv2 create-target-group --name ${APP}-api --protocol HTTP --port 8080 --vpc-id "$VPC_ID" \
  --target-type ip --health-check-path /up --matcher HttpCode=200 \
  --query 'TargetGroups[0].TargetGroupArn' --output text)
TG_WEB_ARN=$(aws elbv2 create-target-group --name ${APP}-web --protocol HTTP --port 3000 --vpc-id "$VPC_ID" \
  --target-type ip --health-check-path /favicon.ico --matcher HttpCode=200 \
  --query 'TargetGroups[0].TargetGroupArn' --output text)

# HTTP only redirects to HTTPS.
aws elbv2 create-listener --load-balancer-arn "$ALB_ARN" --protocol HTTP --port 80 \
  --default-actions 'Type=redirect,RedirectConfig={Protocol=HTTPS,Port=443,StatusCode=HTTP_301}' >/dev/null
# HTTPS answers 404 unless a rule below matches.
LISTENER_HTTPS_ARN=$(aws elbv2 create-listener --load-balancer-arn "$ALB_ARN" --protocol HTTPS --port 443 \
  --certificates CertificateArn="$CERT_ALB_ARN" --ssl-policy ELBSecurityPolicy-TLS13-1-2-2021-06 \
  --default-actions 'Type=fixed-response,FixedResponseConfig={StatusCode=404,ContentType=text/plain,MessageBody=Not found}' \
  --query 'Listeners[0].ListenerArn' --output text)
echo "$TG_API_ARN $TG_WEB_ARN $LISTENER_HTTPS_ARN"
```

Save the three ARNs. Then the rules. The web app is reached only through CloudFront (D41): CloudFront adds a secret header, and the ALB forwards `origin-app.<domain>` only when it is present, so the ALB's own address cannot be used to skip CloudFront. Priority 1 stays free for the asleep page (step 18).

```bash
ORIGIN_VERIFY_SECRET=$(openssl rand -hex 32)     # save it in config.sh
aws elbv2 create-rule --listener-arn "$LISTENER_HTTPS_ARN" --priority 10 \
  --conditions "Field=host-header,Values=api.$DOMAIN" \
  --actions "Type=forward,TargetGroupArn=$TG_API_ARN" >/dev/null
aws elbv2 create-rule --listener-arn "$LISTENER_HTTPS_ARN" --priority 20 \
  --conditions "Field=host-header,Values=origin-app.$DOMAIN" \
               "Field=http-header,HttpHeaderConfig={HttpHeaderName=X-Origin-Verify,Values=[$ORIGIN_VERIFY_SECRET]}" \
  --actions "Type=forward,TargetGroupArn=$TG_WEB_ARN" >/dev/null
```

DNS: `api.<domain>` and `origin-app.<domain>` point at the ALB (alias records cost nothing and follow the ALB's addresses). Use the `CanonicalHostedZoneId` printed above:

```bash
for name in api origin-app; do
  aws route53 change-resource-record-sets --hosted-zone-id "$HOSTED_ZONE_ID" --change-batch "{
    \"Changes\": [{\"Action\": \"UPSERT\", \"ResourceRecordSet\": {\"Name\": \"$name.$DOMAIN\", \"Type\": \"A\",
      \"AliasTarget\": {\"HostedZoneId\": \"<CanonicalHostedZoneId>\", \"DNSName\": \"$ALB_DNS\", \"EvaluateTargetHealth\": false}}}]}"
done
```

## Step 11. CloudFront for the app and the uploads (D41)

First confirm the managed policy ids the templates use (`CachingDisabled`, `CachingOptimized`, `AllViewerExceptHostHeader`):

```bash
aws cloudfront list-cache-policies --type managed --query 'CachePolicyList.Items[].CachePolicy.[Id,CachePolicyConfig.Name]' --output text
aws cloudfront list-origin-request-policies --type managed --query 'OriginRequestPolicyList.Items[].OriginRequestPolicy.[Id,OriginRequestPolicyConfig.Name]' --output text
```

**Uploads (`media.<domain>`).** Origin access control lets this one distribution read the private bucket; nothing else can:

```bash
OAC_ID=$(aws cloudfront create-origin-access-control \
  --origin-access-control-config "file://$(infrastructure/aws/scripts/render.sh cloudfront/media-oac.json)" \
  --query OriginAccessControl.Id --output text)            # save as OAC_ID, then source config.sh
aws cloudfront create-distribution \
  --distribution-config "file://$(infrastructure/aws/scripts/render.sh cloudfront/media-distribution.json)" \
  --query 'Distribution.[Id,DomainName]' --output text     # save as CDN_MEDIA_ID and CDN_MEDIA_DOMAIN
source infrastructure/aws/config.sh
aws s3api put-bucket-policy --bucket "$UPLOADS_BUCKET" \
  --policy "file://$(infrastructure/aws/scripts/render.sh s3/uploads-bucket-policy.json)"
```

**The app (`app.<domain>`).** Pages are never cached (they are personal and set cookies); `/_next/static/*` (hashed, immutable files) is cached at the edge; everything is compressed. CloudFront adds the `X-Origin-Verify` header from step 10:

```bash
aws cloudfront create-distribution \
  --distribution-config "file://$(infrastructure/aws/scripts/render.sh cloudfront/app-distribution.json)" \
  --query 'Distribution.[Id,DomainName]' --output text     # save as CDN_APP_ID and CDN_APP_DOMAIN
```

DNS for both (CloudFront's alias zone id is always `Z2FDTNDATAQYW2`):

```bash
for pair in "app $CDN_APP_DOMAIN" "media $CDN_MEDIA_DOMAIN"; do
  set -- $pair
  aws route53 change-resource-record-sets --hosted-zone-id "$HOSTED_ZONE_ID" --change-batch "{
    \"Changes\": [{\"Action\": \"UPSERT\", \"ResourceRecordSet\": {\"Name\": \"$1.$DOMAIN\", \"Type\": \"A\",
      \"AliasTarget\": {\"HostedZoneId\": \"Z2FDTNDATAQYW2\", \"DNSName\": \"$2\", \"EvaluateTargetHealth\": false}}}]}"
done
```

Distributions take about 5 to 15 minutes to deploy: `aws cloudfront wait distribution-deployed --id "$CDN_APP_ID"`.

## Step 12. Roles, the cluster and the log group

Two IAM roles, each with only what it needs:

- **Execution role** (used by ECS itself to start a task): pull images, write logs, read the one secret.
- **Task role** (used by the Laravel code): write and read objects in the uploads bucket, send mail from the domain. This is why the app holds no AWS keys (D39).

```bash
aws iam create-role --role-name ${APP}-ecs-execution \
  --assume-role-policy-document "file://$(infrastructure/aws/scripts/render.sh iam/ecs-tasks-trust.json)" >/dev/null
aws iam attach-role-policy --role-name ${APP}-ecs-execution \
  --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy
aws iam put-role-policy --role-name ${APP}-ecs-execution --policy-name read-app-secret \
  --policy-document "file://$(infrastructure/aws/scripts/render.sh iam/execution-secrets-policy.json)"

aws iam create-role --role-name ${APP}-api-task \
  --assume-role-policy-document "file://$(infrastructure/aws/scripts/render.sh iam/ecs-tasks-trust.json)" >/dev/null
aws iam put-role-policy --role-name ${APP}-api-task --policy-name uploads-and-mail \
  --policy-document "file://$(infrastructure/aws/scripts/render.sh iam/api-task-policy.json)"

aws ecs create-cluster --cluster-name "$CLUSTER" >/dev/null
aws logs create-log-group --log-group-name "/ecs/$APP"
aws logs put-retention-policy --log-group-name "/ecs/$APP" --retention-in-days "$LOG_RETENTION_DAYS"
```

## Step 13. The first deployment (and every one after it)

From the repository root, on a commit that passed CI:

```bash
source infrastructure/aws/config.sh
infrastructure/aws/scripts/deploy.sh
```

[`deploy.sh`](../infrastructure/aws/scripts/deploy.sh) prints each step as it goes:

1. Logs Docker in to ECR and builds both production images for `linux/amd64`, tagged with the commit (`IMAGE_TAG`). `NEXT_PUBLIC_API_URL=https://api.<domain>` is built into the web image.
2. Pushes them.
3. Registers new revisions of the four task definitions ([`templates/ecs/`](../infrastructure/aws/templates/ecs/)): api, web, worker (with the scheduler as its second container, D38) and release.
4. Runs the **release task**: `php artisan cybercompass:release`, which runs the production preflight, the migrations and the content import. If the preflight finds an unsafe setting, it stops before anything changes, and so does the script.
5. Points the three services at the new revisions (creating them on the first run, one task each) and waits until they are stable. A deployment whose tasks never become healthy is rolled back by ECS (the circuit breaker).

**The first admin account.** Register through the app, then promote yourself with a one-off task:

```bash
aws ecs run-task --cluster "$CLUSTER" --task-definition ${APP}-release --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$PUBLIC_SUBNETS],securityGroups=[$SG_TASKS],assignPublicIp=ENABLED}" \
  --overrides '{"containerOverrides":[{"name":"release","command":["php","artisan","cybercompass:make-admin","you@example.com"]}]}'
```

**Rolling back** to the previous version: `aws ecs update-service --cluster "$CLUSTER" --service ${APP}-api --task-definition ${APP}-api:<previous revision>` (and the same for web and worker). Migrations are additive only (HANDOFF conventions), so older code runs on the newer schema.

## Step 14. Monitoring and alerts (D48)

An SNS topic emails you when an alarm changes state:

```bash
SNS_ALERTS_ARN=$(aws sns create-topic --name ${APP}-alerts --query TopicArn --output text)
aws sns subscribe --topic-arn "$SNS_ALERTS_ARN" --protocol email --notification-endpoint "$ALERT_EMAIL"
# confirm the subscription from the email, save SNS_ALERTS_ARN in config.sh, then:
infrastructure/aws/scripts/create-alarms.sh
```

[`create-alarms.sh`](../infrastructure/aws/scripts/create-alarms.sh) creates:

- two **metric filters** that turn the scheduler's queue line (`{"message":"queue","context":{"pending":…,"failed":…}}`, logged every minute) into the `CyberCompass/QueuePending` and `QueueFailed` metrics: the database queue has no metric of its own, and a filter needs no code or permission;
- nine **alarms**: API 5xx responses, p95 response time over 1.5 s, api CPU and memory, worker memory, database connections and free storage under 2 GB, a queue backlog over 50, and any failed job. Missing data counts as fine, so nothing fires while the demo is asleep;
- one **dashboard** named `cybercompass`.

Nine alarms fit CloudWatch's free ten, three dashboards are free, and SNS email is free at this volume.

## Step 15. Check it works

| Check | How |
|---|---|
| Health | `curl -s https://api.$DOMAIN/api/v1/health` shows the API and database as `ok` |
| TLS and redirects | `http://app.<domain>` redirects to HTTPS; the browser shows a valid certificate on app, api and media |
| CloudFront cannot be skipped | `curl -sk https://$ALB_DNS/ -H "Host: origin-app.$DOMAIN"` answers 404 (no secret header) |
| Sign-up, login, a lesson, an exercise, the dashboard | In the browser, as a new learner |
| Mail | "Forgot password" for an address SES may send to (step 9); the email arrives |
| Uploads | Upload an avatar; it loads from `https://media.<domain>/avatars/...` |
| The queue and the scheduler | After a lesson completion the bell shows it; the dashboard's queue widget shows a line every minute |
| CSP | The first deployment sends the policy as report-only (`CSP_REPORT_ONLY=true` in `task-web.json`, D42). Browse every area with the browser console open; when it shows no violation, set `"false"` and deploy again |

## Step 16. Deploying from GitHub (D47, Part H2)

Deployments then run from GitHub after CI passes on `main` and you approve them, with no AWS key stored anywhere: GitHub's OIDC token is exchanged for a short-lived role.

```bash
aws iam create-open-id-connect-provider --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com
aws iam create-role --role-name ${APP}-github-deploy \
  --assume-role-policy-document "file://$(infrastructure/aws/scripts/render.sh iam/github-oidc-trust.json)" >/dev/null
aws iam put-role-policy --role-name ${APP}-github-deploy --policy-name deploy \
  --policy-document "file://$(infrastructure/aws/scripts/render.sh iam/github-deploy-policy.json)"
```

The role trusts only `repo:<owner>/<repo>:environment:production`, so a fork or another branch's workflow cannot use it. In GitHub (Settings, Environments): create `production`, add yourself as a required reviewer, and add these environment variables (ids, not secrets): `AWS_REGION`, `AWS_ACCOUNT_ID`, `DOMAIN`, `PUBLIC_SUBNETS`, `SG_TASKS`, `RDS_ENDPOINT`, `SECRET_ARN`, `UPLOADS_BUCKET`. Then activate the workflow: `cp infrastructure/aws/templates/github/deploy.yml .github/workflows/deploy.yml`, commit and push. Every later push to `main` that passes CI waits for your approval and then runs `deploy.sh`.

## Step 17. The WAF trial (D50, Part H2)

One regional web ACL on the ALB covers both `api.<domain>` and the app (CloudFront reaches the app through the ALB). Every rule only counts during the trial. The common rule set skips lesson saves, uploads and lab commands, which it would otherwise block (bodies over 8 KB, code that looks like XSS, `cat /etc/passwd` in the Linux lab). About $9 to $10 a month, billed hourly, so a few days cost about $2.

```bash
WAF_ARN=$(aws wafv2 create-web-acl --region "$AWS_REGION" --scope REGIONAL --name "$APP" \
  --default-action Allow={} --rules "file://$(infrastructure/aws/scripts/render.sh waf/rules.json)" \
  --visibility-config SampledRequestsEnabled=true,CloudWatchMetricsEnabled=true,MetricName=$APP \
  --query Summary.ARN --output text)
aws wafv2 associate-web-acl --region "$AWS_REGION" --web-acl-arn "$WAF_ARN" --resource-arn "$ALB_ARN"
```

After a few days of normal use, read what would have been blocked (AWS console, WAF, the web ACL, Sampled requests and the rule metrics). Then decide: keep it in block mode (in `templates/waf/rules.json`, change each `"OverrideAction": {"Count": {}}` to `{"None": {}}` and the rate rule's `"Action"` to `{"Block": {}}`, then `aws wafv2 update-web-acl`), or remove it with `aws wafv2 disassociate-web-acl` and `aws wafv2 delete-web-acl`.

## Step 18. Sleep and wake between demos (D49)

```bash
infrastructure/aws/scripts/sleep.sh    # asleep page, services to zero, database stopped (~$38 a month)
infrastructure/aws/scripts/wake.sh     # about 10 minutes: database, then services, then the page goes away
```

A deployment (by hand or from GitHub) keeps each service's desired count, so it never wakes a sleeping demo.

**Long breaks.** AWS restarts a stopped database by itself after 7 days (the budget alert would tell you). For a break of weeks, take a final snapshot and delete the instance instead (`aws rds delete-db-instance --db-instance-identifier "$APP" --final-db-snapshot-identifier ${APP}-break`, after turning deletion protection off), and restore it later with `aws rds restore-db-instance-from-db-snapshot` with the same identifier, subnet group and security group. Snapshot storage costs about $2 a month.

## Environment variables

Every production variable, its value and whether it is a secret: [`backend/.env.production.example`](../backend/.env.production.example) and [`frontend/.env.production.example`](../frontend/.env.production.example). On AWS:

- **Plain values** are in the task definitions' `environment` ([`templates/ecs/`](../infrastructure/aws/templates/ecs/)), filled in from `config.sh`.
- **Secrets** (`APP_KEY`, `DB_PASSWORD`, `INTERNAL_API_TOKEN`) are in Secrets Manager (step 7) and reach the containers through the task definitions' `secrets`.
- **No AWS keys anywhere:** S3 and SES use the task role (step 12); GitHub uses OIDC (step 16).
- `NEXT_PUBLIC_API_URL` is built into the web image (`deploy.sh`), not set at runtime.
- `php artisan cybercompass:preflight` (run by every release) refuses a production configuration that is unsafe: 17 checks, listed in [SECURITY_REVIEW.md](SECURITY_REVIEW.md) (S2).

## Logs

All containers log to CloudWatch Logs, group `/ecs/cybercompass`, one stream per task (`api/…`, `web/…`, `worker/…`, `scheduler/…`, `release/…`), kept 14 days.

```bash
aws logs tail "/ecs/$APP" --follow --log-stream-name-prefix api       # live
aws logs tail "/ecs/$APP" --since 1h --filter-pattern ERROR            # recent errors
```

Laravel logs at `info` and never includes request bodies; the admin audit log stays in the database (`/admin/audit`).

## Tearing down

In this order, each command once (dependencies first): the ECS services (`aws ecs update-service … --desired-count 0`, then `aws ecs delete-service`), the cluster, the CloudFront distributions (disable, wait, delete), the WAF ACL, the ALB listeners, target groups and load balancer, the database (turn off deletion protection, then `delete-db-instance`, with or without a final snapshot), the bucket (empty it first), the ECR repositories, the secret (`--force-delete-without-recovery` or a 7-day wait), the IAM roles and the OIDC provider, the security groups, the private subnets and their route table, the alarms, dashboard, metric filters, log group and SNS topic, the budget. The domain renews yearly until you turn off auto-renew.

## Future work

- **Infrastructure as code** (Terraform or AWS CDK): the same resources, created and destroyed by one command and reviewed in pull requests. Left out on purpose (D44) so every resource here is a command its owner can explain.
- **Private task subnets with a NAT gateway** (about $43 a month, D40) and **Multi-AZ RDS** (about twice the database cost, D43).
- **Graviton (ARM64) tasks**, about 20% cheaper, once the images are built for `linux/arm64`.
- **A smaller api image**: trimming the AWS SDK to S3 and SES would save about 100 MB (Part F).
- **Redis** (ElastiCache) for sessions, cache and queue, if the database ever becomes the bottleneck (D6).
