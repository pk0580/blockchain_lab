Run the test suite inside the project's Docker container.

Steps:
1. Check that `CLAUDE_PHP_CONTAINER` is set. If not, tell the user and stop.
2. Verify the container is running: `docker ps --format '{{.Names}}' | grep -x "$CLAUDE_PHP_CONTAINER"`. If not running, tell the user and stop.
3. Auto-detect the test runner (check host filesystem under `{APP_ROOT}/`):
   - `{APP_ROOT}/vendor/bin/pest` exists → use Pest.
     - Pest ≥ 3 (check `{APP_ROOT}/composer.lock` for `"name": "pestphp/pest"` version ≥ 3) or `{APP_ROOT}/vendor/bin/paratest` exists → add `--parallel`.
     - Command: `./vendor/bin/pest --bail [--parallel]`
   - `{APP_ROOT}/vendor/bin/phpunit` exists → `./vendor/bin/phpunit --stop-on-failure`
   - `{APP_ROOT}/artisan` exists → `php artisan test --stop-on-failure`
   - None found: tell the user and stop.

4. Append `$ARGUMENTS` to the command (e.g. `--filter=OrderTest`).

5. Execute (pass `$ARGUMENTS` as extra words — no shell wrapper to avoid injection):
   ```
   docker exec --workdir "${CLAUDE_CONTAINER_ROOT:-/var/www/html}" "$CLAUDE_PHP_CONTAINER" \
     <command> $ARGUMENTS
   ```

6. If all tests pass: output one line — `Tests passed.`
7. If any test fails: show the full output, identify the failing test(s), and fix them before responding to the user.
