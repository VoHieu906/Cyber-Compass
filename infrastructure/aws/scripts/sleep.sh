#!/usr/bin/env bash
# Puts the demo to sleep between presentations (D49): the load balancer answers every request
# with a small "asleep" page, the three services scale to zero tasks, and the database stops.
# Asleep it costs about $38 a month instead of about $125 (the ALB and storage keep billing).
#   source infrastructure/aws/config.sh && infrastructure/aws/scripts/sleep.sh
#
# AWS starts a stopped RDS instance again by itself after 7 days. The budget alert catches that;
# for a longer break see "Long breaks" in docs/DEPLOYMENT_AWS.md.
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "${here}/config.sh"
step() { printf '\n==> %s\n' "$*"; }

step "Show the asleep page (listener rule, priority 1)"
existing="$(aws elbv2 describe-rules --listener-arn "${LISTENER_HTTPS_ARN}" --query "Rules[?Priority=='1'].RuleArn" --output text)"
if [ -z "${existing}" ]; then
  aws elbv2 create-rule --listener-arn "${LISTENER_HTTPS_ARN}" --priority 1 \
    --conditions Field=path-pattern,Values='/*' \
    --actions "file://${here}/templates/elb/asleep-action.json" --query 'Rules[0].RuleArn' --output text
else
  echo "already shown: ${existing}"
fi

step "Scale the services to zero tasks"
for name in api web worker; do
  aws ecs update-service --cluster "${CLUSTER}" --service "${APP}-${name}" --desired-count 0 \
    --query 'service.[serviceName,desiredCount]' --output text
done

step "Stop the database (it keeps its data; storage is still billed)"
status="$(aws rds describe-db-instances --db-instance-identifier "${APP}" --query 'DBInstances[0].DBInstanceStatus' --output text)"
if [ "${status}" = "available" ]; then
  aws rds stop-db-instance --db-instance-identifier "${APP}" --query 'DBInstance.DBInstanceStatus' --output text
else
  echo "database is ${status}; not stopping it"
fi

step "Asleep. Wake it with infrastructure/aws/scripts/wake.sh"
