#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; VERSION="$(php -r '$j=json_decode(file_get_contents($argv[1]),true);echo $j["version"];' "$ROOT/plugin.json")"; OUT="${1:-$(dirname "$ROOT")/release}"; mkdir -p "$OUT"; ZIP="$OUT/gaming-hub-panel-v${VERSION}.zip"; rm -f "$ZIP" "$ZIP.sha256"; (cd "$(dirname "$ROOT")" && zip -qr "$ZIP" "$(basename "$ROOT")" -x '*/vendor/*' '*/.git/*' '*/.phpunit.cache/*'); (cd "$OUT" && sha256sum "$(basename "$ZIP")" > "$(basename "$ZIP").sha256"); echo "$ZIP"
