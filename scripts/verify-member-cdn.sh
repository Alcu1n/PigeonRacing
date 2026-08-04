#!/usr/bin/env bash

# [IN]: Origin login HTML and CDN asset host / 源站登录页 HTML 与 CDN 资源域名
# [OUT]: Origin asset-reference and CDN edge-response verification / 源站资源引用与 CDN 边缘响应验证
# [POS]: Read-only member asset/CDN acceptance check / 只读会员资源/CDN 验收检查
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

set -euo pipefail

ORIGIN_URL="${ORIGIN_URL:-https://feilesaige.cn}"
CDN_URL="${CDN_URL:-https://cdn.feilesaige.cn}"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

ORIGIN_URL="${ORIGIN_URL%/}"
CDN_URL="${CDN_URL%/}"
HTTP_CDN_URL="http://${CDN_URL#https://}"

HTTP_STATUS="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 15 "${HTTP_CDN_URL}/")"
case "$HTTP_STATUS" in
    301|302|307|308) ;;
    *)
        echo "${HTTP_CDN_URL} 未跳转到 HTTPS，收到 HTTP ${HTTP_STATUS}。" >&2
        exit 1
        ;;
esac

curl --fail --silent --show-error --location --retry 2 \
    "${ORIGIN_URL}/login" \
    --output "${WORK_DIR}/login.html"

grep -oE "${CDN_URL//./\\.}/assets/[^\"' ]+\.(js|css)" "${WORK_DIR}/login.html" \
    | sort -u \
    > "${WORK_DIR}/assets.txt" || true

ASSET_COUNT="$(wc -l < "${WORK_DIR}/assets.txt" | tr -d ' ')"
if [[ "$ASSET_COUNT" -eq 0 ]]; then
    echo "未在 ${ORIGIN_URL}/login 中发现 ${CDN_URL}/assets/ 资源引用。" >&2
    exit 1
fi

echo "发现 ${ASSET_COUNT} 个 CDN 资源引用。"

while IFS= read -r asset_url; do
    asset_key="$(printf '%s' "$asset_url" | cksum | awk '{print $1}')"

    for pass in 1 2; do
        headers_file="${WORK_DIR}/headers-${asset_key}-${pass}.txt"
        curl --fail --silent --show-error --location --retry 2 \
            --dump-header "$headers_file" \
            --output /dev/null \
            "$asset_url"

        status="$(awk 'NR == 1 {print $2}' "$headers_file")"
        cache="$(awk 'BEGIN {IGNORECASE=1} /^x-cache:/ {$1=""; sub(/^ /, ""); print; exit}' "$headers_file")"
        age="$(awk 'BEGIN {IGNORECASE=1} /^age:/ {$1=""; sub(/^ /, ""); print; exit}' "$headers_file")"
        via="$(awk 'BEGIN {IGNORECASE=1} /^via:/ {$1=""; sub(/^ /, ""); print; exit}' "$headers_file")"

        printf '请求 %s：HTTP %s  %s\n' "$pass" "${status:-unknown}" "$asset_url"
        printf '  X-Cache: %s\n' "${cache:-未返回}"
        printf '  Age: %s\n' "${age:-未返回}"
        printf '  Via: %s\n' "${via:-未返回}"
    done
done < "${WORK_DIR}/assets.txt"

echo "CDN 资源返回成功；请以 X-Cache/Age/Via 等响应头确认节点命中状态。"
