#!/usr/bin/env bash
# Wakes the demo (D49), in the order that keeps it working: the database first (the services
# need it as they start), then one task per service, then the asleep page is removed once the
# services are stable. Takes about 10 minutes, most of it the database starting.
#   source infrastructure/aws/config.sh && infrastructure/aws/scripts/wake.sh
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "${here}/config.sh"
step() { printf '\n==> %s\n' "$*"; }

step "Start the database and wait until it is available"
status="$(aws rds describe-db-instances --db-instance-identifier "${APP}" --query 'DBInstances[0].DBInstanceStatus' --output text)"
if [ "${status}" = "stopped" ]; then
  aws rds start-db-instance --db-instance-identifier "${APP}" --query 'DBInstance.DBInstanceStatus' --output text
fi
aws rds wait db-instance-available --db-instance-identifier "${APP}"

step "Start one task per service and wait until they are stable"
for name in api web worker; do
  aws ecs update-service --cluster "${CLUSTER}" --service "${APP}-${name}" --desired-count 1 \
    --query 'service.[serviceName,desiredCount]' --output text
done
aws ecs wait services-stable --cluster "${CLUSTER}" --services "${APP}-api" "${APP}-web" "${APP}-worker"

step "Remove the asleep page"
rule="$(aws elbv2 describe-rules --listener-arn "${LISTENER_HTTPS_ARN}" --query "Rules[?Priority=='1'].RuleArn" --output text)"
if [ -n "${rule}" ]; then
  aws elbv2 delete-rule --rule-arn "${rule}"
fi

step "Awake: https://app.${DOMAIN}"
