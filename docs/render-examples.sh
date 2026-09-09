#!/usr/bin/env bash
#
# Re-render the PNGs in docs/examples/ from docs/ui-components-examples.html,
# so the Markdown gallery GitHub shows stays in step with the kit.
#
# Needs Google Chrome (or set CHROME to another Chromium binary) and Python 3
# for the throw-away HTTP server. Run from anywhere:
#
#     docs/render-examples.sh
#
set -euo pipefail

CHROME="${CHROME:-$(command -v google-chrome || command -v google-chrome-stable || command -v chromium || command -v chromium-browser)}"
PORT="${PORT:-8791}"
WIDTH=1400
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/docs/examples"
SECTIONS=(metrics charts data actions skills spec)

mkdir -p "$OUT"

python3 -m http.server "$PORT" --bind 127.0.0.1 --directory "$ROOT" >/dev/null 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 1

for section in "${SECTIONS[@]}"; do
    url="http://127.0.0.1:$PORT/docs/ui-components-examples.html?section=$section"

    # First pass: let the page lay out, read back its height.
    height=$("$CHROME" --headless=new --disable-gpu --hide-scrollbars --no-sandbox \
        --window-size="$WIDTH,800" --virtual-time-budget=6000 --dump-dom "$url" 2>/dev/null \
        | grep -o 'data-height="[0-9]*"' | grep -o '[0-9]*' | head -n1)
    height="${height:-1200}"

    # Second pass: the actual capture at that height.
    "$CHROME" --headless=new --disable-gpu --hide-scrollbars --no-sandbox \
        --window-size="$WIDTH,$height" --virtual-time-budget=6000 \
        --screenshot="$OUT/$section.png" "$url" >/dev/null 2>&1

    echo "$section: ${WIDTH}x${height} -> docs/examples/$section.png"
done
