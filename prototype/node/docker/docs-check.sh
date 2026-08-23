#!/usr/bin/env bash
set -euo pipefail

# Renders every Mermaid block under docs/ with mermaid-cli, so a diagram that
# does not parse fails here rather than in front of a reader. Docker is the
# only thing this needs on the host.
#
# mermaid-cli writes its Chromium profile under /tmp and the image's own /tmp
# is too small for it, so /tmp is bound to a host directory too.

root="$(cd "$(dirname "$0")/.." && pwd)"
work="${DOCS_CHECK_DIR:-$(mktemp -d)}"
image="${MERMAID_IMAGE:-minlag/mermaid-cli}"

mkdir -p "$work/tmp"
# The image runs as its own user, which owns nothing the host just made.
chmod 777 "$work" "$work/tmp"

shopt -s nullglob

for doc in "$root"/docs/*.md; do
    awk -v out="$work" -v doc="$(basename "$doc" .md)" '
        /^```mermaid$/ {
            block += 1
            file = sprintf("%s/%s-%02d.mmd", out, doc, block)
            inside = 1
            next
        }
        /^```$/ && inside { inside = 0; next }
        inside { print > file }
    ' "$doc"
done

diagrams=("$work"/*.mmd)

if [ ${#diagrams[@]} -eq 0 ]; then
    echo "docs-check: no mermaid blocks found under docs/" >&2
    exit 1
fi

failed=0

for diagram in "${diagrams[@]}"; do
    name="$(basename "$diagram" .mmd)"

    if docker run --rm \
        -v "$work":/data \
        -v "$work/tmp":/tmp \
        "$image" -i "/data/$name.mmd" -o "/data/$name.svg" >/dev/null 2>"$work/$name.err"; then
        echo "ok   $name"
    else
        echo "FAIL $name"
        sed 's/^/     /' "$work/$name.err"
        failed=$((failed + 1))
    fi
done

echo "${#diagrams[@]} diagram(s) rendered, $failed failed."

exit $((failed > 0))
