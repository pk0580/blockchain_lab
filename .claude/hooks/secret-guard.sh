#!/usr/bin/env bash
# PreToolUse hook for Write|Edit|Bash. Blocks the tool call when the
# new file path / content / bash command looks like it contains a
# hard-coded secret, .env file leak, or a checked-in credential.
#
# Errors are written to stderr; non-zero exit blocks the tool call.

set -euo pipefail

# shellcheck source=_lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"

payload=$(cat)

f=$(extract_file_path "$payload")

# 1. Hard block on obvious secret-looking files (Write|Edit only — for
#    Bash the file_path is empty so this falls through harmlessly).
case "$f" in
  *.env.example|*.env.sample|*.env.dist) ;; # Allow committed templates
  *.env|*.env.local|*.env.production|*.env.prod|*.env.staging|*.env.stage|*.env.development|*.env.dev|*.env.test|*.env.testing|*/id_rsa*|*/id_dsa*|*.pem|*.key|*credentials.json|*secrets.json)
    echo "secret-guard: refusing to write to '$f' — looks like a secret file." >&2
    exit 1
    ;;
esac

# 2. Pattern-based heuristics on the whole payload. Covers Write|Edit
#    content/new_string AND Bash command — catches echo "AKIA..." >> .env style leaks.
if printf '%s' "$payload" | grep -Eq \
     '(AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|AIza[0-9A-Za-z_-]{35}|sk_live_[0-9A-Za-z]{24,}|sk_test_[0-9A-Za-z]{24,}|rk_live_[0-9A-Za-z]{24,}|xox[baprs]-[0-9A-Za-z-]{10,}|xoxe[ap]-[0-9A-Za-z-]{10,}|ghp_[0-9A-Za-z]{36}|ghs_[0-9A-Za-z]{36}|gho_[0-9A-Za-z]{36}|github_pat_[0-9A-Za-z_]{82}|glpat-[0-9A-Za-z_-]{20,}|SG\.[A-Za-z0-9_-]{22}\.[A-Za-z0-9_-]{43}|hf_[A-Za-z0-9]{30,}|dp\.pt\.[A-Za-z0-9]{40,}|eyJ[A-Za-z0-9\-_]+\.eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+|-----BEGIN (RSA|EC|DSA|OPENSSH|PRIVATE) KEY-----)'
then
  echo "secret-guard: refusing — payload matches a known secret pattern." >&2
  exit 1
fi

exit 0
