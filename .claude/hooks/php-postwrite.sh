#!/usr/bin/env bash
# Docker-only PostToolUse hook for Write|Edit on .php files (WSL2 +
# Laravel application-root layout).
# Maps a host path under ${CLAUDE_SRC_PREFIX} to the corresponding
# path inside ${CLAUDE_PHP_CONTAINER} and runs Pint + php -l there.
# Silent on success; errors go to stderr so Claude sees them.
#
# Required env (set in .claude/settings.local.json):
#   CLAUDE_PHP_CONTAINER     — name of the running PHP container
# Optional env (single source of truth in .claude/settings.json):
#   CLAUDE_APP_ROOT          — application root directory relative to
#                              the repo root (default: src). To indicate
#                              "PHP lives at the repo root" set any of:
#                              "" (empty), ".", "./", or "/" — all are
#                              normalized to the repo root.
#   CLAUDE_SRC_PREFIX        — explicit host path mounted into the
#                              container; overrides CLAUDE_APP_ROOT
#                              (default: <project_root>/${CLAUDE_APP_ROOT}/)
#   CLAUDE_CONTAINER_ROOT    — container path that maps to SRC_PREFIX
#                              (default: /var/www/html)

set -euo pipefail

# shellcheck source=_lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"

project_root="$(git rev-parse --show-toplevel 2>/dev/null || (cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd))"

container="${CLAUDE_PHP_CONTAINER:-}"
app_root="${CLAUDE_APP_ROOT-src}"
container_root="${CLAUDE_CONTAINER_ROOT:-/var/www/html}"

# Normalize app_root: "", ".", "./", "/" all mean "PHP at the repo root".
case "$app_root" in
  ""|"."|"./"|"/") app_root="" ;;
esac

# Compute the host-side source prefix.
if [ -n "${CLAUDE_SRC_PREFIX-}" ]; then
  src_prefix="$CLAUDE_SRC_PREFIX"
elif [ -z "$app_root" ]; then
  src_prefix="${project_root}/"
else
  src_prefix="${project_root}/${app_root}/"
fi

payload=$(cat)
f=$(extract_file_path "$payload")

# Only handle PHP files.
case "$f" in
  *.php) ;;
  *) exit 0 ;;
esac

# Normalize both paths via realpath -m so trailing slashes, double slashes,
# and ./ segments collapse. -m allows non-existent components, which
# matters because the file is freshly written when the hook fires.
src_prefix_norm="$(realpath -m -- "$src_prefix" 2>/dev/null || printf '%s' "$src_prefix")"
src_prefix_norm="${src_prefix_norm%/}/"
f_norm="$(realpath -m -- "$f" 2>/dev/null || printf '%s' "$f")"

# Only handle files that are mounted into the container.
case "$f_norm" in
  "${src_prefix_norm}"*) rel="${f_norm#"${src_prefix_norm}"}" ;;
  *) exit 0 ;;
esac

# Skip silently if the container is missing or not running. Don't
# block the user's flow because their dev container was stopped.
if [ -z "$container" ] \
   || ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container"; then
  exit 0
fi

target="${container_root%/}/${rel}"

if ! out=$(docker exec --workdir "$container_root" "$container" \
            sh -c '[ -f ./vendor/bin/pint ] && ./vendor/bin/pint "$1" 2>&1; php -l "$1" 2>&1' \
            _ "$target" 2>&1); then
  echo "$out" >&2
  exit 1
fi

exit 0
