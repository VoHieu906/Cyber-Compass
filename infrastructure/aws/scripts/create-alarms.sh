#!/usr/bin/env bash
# Monitoring (D48, docs/DEPLOYMENT_AWS.md step 14): two metric filters that turn the scheduler's
# queue line into metrics, nine alarms that email the SNS topic, and one dashboard. Safe to run
# again: each call replaces what it created before. Every alarm treats missing data as fine, so
# nothing fires while the demo is asleep (D49).
#   source infrastructure/aws/config.sh && infrastructure/aws/scripts/create-alarms.sh
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "${here}/config.sh"
step() { printf '\n==> %s\n' "$*"; }

: "${SNS_ALERTS_ARN:?Set SNS_ALERTS_ARN in config.sh (step 14)}"
: "${ALB_DIMENSION:?Set ALB_ARN in config.sh (step 10)}"

step "Metric filters on /ecs/${APP}: the scheduler logs {\"message\":\"queue\",\"context\":{...}} every minute"
aws logs put-metric-filter --log-group-name "/ecs/${APP}" --filter-name queue-pending \
  --filter-pattern '{ $.message = "queue" }' \
  --metric-transformations 'metricName=QueuePending,metricNamespace=CyberCompass,metricValue=$.context.pending,defaultValue=0'
aws logs put-metric-filter --log-group-name "/ecs/${APP}" --filter-name queue-failed \
  --filter-pattern '{ $.message = "queue" }' \
  --metric-transformations 'metricName=QueueFailed,metricNamespace=CyberCompass,metricValue=$.context.failed,defaultValue=0'

# alarm <name> <namespace> <metric> <statistic> <threshold> <periods> <dimensions...>
alarm() {
  local name="$1" namespace="$2" metric="$3" stat="$4" threshold="$5" periods="$6"
  shift 6
  local stat_args=(--statistic "${stat}")
  [[ "${stat}" == p* ]] && stat_args=(--extended-statistic "${stat}")
  aws cloudwatch put-metric-alarm --alarm-name "${APP}-${name}" --namespace "${namespace}" --metric-name "${metric}" \
    "${stat_args[@]}" --period 300 --evaluation-periods "${periods}" --threshold "${threshold}" \
    --comparison-operator GreaterThanOrEqualToThreshold --treat-missing-data notBreaching \
    --alarm-actions "${SNS_ALERTS_ARN}" --ok-actions "${SNS_ALERTS_ARN}" ${1:+--dimensions "$@"}
  echo "alarm ${APP}-${name}"
}

step "Alarms (they email ${ALERT_EMAIL} through the SNS topic)"
alarm api-5xx AWS/ApplicationELB HTTPCode_Target_5XX_Count Sum 10 1 "Name=LoadBalancer,Value=${ALB_DIMENSION}"
alarm latency-p95 AWS/ApplicationELB TargetResponseTime p95 1.5 3 "Name=LoadBalancer,Value=${ALB_DIMENSION}"
alarm api-cpu AWS/ECS CPUUtilization Average 80 3 "Name=ClusterName,Value=${CLUSTER}" "Name=ServiceName,Value=${APP}-api"
alarm api-memory AWS/ECS MemoryUtilization Average 85 3 "Name=ClusterName,Value=${CLUSTER}" "Name=ServiceName,Value=${APP}-api"
alarm worker-memory AWS/ECS MemoryUtilization Average 85 3 "Name=ClusterName,Value=${CLUSTER}" "Name=ServiceName,Value=${APP}-worker"
alarm rds-connections AWS/RDS DatabaseConnections Maximum 50 2 "Name=DBInstanceIdentifier,Value=${APP}"
# Free storage alarms when it falls below 2 GB, so this one compares the other way.
aws cloudwatch put-metric-alarm --alarm-name "${APP}-rds-free-storage" --namespace AWS/RDS --metric-name FreeStorageSpace \
  --statistic Minimum --period 300 --evaluation-periods 1 --threshold 2000000000 \
  --comparison-operator LessThanThreshold --treat-missing-data notBreaching \
  --alarm-actions "${SNS_ALERTS_ARN}" --ok-actions "${SNS_ALERTS_ARN}" --dimensions "Name=DBInstanceIdentifier,Value=${APP}"
echo "alarm ${APP}-rds-free-storage"
alarm queue-backlog CyberCompass QueuePending Maximum 50 2
alarm queue-failed CyberCompass QueueFailed Maximum 1 1

step "Dashboard ${APP}"
dashboard="$("${here}/scripts/render.sh" monitoring/dashboard.json)"
aws cloudwatch put-dashboard --dashboard-name "${APP}" --dashboard-body "file://${dashboard}"

step "Done: CloudWatch, Dashboards, ${APP}"
