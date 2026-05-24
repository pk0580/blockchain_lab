Run PHPStan inside the project's Docker container.

Steps:
1. Check that `CLAUDE_PHP_CONTAINER` is set. If not, tell the user and stop.
2. Verify the container is running: `docker ps --format '{{.Names}}' | grep -x "$CLAUDE_PHP_CONTAINER"`. If not running, tell the user and stop.
3. Check that a PHPStan config exists (`{APP_ROOT}/phpstan.neon`, `{APP_ROOT}/phpstan.neon.dist`, or `{APP_ROOT}/phpstan.dist.neon`). If none found, tell the user PHPStan is not configured and stop.
4. Run:
   ```
   docker exec --workdir "${CLAUDE_CONTAINER_ROOT:-/var/www/html}" "$CLAUDE_PHP_CONTAINER" \
     ./vendor/bin/phpstan analyse --no-progress --memory-limit="${CLAUDE_PHPSTAN_MEMORY:-512M}" $ARGUMENTS
   ```
   If the PHPStan config does not declare a `level:`, insert `--level="${CLAUDE_PHPSTAN_LEVEL:-8}"` before `$ARGUMENTS`.

5. If PHPStan exits 0: output one line — `PHPStan passed.`
6. If PHPStan fails: show the full output and fix every reported error before responding to the user.

Pass any `$ARGUMENTS` as extra PHPStan flags (e.g. `--paths={APP_ROOT}/app/Domain`).
