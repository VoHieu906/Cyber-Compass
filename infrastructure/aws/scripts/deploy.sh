#!/usr/bin/env bash
# One deployment (docs/DEPLOYMENT_AWS.md, step 13): build both images, push them to ECR, register
# the task definitions, run the release task (preflight, migrations, content) and, only if it
# succeeded, roll the three services onto the new images. Run from the repository root:
#   source infrastructure/aws/config.sh && infrastructure/aws/scripts/deploy.sh
# The GitHub Actions deployment (D47) runs this same script.
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "${here}/config.sh"
step() { printf '\n==> %s\n' "$*"; }

registry="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
  echo "Warning: uncommitted changes are included in this build (tag ${IMAGE_TAG})." >&2
fi

step "Log Docker in to ECR (${registry})"
aws ecr get-login-password --region "${AWS_REGION}" | docker login --username AWS --password-stdin "${registry}"

step "Build the images for linux/amd64, tagged ${IMAGE_TAG}"
docker build --platform linux/amd64 --target production -t "${registry}/${ECR_API}:${IMAGE_TAG}" backend
docker build --platform linux/amd64 --target production \
  --build-arg "NEXT_PUBLIC_API_URL=https://api.${DOMAIN}" -t "${registry}/${ECR_WEB}:${IMAGE_TAG}" frontend

step "Push them"
docker push "${registry}/${ECR_API}:${IMAGE_TAG}"
docker push "${registry}/${ECR_WEB}:${IMAGE_TAG}"

step "Register the task definitions (new revisions pointing at ${IMAGE_TAG})"
for name in api web worker release; do
  file="$("${here}/scripts/render.sh" "ecs/task-${name}.json")"
  aws ecs register-task-definition --cli-input-json "file://${file}" \
    --query 'taskDefinition.taskDefinitionArn' --output text
done

step "Run the release task and wait for it"
network="awsvpcConfiguration={subnets=[${PUBLIC_SUBNETS}],securityGroups=[${SG_TASKS}],assignPublicIp=ENABLED}"
task_arn="$(aws ecs run-task --cluster "${CLUSTER}" --task-definition "${APP}-release" --launch-type FARGATE \
  --network-configuration "${network}" --query 'tasks[0].taskArn' --output text)"
echo "release task: ${task_arn}"
aws ecs wait tasks-stopped --cluster "${CLUSTER}" --tasks "${task_arn}"
exit_code="$(aws ecs describe-tasks --cluster "${CLUSTER}" --tasks "${task_arn}" --query 'tasks[0].containers[0].exitCode' --output text)"
if [ "${exit_code}" != "0" ]; then
  echo "The release task failed (exit code ${exit_code}); the services were not changed." >&2
  echo "Its log: CloudWatch Logs, group /ecs/${APP}, stream release/release/${task_arn##*/}" >&2
  exit 1
fi

# Creates a service on the first deployment; afterwards only its task definition changes, and its
# desired count is left as it is, so a deployment never wakes a sleeping demo (D47, D49). The
# circuit breaker rolls a deployment back by itself if its new tasks never become healthy.
ensure_service() {
  local name="$1" load_balancer="$2" status
  status="$(aws ecs describe-services --cluster "${CLUSTER}" --services "${APP}-${name}" \
    --query 'services[0].status' --output text 2>/dev/null || true)"
  if [ "${status}" = "ACTIVE" ]; then
    aws ecs update-service --cluster "${CLUSTER}" --service "${APP}-${name}" --task-definition "${APP}-${name}" \
      --query 'service.taskDefinition' --output text
    return
  fi
  local lb_args=()
  if [ -n "${load_balancer}" ]; then
    lb_args=(--load-balancers "${load_balancer}" --health-check-grace-period-seconds 90)
  fi
  aws ecs create-service --cluster "${CLUSTER}" --service-name "${APP}-${name}" --task-definition "${APP}-${name}" \
    --desired-count 1 --launch-type FARGATE --network-configuration "${network}" "${lb_args[@]}" \
    --deployment-configuration 'minimumHealthyPercent=100,maximumPercent=200,deploymentCircuitBreaker={enable=true,rollback=true}' \
    --query 'service.serviceArn' --output text
}

step "Roll the services onto the new revisions (created on the first deployment)"
ensure_service api "targetGroupArn=${TG_API_ARN},containerName=api,containerPort=8080"
ensure_service web "targetGroupArn=${TG_WEB_ARN},containerName=web,containerPort=3000"
ensure_service worker ""
aws ecs wait services-stable --cluster "${CLUSTER}" --services "${APP}-api" "${APP}-web" "${APP}-worker"

step "Deployed ${IMAGE_TAG}"
