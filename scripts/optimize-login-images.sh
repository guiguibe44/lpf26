#!/usr/bin/env bash
# Régénère WebP + JPEG (max 1200px) depuis public/images/login/_original/*.png
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$ROOT/public/images/login"
ORIG="$DIR/_original"

if ! command -v cwebp >/dev/null 2>&1; then
    echo "cwebp requis (brew install webp)" >&2
    exit 1
fi

mkdir -p "$ORIG"
for f in "$DIR"/*.png; do
    [[ -f "$f" ]] || continue
    base="$(basename "$f")"
    [[ -f "$ORIG/$base" ]] || cp "$f" "$ORIG/$base"
done

for f in "$ORIG"/*.png; do
    [[ -f "$f" ]] || continue
    name="$(basename "${f%.png}")"
    sips -Z 1200 "$f" --out "$DIR/$name.png" >/dev/null
    sips -s format jpeg -s formatOptions 82 "$DIR/$name.png" --out "$DIR/$name.jpg" >/dev/null
    cwebp -q 82 "$DIR/$name.png" -o "$DIR/$name.webp" >/dev/null
    echo "OK $name (webp + jpg)"
done

ls -lh "$DIR"/*.{webp,jpg} 2>/dev/null | awk '{print $5, $9}'
