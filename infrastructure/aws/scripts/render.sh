#!/usr/bin/env bash
# Renders a template from infrastructure/aws/templates into infrastructure/aws/build (git-ignored),
# replacing only the ${NAME} placeholders listed below with values from config.sh. Anything else
# that looks like a variable is left as it is.
#   infrastructure/aws/scripts/render.sh ecs/task-api.json     -> build/ecs/task-api.json
set -euo pipefail
here="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=/dev/null
source "${here}/config.sh"

template="${1:?usage: render.sh <path under templates/>}"
out="${here}/build/${template}"
mkdir -p "$(dirname "${out}")"

vars='${AWS_REGION} ${AWS_ACCOUNT_ID} ${DOMAIN} ${GITHUB_REPO} ${APP} ${CLUSTER} ${ECR_API} ${ECR_WEB}
${UPLOADS_BUCKET} ${SECRET_ARN} ${RDS_ENDPOINT} ${LOG_RETENTION_DAYS} ${IMAGE_TAG} ${ORIGIN_VERIFY_SECRET}
${CERT_CDN_ARN} ${OAC_ID} ${ALB_DNS} ${CDN_MEDIA_ID} ${CDN_APP_ID} ${SNS_ALERTS_ARN} ${ALERT_EMAIL}
${TG_API_ARN} ${TG_WEB_ARN} ${ALB_ARN} ${ALB_DIMENSION}'
envsubst "${vars}" < "${here}/templates/${template}" > "${out}"
echo "${out}"
