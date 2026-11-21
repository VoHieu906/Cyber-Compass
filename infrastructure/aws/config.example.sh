# CyberCompass on AWS: the values every step and script reads (docs/DEPLOYMENT_AWS.md).
# Copy to config.sh (git-ignored), fill it in as the guide goes, and load it in each terminal:
#   cp infrastructure/aws/config.example.sh infrastructure/aws/config.sh
#   source infrastructure/aws/config.sh
# Values marked "from step N" are printed by that step of the guide.

# --- Account and place ----------------------------------------------------------------------
export AWS_REGION=ap-southeast-1                 # Singapore (D40 to D43 were priced here)
export AWS_ACCOUNT_ID=123456789012               # aws sts get-caller-identity --query Account --output text
export DOMAIN=example.com                        # the domain registered in Route 53 (D41)
export ALERT_EMAIL=you@example.com               # budget and alarm emails (D48)
export GITHUB_REPO=VoHieu906/Cyber-Compass       # for the deployment role (D47)

# --- Names (change only to run a second copy) -----------------------------------------------
export APP=cybercompass
export CLUSTER=${APP}
export ECR_API=${APP}-api
export ECR_WEB=${APP}-web
export UPLOADS_BUCKET=${APP}-uploads-${AWS_ACCOUNT_ID}    # bucket names are global
export SECRET_NAME=${APP}/production
export LOG_RETENTION_DAYS=14

# --- Filled in while following the guide ----------------------------------------------------
export HOSTED_ZONE_ID=                           # from step 2
export VPC_ID=                                   # from step 4 (the default VPC)
export PUBLIC_SUBNETS=                           # from step 4, comma separated, two or more AZs
export PRIVATE_SUBNETS=                          # from step 4, the two RDS subnets
export SG_ALB=                                   # from step 4
export SG_TASKS=                                 # from step 4
export SG_RDS=                                   # from step 4
export RDS_ENDPOINT=                             # from step 6
export SECRET_ARN=                               # from step 7
export CERT_ALB_ARN=                             # from step 3 (in AWS_REGION)
export CERT_CDN_ARN=                             # from step 3 (in us-east-1, for CloudFront)
export ALB_ARN=                                  # from step 10
export ALB_DNS=                                  # from step 10
export ALB_DIMENSION=${ALB_ARN#*:loadbalancer/}     # app/<name>/<id>, for CloudWatch (derived)
export LISTENER_HTTPS_ARN=                       # from step 10
export TG_API_ARN=                               # from step 10
export TG_WEB_ARN=                               # from step 10
export ORIGIN_VERIFY_SECRET=                     # from step 10: openssl rand -hex 32 (CloudFront to ALB, D41)
export OAC_ID=                                   # from step 11
export CDN_APP_ID=                               # from step 11
export CDN_APP_DOMAIN=                           # from step 11 (dxxxx.cloudfront.net)
export CDN_MEDIA_ID=                             # from step 11
export CDN_MEDIA_DOMAIN=                         # from step 11
export SNS_ALERTS_ARN=                           # from step 14

# --- Per deployment -------------------------------------------------------------------------
export IMAGE_TAG=$(git rev-parse --short HEAD 2>/dev/null || echo manual)
