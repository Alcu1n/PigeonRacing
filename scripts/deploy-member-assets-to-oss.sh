# [IN]: Member H5 source, ossutil 2.0, and OSS environment variables / 会员端 H5 源码、ossutil 2.0 与 OSS 环境变量
# [OUT]: CDN-prefixed Vite build and synchronized OSS assets prefix / CDN 前缀的 Vite 构建与已同步的 OSS assets 前缀
# [POS]: Production helper for uploading member static assets to Alibaba Cloud OSS / 上传会员端静态资源到阿里云 OSS 的生产辅助脚本
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OSS_ENV_FILE="${OSS_ENV_FILE:-$ROOT_DIR/.env.oss.local}"
FRONTEND_DIR="$ROOT_DIR/frontend/member-h5"
ASSETS_DIR="$FRONTEND_DIR/dist/assets"
INDEX_FILE="$FRONTEND_DIR/dist/index.html"
SNAPSHOT_DIR="$ROOT_DIR/.ossutil-snapshot/member-h5-assets"

if [[ -f "$OSS_ENV_FILE" ]]; then
    set -a
    # shellcheck source=/dev/null
    source "$OSS_ENV_FILE"
    set +a
fi

OSSUTIL_BIN="${OSSUTIL_BIN:-ossutil}"
OSS_BUCKET="${OSS_BUCKET:-filesg}"
OSS_PREFIX="${OSS_PREFIX:-assets/}"
OSS_REGION="${OSS_REGION:-oss-cn-hongkong}"
VITE_ASSET_BASE_URL="${VITE_ASSET_BASE_URL:-https://cdn.feilesg.com/}"
RUN_TYPECHECK="${RUN_TYPECHECK:-0}"
DRY_RUN="${DRY_RUN:-0}"
OSS_DELETE_EXTRA="${OSS_DELETE_EXTRA:-0}"
OSS_CACHE_CONTROL="${OSS_CACHE_CONTROL:-public, max-age=31536000, immutable}"

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

oss_destination() {
    local prefix
    prefix="$(normalize_prefix "$OSS_PREFIX")"

    if [[ -z "$prefix" ]]; then
        printf 'oss://%s/' "$OSS_BUCKET"
        return
    fi

    printf 'oss://%s/%s/' "$OSS_BUCKET" "$prefix"
}

build_member_h5() {
    cd "$FRONTEND_DIR"

    if [[ ! -d node_modules ]]; then
        npm ci
    fi

    if [[ "$RUN_TYPECHECK" == "1" ]]; then
        npm run typecheck
    fi

    VITE_ASSET_BASE_URL="$VITE_ASSET_BASE_URL" npx vite build
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
    local metadata_flags=()
    local snapshot_flags=()

    destination="$(oss_destination)"

    if [[ "$DRY_RUN" == "1" ]]; then
        dry_run_flags+=(--dry-run)
    fi

    if [[ "$OSS_DELETE_EXTRA" == "1" ]]; then
        delete_flags+=(--delete)
    fi

    if "$OSSUTIL_BIN" sync --help 2>&1 | grep -q -- '--snapshot-path'; then
        mkdir -p "$SNAPSHOT_DIR"
        snapshot_flags+=(--snapshot-path "$SNAPSHOT_DIR")
    else
        printf 'ossutil sync does not support --snapshot-path; continuing without local sync snapshots.\n'
    fi

    if "$OSSUTIL_BIN" sync --help 2>&1 | grep -q -- '--meta'; then
        metadata_flags+=(--meta "Cache-Control:$OSS_CACHE_CONTROL")
    else
        printf 'ossutil sync does not support --meta; configure Cache-Control on OSS/CDN if needed.\n'
    fi

    printf 'Syncing %s -> %s\n' "$ASSETS_DIR/" "$destination"

    "$OSSUTIL_BIN" sync "$ASSETS_DIR/" "$destination" \
        -f \
        "${snapshot_flags[@]}" \
        "${metadata_flags[@]}" \
        "${dry_run_flags[@]}" \
        "${delete_flags[@]}"
}

main() {
    [[ -n "$OSS_BUCKET" ]] || fail "Set OSS_BUCKET, for example: export OSS_BUCKET=your-bucket"
    [[ -n "$OSS_REGION" ]] || fail "Set OSS_REGION, for example: export OSS_REGION=oss-cn-hangzhou"
    [[ -n "${OSS_ACCESS_KEY_ID:-}" ]] || fail "Set OSS_ACCESS_KEY_ID in a host-local secret file"
    [[ -n "${OSS_ACCESS_KEY_SECRET:-}" ]] || fail "Set OSS_ACCESS_KEY_SECRET in a host-local secret file"

    export OSS_REGION

    if [[ -f "$OSS_ENV_FILE" ]]; then
        printf 'Loaded OSS env file: %s\n' "$OSS_ENV_FILE"
    fi

    require_command node
    require_command npm
    require_command "$OSSUTIL_BIN"

    build_member_h5
    verify_build
    sync_assets

    printf 'Done. Entry HTML stays on origin; only dist/assets is uploaded to OSS.\n'
}

main "$@"
