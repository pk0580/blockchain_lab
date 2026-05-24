Run `composer audit` inside the project's Docker container to check for known security vulnerabilities in dependencies.

Steps:
1. Check that `CLAUDE_PHP_CONTAINER` is set. If not, tell the user and stop.
2. Verify the container is running: `docker ps --format '{{.Names}}' | grep -x "$CLAUDE_PHP_CONTAINER"`. If not running, tell the user and stop.
3. Run:
   ```
   docker exec --workdir "${CLAUDE_CONTAINER_ROOT:-/var/www/html}" "$CLAUDE_PHP_CONTAINER" \
     composer audit --format=plain
   ```
4. If exit 0: output one line — `Composer audit passed — no known vulnerabilities.`
5. If exit non-zero: show the full output.
   - For each reported CVE: describe the affected package, severity, and recommended action (upgrade version, replace package, or accept risk with justification).
   - Do not automatically upgrade without user confirmation; present findings and ask how to proceed.
