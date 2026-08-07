# [IN]: Member H5 source, ossutil 2.0, and OSS environment variables / 会员端 H5 源码、ossutil 2.0 与 OSS 环境变量
# [OUT]: Lockfile-synced CDN Vite build and baseline-compatible OSS asset sync / 按锁文件同步依赖的 CDN Vite 构建与基础兼容 OSS 资源同步
# [POS]: Production helper for uploading member static assets to Alibaba Cloud OSS / 上传会员端静态资源到阿里云 OSS 的生产辅助脚本
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OSS_ENV_FILE="${OSS_ENV_FILE:-$ROOT_DIR/.env.oss.local}"
FRONTEND_DIR="$ROOT_DIR/frontend/member-h5"
ASSETS_DIR="$FRONTEND_DIR/dist/assets"
INDEX_FILE="$FRONTEND_DIR/dist/index.html"

if [[ -f "$OSS_ENV_FILE" ]]; then
    set -a
    # shellcheck source=/dev/null
    source "$OSS_ENV_FILE"
    set +a
fi

OSSUTIL_BIN="${OSSUTIL_BIN:-ossutil}"
OSS_BUCKET="${OSS_BUCKET:-feilesaigecn}"
OSS_PREFIX="${OSS_PREFIX:-assets/}"
OSS_REGION="${OSS_REGION:-cn-shenzhen}"
VITE_ASSET_BASE_URL="${VITE_ASSET_BASE_URL:-https://cdn.feilesaige.cn/}"
OSS_CACHE_CONTROL="${OSS_CACHE_CONTROL:-public, max-age=31536000, immutable}"
RUN_TYPECHECK="${RUN_TYPECHECK:-0}"
DRY_RUN="${DRY_RUN:-0}"
OSS_DELETE_EXTRA="${OSS_DELETE_EXTRA:-0}"
NODE_IMAGE="${NODE_IMAGE:-node:22-alpine}"
FRONTEND_BUILD_TIMEOUT="${FRONTEND_BUILD_TIMEOUT:-1200}"
NODE_IMAGE_PULL_TIMEOUT="${NODE_IMAGE_PULL_TIMEOUT:-180}"
NPM_FETCH_TIMEOUT="${NPM_FETCH_TIMEOUT:-120000}"
NPM_FETCH_RETRIES="${NPM_FETCH_RETRIES:-2}"
NPM_CACHE_VOLUME="${NPM_CACHE_VOLUME:-pigeon-member-h5-npm-cache}"

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

normalize_prefix() {
    local value="$1"
    value="${value#/}"
    value="${value%/}"
    printf '%s' "$value"
}

normalize_region() {
    local value="$1"
    value="${value#oss-}"
    printf '%s' "$value"
}

oss_destination() {
    local prefix
    prefix="$(normalize_prefix "$OSS_PREFIX")"

    if [[ -z "$prefix" ]]; then
        printf 'oss://%s/' "$OSS_BUCKET"
        return
    fi

    printf 'oss://%s/%s/' "$OSS_BUCKET" "$prefix"
}

build_member_h5_on_host() {
    require_command timeout
    cd "$FRONTEND_DIR"

    printf 'Frontend build stage 1/2: npm ci on host...\n'
    if ! timeout "$FRONTEND_BUILD_TIMEOUT" npm ci \
        --include=dev \
        --no-audit \
        --no-fund \
        --prefer-offline \
        --fetch-retries "$NPM_FETCH_RETRIES" \
        --fetch-timeout "$NPM_FETCH_TIMEOUT" \
        --loglevel=info; then
        fail "Host npm dependency install failed or exceeded ${FRONTEND_BUILD_TIMEOUT}s"
    fi

    if [[ "$RUN_TYPECHECK" == "1" ]]; then
        printf 'Frontend build stage 2/3: typecheck on host...\n'
        if ! timeout "$FRONTEND_BUILD_TIMEOUT" npm run typecheck; then
            fail "Host typecheck failed or exceeded ${FRONTEND_BUILD_TIMEOUT}s"
        fi
        printf 'Frontend build stage 3/3: Vite build on host...\n'
    else
        printf 'Frontend build stage 2/2: Vite build on host...\n'
    fi

    if ! timeout "$FRONTEND_BUILD_TIMEOUT" env VITE_ASSET_BASE_URL="$VITE_ASSET_BASE_URL" \
        npx vite build; then
        fail "Host Vite build failed or exceeded ${FRONTEND_BUILD_TIMEOUT}s"
    fi
}

prepare_node_image() {
    require_command docker
    require_command timeout

    printf 'Checking Docker daemon before frontend build...\n'
    if ! timeout 30 docker info >/dev/null 2>&1; then
        fail "Docker daemon is unavailable; start Docker or restore host npm before retrying"
    fi

    if timeout 30 docker image inspect "$NODE_IMAGE" >/dev/null 2>&1; then
        printf 'Using cached Node image: %s\n' "$NODE_IMAGE"
        return
    fi

    printf 'Node image %s is not cached; pulling it (timeout: %ss)...\n' \
        "$NODE_IMAGE" "$NODE_IMAGE_PULL_TIMEOUT"
    if ! timeout "$NODE_IMAGE_PULL_TIMEOUT" docker pull "$NODE_IMAGE"; then
        fail "Unable to pull $NODE_IMAGE within ${NODE_IMAGE_PULL_TIMEOUT}s; check Docker Hub/network access or pre-pull the image manually"
    fi
}

build_member_h5_in_container() {
    local typecheck_cmd=""
    local install_stage="1/2"
    local build_stage="2/2"
    local container_build_command

    if [[ "$RUN_TYPECHECK" == "1" ]]; then
        install_stage="1/3"
        build_stage="3/3"
        typecheck_cmd="printf '%s\\n' 'Frontend container stage 2/3: typecheck'; npm run typecheck;"
    fi

    prepare_node_image

    printf 'npm not found on host; building member H5 inside %s container (timeout: %ss)...\n' \
        "$NODE_IMAGE" "$FRONTEND_BUILD_TIMEOUT"
    container_build_command="printf '%s\\n' 'Frontend container stage ${install_stage}: npm ci (persistent cache: $NPM_CACHE_VOLUME)'; npm ci --include=dev --no-audit --no-fund --prefer-offline; ${typecheck_cmd} printf '%s\\n' 'Frontend container stage ${build_stage}: Vite build'; npx vite build"

    if ! timeout "$FRONTEND_BUILD_TIMEOUT" docker run --rm --pull=never \
        --mount "type=bind,src=$FRONTEND_DIR,dst=/app" \
        --mount "type=volume,src=$NPM_CACHE_VOLUME,dst=/root/.npm" \
        --mount "type=volume,dst=/app/node_modules" \
        -w /app \
        -e NPM_CONFIG_CACHE=/root/.npm \
        -e NPM_CONFIG_AUDIT=false \
        -e NPM_CONFIG_FUND=false \
        -e NPM_CONFIG_PREFER_OFFLINE=true \
        -e NPM_CONFIG_FETCH_RETRIES="$NPM_FETCH_RETRIES" \
        -e NPM_CONFIG_FETCH_TIMEOUT="$NPM_FETCH_TIMEOUT" \
        -e NPM_CONFIG_LOGLEVEL=info \
        -e VITE_ASSET_BASE_URL="$VITE_ASSET_BASE_URL" \
        "$NODE_IMAGE" \
        sh -eu -c "$container_build_command"; then
        fail "Container frontend build failed or exceeded ${FRONTEND_BUILD_TIMEOUT}s; inspect the last reported npm/Vite stage"
    fi
}

build_member_h5() {
    if command -v npm >/dev/null 2>&1; then
        build_member_h5_on_host
        return
    fi

    build_member_h5_in_container
}

verify_build() {
    [[ -d "$ASSETS_DIR" ]] || fail "Missing build output: $ASSETS_DIR"
    find "$ASSETS_DIR" -type f -print -quit | grep -q . || fail "No files found in $ASSETS_DIR"

    if [[ "$VITE_ASSET_BASE_URL" != "/" ]]; then
        grep -q "$VITE_ASSET_BASE_URL" "$INDEX_FILE" \
            || fail "dist/index.html does not reference VITE_ASSET_BASE_URL=$VITE_ASSET_BASE_URL"
    fi
}

sync_assets() {
    local destination
    local dry_run_flags=()
    local delete_flags=()
    local cache_flags=(--cache-control "$OSS_CACHE_CONTROL")

    destination="$(oss_destination)"

    if [[ "$DRY_RUN" == "1" ]]; then
        dry_run_flags+=(--dry-run)
    fi

    if [[ "$OSS_DELETE_EXTRA" == "1" ]]; then
        delete_flags+=(--delete)
    fi

    printf 'Syncing %s -> %s\n' "$ASSETS_DIR/" "$destination"

    "$OSSUTIL_BIN" sync "$ASSETS_DIR/" "$destination" \
        -f \
        "${cache_flags[@]}" \
        ${dry_run_flags[@]+"${dry_run_flags[@]}"} \
        ${delete_flags[@]+"${delete_flags[@]}"}
}

main() {
    [[ -n "$OSS_BUCKET" ]] || fail "Set OSS_BUCKET, for example: export OSS_BUCKET=your-bucket"
    [[ -n "$OSS_REGION" ]] || fail "Set OSS_REGION, for example: export OSS_REGION=oss-cn-hangzhou"
    [[ -n "${OSS_ACCESS_KEY_ID:-}" ]] || fail "Set OSS_ACCESS_KEY_ID in a host-local secret file"
    [[ -n "${OSS_ACCESS_KEY_SECRET:-}" ]] || fail "Set OSS_ACCESS_KEY_SECRET in a host-local secret file"

    OSS_REGION="$(normalize_region "$OSS_REGION")"
    export OSS_REGION

    if [[ -f "$OSS_ENV_FILE" ]]; then
        printf 'Loaded OSS env file: %s\n' "$OSS_ENV_FILE"
    fi

    if command -v npm >/dev/null 2>&1; then
        require_command node
    else
        require_command docker
    fi
    require_command "$OSSUTIL_BIN"

    build_member_h5
    verify_build
    sync_assets

    printf 'Done. Entry HTML stays on origin; only dist/assets is uploaded to OSS.\n'
}

main "$@"
