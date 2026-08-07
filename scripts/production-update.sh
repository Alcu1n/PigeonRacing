#!/usr/bin/env bash
# [IN]: Production update mode and host-local deployment configuration / 生产更新模式与服务器本地部署配置
# [OUT]: One-command frontend, backend, or full production update flow / 前端、后端或完整生产更新的一键流程
# [POS]: Production deployment orchestrator / 生产部署编排脚本
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

set -euo pipefail
umask 022

ROOT_DIR="${PIGEON_ROOT:-/opt/pigeon-racing}"
COMPOSE_FILE="${COMPOSE_FILE:-$ROOT_DIR/docker-compose.yml}"
MODE="${1:-}"
PULL_CODE="${PULL_CODE:-1}"
FRONTEND_ASSET_MODE="${FRONTEND_ASSET_MODE:-auto}"
STRICT_GIT_STATUS="${STRICT_GIT_STATUS:-0}"
OSS_ENV_FILE="${OSS_ENV_FILE:-$ROOT_DIR/.env.oss.local}"
NODE_IMAGE="${NODE_IMAGE:-node:22-alpine}"
RUN_TYPECHECK="${RUN_TYPECHECK:-0}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1:8080}"
HEALTHCHECK_RETRIES="${HEALTHCHECK_RETRIES:-30}"
HEALTHCHECK_INTERVAL="${HEALTHCHECK_INTERVAL:-2}"

SCRIPT_PATH="${BASH_SOURCE[0]}"
ORIGINAL_ARGS=("$@")
PREVIOUS_COMMIT="${PIGEON_DEPLOY_PREVIOUS_COMMIT:-}"
DEPLOYMENT_UPDATED="${PIGEON_DEPLOYMENT_UPDATED:-0}"
MIGRATIONS_CHANGED="0"

if [[ "$SCRIPT_PATH" != /* ]]; then
    SCRIPT_PATH="$PWD/$SCRIPT_PATH"
fi

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

usage() {
    cat <<'USAGE'
Usage:
  bash scripts/production-update.sh frontend
  bash scripts/production-update.sh backend
  bash scripts/production-update.sh full

Environment variables:
  PIGEON_ROOT=/opt/pigeon-racing      Production project directory.
  COMPOSE_FILE=/path/docker-compose.yml
  PULL_CODE=0                         Skip git fetch/pull.
  FRONTEND_ASSET_MODE=auto|oss|local    Default: auto (oss when OSS credentials exist, otherwise local).
  OSS_ENV_FILE=/path/.env.oss.local     OSS credential file checked by auto mode.
  NODE_IMAGE=node:22-alpine             Node image used to build the frontend when host npm is missing.
  FRONTEND_BUILD_TIMEOUT=1200           Maximum seconds for a container frontend build.
  NODE_IMAGE_PULL_TIMEOUT=180           Maximum seconds allowed to pull NODE_IMAGE.
  NPM_FETCH_TIMEOUT=120000              npm registry request timeout in milliseconds.
  NPM_FETCH_RETRIES=2                   npm registry retry count inside the Node container.
  NPM_CACHE_VOLUME=pigeon-member-h5-npm-cache
                                      Persistent Docker volume used for npm downloads.
  RUN_TYPECHECK=1                       Run vue-tsc typecheck before vite build (off by default).
  STRICT_GIT_STATUS=1                   Fail before pull when local changes exist.
  HEALTHCHECK_URL=http://127.0.0.1:8080  Local application URL used by the post-update health check.
  HEALTHCHECK_RETRIES=30                Number of health-check attempts.
  HEALTHCHECK_INTERVAL=2                Seconds between health-check attempts.
USAGE
}

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

ensure_mode() {
    case "$MODE" in
        frontend | backend | full)
            ;;
        *)
            usage
            fail "Update mode must be one of: frontend, backend, full"
            ;;
    esac
}

normalize_backend_permissions() {
    local entry mode path

    printf 'Normalizing backend source permissions for PHP-FPM...\n'

    while IFS= read -r -d '' entry; do
        mode="${entry%% *}"
        path="${entry#*$'\t'}"

        [[ "$path" == backend/* ]] || continue

        # Never change permissions on host-local environment files, even if one
        # is accidentally tracked in a production checkout.
        case "$path" in
            backend/.env | backend/.env.*)
                continue
                ;;
        esac

        case "$mode" in
            100644)
                [[ -f "$path" ]] && chmod 644 -- "$path"
                ;;
            100755)
                [[ -f "$path" ]] && chmod 755 -- "$path"
                ;;
        esac
    done < <(git ls-files --stage -z)

    # Git does not track directory read/execute bits. Keep PHP source
    # directories traversable, while leaving runtime storage permissions alone.
    local directory
    for directory in backend/app backend/bootstrap backend/config backend/database backend/public backend/resources backend/routes; do
        if [[ -d "$directory" ]]; then
            find "$directory" -type d -exec chmod a+rx {} +
        fi
    done

    # Composer-generated PHP files are not tracked by Git but must also be
    # readable by the www-data PHP-FPM worker.
    if [[ -d backend/vendor ]]; then
        find backend/vendor -type d -exec chmod a+rx {} +
        find backend/vendor -type f -exec chmod a+r {} +
    fi
}

calculate_deployment_flags() {
    local current_commit

    current_commit="$(git rev-parse HEAD)"
    if [[ -z "$PREVIOUS_COMMIT" ]]; then
        PREVIOUS_COMMIT="$current_commit"
    fi

    if [[ "$PREVIOUS_COMMIT" != "$current_commit" ]]; then
        DEPLOYMENT_UPDATED="1"
        if [[ -n "$(git diff --name-only "$PREVIOUS_COMMIT" "$current_commit" -- backend/database/migrations)" ]]; then
            MIGRATIONS_CHANGED="1"
        fi
    fi
}

reload_updated_script() {
    local script_checksum_after_pull

    [[ "$PULL_CODE" == "1" ]] || return

    script_checksum_after_pull="$(git hash-object -- "$SCRIPT_PATH")"
    if [[ -n "${SCRIPT_CHECKSUM_BEFORE_PULL:-}" && "$SCRIPT_CHECKSUM_BEFORE_PULL" != "$script_checksum_after_pull" ]]; then
        printf 'Deployment script changed during pull; reloading the updated script before continuing.\n'
        export PIGEON_DEPLOY_PREVIOUS_COMMIT="$PREVIOUS_COMMIT"
        export PIGEON_DEPLOYMENT_UPDATED="1"
        exec bash "$SCRIPT_PATH" "${ORIGINAL_ARGS[@]}"
    fi
}

prepare_workspace() {
    [[ -d "$ROOT_DIR" ]] || fail "Project directory does not exist: $ROOT_DIR"
    [[ -f "$COMPOSE_FILE" ]] || fail "Compose file does not exist: $COMPOSE_FILE"

    cd "$ROOT_DIR"

    if [[ -z "$PREVIOUS_COMMIT" ]]; then
        PREVIOUS_COMMIT="$(git rev-parse HEAD)"
    fi

    SCRIPT_CHECKSUM_BEFORE_PULL="$(git hash-object -- "$SCRIPT_PATH")"

    printf 'Project: %s\n' "$ROOT_DIR"
    printf 'Mode: %s\n' "$MODE"
    compose ps

    if [[ "$PULL_CODE" != "1" ]]; then
        printf 'Skipping git pull because PULL_CODE=%s\n' "$PULL_CODE"
    else
        timeout 60 git fetch origin || fail "git fetch timed out: cannot reach origin (check network/proxy, or use PULL_CODE=0)"

        if [[ -n "$(git status --short)" ]]; then
            printf 'Production worktree has local changes; git pull will use --autostash for tracked files:\n'
            git status --short

            if [[ "$STRICT_GIT_STATUS" == "1" ]]; then
                fail "Production worktree has local changes and STRICT_GIT_STATUS=1."
            fi
        fi

        git pull --ff-only --autostash
        calculate_deployment_flags
        reload_updated_script
    fi

    normalize_backend_permissions
}

resolve_frontend_asset_mode() {
    if [[ "$MODE" != "frontend" && "$MODE" != "full" ]]; then
        return
    fi

    if [[ "$FRONTEND_ASSET_MODE" != "auto" ]]; then
        return
    fi

    if [[ -f "$OSS_ENV_FILE" ]]; then
        set -a
        # shellcheck source=/dev/null
        source "$OSS_ENV_FILE"
        set +a
    fi

    if [[ -n "${OSS_ACCESS_KEY_ID:-}" && -n "${OSS_ACCESS_KEY_SECRET:-}" ]]; then
        FRONTEND_ASSET_MODE="oss"
        printf 'OSS credentials detected; frontend assets will be deployed to OSS/CDN.\n'
    else
        FRONTEND_ASSET_MODE="local"
        printf 'OSS credentials not found; falling back to local frontend build (FRONTEND_ASSET_MODE=local).\n'
    fi
}

build_frontend_local() {
    if command -v npm >/dev/null 2>&1; then
        cd "$ROOT_DIR/frontend/member-h5"
        npm ci --include=dev

        if [[ "$RUN_TYPECHECK" == "1" ]]; then
            npm run typecheck
        fi

        npx vite build
        cd "$ROOT_DIR"
        return
    fi

    command -v docker >/dev/null 2>&1 || fail "npm not found on host and docker is unavailable: install Node.js or Docker to build the frontend"

    local typecheck_cmd=""
    if [[ "$RUN_TYPECHECK" == "1" ]]; then
        typecheck_cmd="npm run typecheck && "
    fi

    printf 'npm not found on host; building frontend inside %s container.\n' "$NODE_IMAGE"
    docker run --rm \
        -v "$ROOT_DIR/frontend/member-h5":/app \
        -w /app \
        "$NODE_IMAGE" \
        sh -c "npm ci --include=dev && ${typecheck_cmd}npx vite build"
}

deploy_frontend() {
    local restart_nginx="${1:-1}"

    case "$FRONTEND_ASSET_MODE" in
        oss)
            bash "$ROOT_DIR/scripts/deploy-member-assets-to-oss.sh"
            ;;
        local)
            build_frontend_local
            ;;
        *)
            fail "FRONTEND_ASSET_MODE must be auto, oss or local"
            ;;
    esac

    if [[ "$restart_nginx" == "1" ]]; then
        compose restart nginx
    fi
}

prepare_backend() {
    if ! compose build app queue scheduler; then
        abort_backend_update "Backend image build failed"
    fi

    if ! compose run --rm app composer install --no-dev --optimize-autoloader; then
        abort_backend_update "Composer install failed"
    fi

    normalize_backend_permissions
}

verify_geoip_database() {
    printf 'Checking optional MaxMind Country database readability...\n'

    if ! compose run --rm --no-deps --user www-data app sh -c 'db="$$(printenv MAXMIND_COUNTRY_DB_PATH 2>/dev/null || true)"; if [ -z "$$db" ]; then exit 0; fi; test -r "$$db"'; then
        abort_backend_update "MAXMIND_COUNTRY_DB_PATH is configured but the GeoLite2/MaxMind Country database is not readable"
    fi
}

preflight_backend() {
    verify_geoip_database
    printf 'Running Laravel route preflight as www-data...\n'

    if ! compose run --rm --no-deps --user www-data app php artisan route:list --no-ansi --no-interaction >/dev/null; then
        abort_backend_update "Laravel route preflight failed; PHP-FPM would not be able to serve this revision"
    fi
}

apply_backend() {
    if ! compose up -d --remove-orphans; then
        abort_backend_update "Backend containers failed to start"
    fi

    # Re-resolve the recreated app container before probing through Nginx.
    # Nginx keeps the old Compose service IP until it is restarted.
    if ! compose restart nginx; then
        abort_backend_update "Nginx failed to reload the recreated app upstream"
    fi

    # Check the live bind-mounted source before migrations and cache writes.
    # This is the earliest point where the real web stack can be rejected.
    if ! verify_http; then
        abort_backend_update "Pre-migration HTTP health check failed"
    fi

    if ! compose exec -T app php artisan migrate --force; then
        fail "Database migration failed; deployment stopped before reporting success"
    fi
    compose exec -T app php artisan filament:assets
    compose exec -T app php artisan optimize:clear
    compose exec -T app php artisan config:cache
    compose exec -T app php artisan route:cache
    compose exec -T app php artisan view:cache
    compose restart app queue scheduler nginx
}

http_status() {
    local path="$1"

    curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 5 --max-time 15 \
        "$HEALTHCHECK_URL$path" 2>/dev/null || printf '000'
}

verify_http() {
    command -v curl >/dev/null 2>&1 || fail "curl is required for the production HTTP health check"

    local attempt up_status login_status admin_status branding_status
    for ((attempt = 1; attempt <= HEALTHCHECK_RETRIES; attempt++)); do
        up_status="$(http_status /up)"
        login_status="$(http_status /admin/login)"
        admin_status="$(http_status /admin)"
        branding_status="$(http_status /api/member/branding)"

        if [[ "$up_status" == "200" && "$login_status" == "200" && "$admin_status" == "302" && "$branding_status" == "200" ]]; then
            printf 'HTTP health check passed: /up=%s /admin/login=%s /admin=%s /api/member/branding=%s\n' \
                "$up_status" "$login_status" "$admin_status" "$branding_status"
            return 0
        fi

        sleep "$HEALTHCHECK_INTERVAL"
    done

    printf 'HTTP health check failed after %s attempts: /up=%s /admin/login=%s /admin=%s /api/member/branding=%s\n' \
        "$HEALTHCHECK_RETRIES" "$up_status" "$login_status" "$admin_status" "$branding_status" >&2
    return 1
}

rollback_previous_revision() {
    local current_commit

    [[ "$DEPLOYMENT_UPDATED" == "1" ]] || return 1
    [[ -n "$PREVIOUS_COMMIT" ]] || return 1

    current_commit="$(git rev-parse HEAD)"
    if [[ "$current_commit" == "$PREVIOUS_COMMIT" ]]; then
        return 1
    fi

    printf 'Restoring previous revision %s after failed backend validation...\n' "${PREVIOUS_COMMIT:0:12}"
    git reset --merge "$PREVIOUS_COMMIT"
    normalize_backend_permissions
    compose build app queue scheduler
    compose run --rm app composer install --no-dev --optimize-autoloader
    normalize_backend_permissions
    compose up -d --remove-orphans
    compose exec -T app php artisan optimize:clear
    compose exec -T app php artisan config:cache
    compose exec -T app php artisan route:cache
    compose exec -T app php artisan view:cache
    compose restart app queue scheduler nginx
    verify_http
}

abort_backend_update() {
    local reason="$1"

    if rollback_previous_revision; then
        fail "$reason; previous revision was restored and HTTP health passed"
    fi

    fail "$reason; automatic rollback could not restore a healthy previous revision"
}

verify_services() {
    compose ps

    if ! verify_http; then
        compose logs --tail=100 app nginx queue || true

        if [[ "$MIGRATIONS_CHANGED" == "0" ]] && rollback_previous_revision; then
            fail "Post-update HTTP health check failed; previous revision was restored and HTTP health passed"
        fi

        fail "Post-update HTTP health check failed; deployment was not reported as successful"
    fi

    compose logs --tail=50 app
    compose logs --tail=50 nginx
    compose logs --tail=50 queue
}

main() {
    ensure_mode
    prepare_workspace
    resolve_frontend_asset_mode

    case "$MODE" in
        frontend)
            deploy_frontend 1
            ;;
        backend)
            prepare_backend
            preflight_backend
            apply_backend
            ;;
        full)
            prepare_backend
            preflight_backend
            deploy_frontend 0
            apply_backend
            ;;
    esac

    verify_services

    if [[ "$MODE" == "frontend" || "$MODE" == "full" ]] && [[ "$FRONTEND_ASSET_MODE" == "oss" ]]; then
        cat <<'NOTICE'
Hashed assets were published to OSS/CDN; routine directory refresh is not required.
Verify the new hashed asset URLs from the origin HTML.
Use an exact-file refresh only for an emergency same-name resource update.
NOTICE
    fi

    printf 'Production update finished: %s\n' "$MODE"
}

main "$@"
