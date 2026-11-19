#!/usr/bin/env bash
#
# Docker end-to-end smoke test.
#
#   tests/docker_smoke.sh [--keep]
#
# Requires a running Docker daemon. Builds the image, boots a fresh compose
# project (own database + volumes), checks the admin:admin convention and the
# forced password change, loads and runs a DAG, and verifies the per-attempt
# log. Everything is removed afterwards unless --keep is given.
set -euo pipefail
cd "$(dirname "$0")/.."

PROJECT="${PN_DOCKER_PROJECT:-pipnix_smoke}"
PORT="${PN_DOCKER_PORT:-18081}"
KEEP=0
[[ "${1:-}" == "--keep" ]] && KEEP=1

JAR=/tmp/pn_docker_smoke.jar
B="http://127.0.0.1:$PORT"
DAG_FILE=dags/docker_smoke.py

say() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail() { printf '\033[31mFAIL:\033[0m %s\n' "$*"; exit 1; }
ok()   { printf '\033[32mok\033[0m — %s\n' "$*"; }

command -v docker >/dev/null || fail "docker is not installed"
docker info >/dev/null 2>&1 || fail "the Docker daemon is not running"

export PIPNIX_PORT="$PORT"
export PIPNIX_DB_NAME=pipnix_smoke
export PIPNIX_DB_USER=pipnix
export PIPNIX_DB_PASS=smoke-db-pass
export PIPNIX_DB_ROOT_PASS=smoke-root-pass
export PIPNIX_ADMIN_USER=admin
export PIPNIX_ADMIN_PASS=          # exercise the admin:admin default
export PIPNIX_TIMEZONE=UTC

cleanup() {
  rm -f "$JAR" "$DAG_FILE"
  if [[ $KEEP -eq 0 ]]; then
    docker compose -p "$PROJECT" down -v --remove-orphans >/dev/null 2>&1 || true
  else
    printf '\nkept: docker compose -p %s logs -f app\n' "$PROJECT"
  fi
}
trap cleanup EXIT

dc() { docker compose -p "$PROJECT" "$@"; }

say "1. build and start a fresh stack"
rm -f "$DAG_FILE"
dc up -d --build >/dev/null
for _ in $(seq 1 150); do
  dc logs app 2>/dev/null | grep -q 'starting Apache' && break
  sleep 2
done
dc logs app 2>/dev/null | grep -q 'starting Apache' || {
  dc logs app 2>&1 | tail -20
  fail "the app container did not finish booting"
}
ok "container booted"

say "2. the first admin is admin:admin and is flagged as temporary"
[[ "$(dc exec -T app stat -c '%a' app/config.php)" == "640" ]] \
  || fail "app/config.php should be 0640"
[[ "$(dc exec -T app stat -c '%U' app/config.php)" == "www-data" ]] \
  || fail "app/config.php should belong to www-data"
FOUND=0
for _ in $(seq 1 15); do
  if dc logs app 2>/dev/null | grep -q "temporary password 'admin'"; then
    FOUND=1
    break
  fi
  sleep 2
done
[[ "$FOUND" = "1" ]] || fail "the boot log does not mention the temporary admin password"
ok "admin:admin created as a temporary password; config.php is 0640 www-data"

say "3. the console blocks everything until the password changes"
for _ in $(seq 1 60); do
  [[ "$(curl -s -o /dev/null -w '%{http_code}' "$B/login" || true)" == "200" ]] && break
  sleep 2
done
rm -f "$JAR"
T=$(curl -s -c "$JAR" "$B/login" | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&username=admin&password=admin" "$B/login")
[[ "$LOC" == "$B/account/password" ]] || fail "login with admin:admin went to '$LOC'"
for PAGE in / /dags /runs /logs /python /admin; do
  LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" "$B$PAGE")
  [[ "$LOC" == *"/account/password" ]] || fail "$PAGE was not blocked (got '$LOC')"
done

T=$(curl -s -b "$JAR" -c "$JAR" "$B/account/password" \
  | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&current_password=admin&new_password=Good-Pass-1&confirm_password=Good-Pass-1" \
  "$B/account/password")
[[ "$LOC" == "$B/" ]] || fail "password change did not land on the dashboard (got '$LOC')"
[[ "$(dc exec -T app php -r 'require "app/bootstrap.php"; echo (int) Db::one("SELECT must_change_password AS f FROM pn_user WHERE username = \"admin\"")["f"];')" == "0" ]] \
  || fail "must_change_password was not cleared"
ok "weak password blocked everywhere, then replaced"

say "4. web console pages are reachable after the change"
rm -f "$JAR"
T=$(curl -s -c "$JAR" "$B/login" | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&username=admin&password=Good-Pass-1" "$B/login")
[[ "$LOC" == "$B/" ]] || fail "login with the new password failed (got '$LOC')"
for PAGE in / /dags /runs /logs /python /admin /admin/updates /docs/dags; do
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$B$PAGE")
  [[ "$CODE" == "200" ]] || fail "$PAGE returned $CODE"
done
GUIDE=$(curl -s -b "$JAR" "$B/docs")
grep -q 'Persyaratan' <<<"$GUIDE" || fail "getting-started guide content missing"
CPANEL_GUIDE=$(curl -s -b "$JAR" "$B/docs/cpanel")
grep -q 'Deploying to cPanel' <<<"$CPANEL_GUIDE" || fail "cPanel guide content missing"
UPDATES=$(curl -s -b "$JAR" "$B/admin/updates")
grep -q '↻ Check now' <<<"$UPDATES" || fail "GitHub check button missing in Docker"
grep -q 'disabled>↻ Check now' <<<"$UPDATES" && fail "GitHub check should remain enabled in Docker"
grep -q 'Applying updates unavailable' <<<"$UPDATES" || fail "Docker apply restriction missing"
ok "login + every page"
ok "GitHub check stays enabled; file apply remains disabled in Docker"

say "5. the scheduler tick loads DAGs (supercronic, every minute)"
cat > "$DAG_FILE" <<'PY'
DAG = {
    "name": "docker_smoke",
    "schedule": "interval:30",
    "tasks": [
        {"name": "hello", "command": "python3 -c \"print('docker smoke ok')\"", "timeout": 30},
        {"name": "join", "command": "echo joined", "depends_on": ["hello"], "timeout": 30},
    ],
}
PY
dc exec -T -u www-data app php app/ops.php tick >/dev/null
dc exec -T -u www-data app php app/ops.php dags | grep -q docker_smoke \
  || fail "docker_smoke was not loaded"
ok "DAG loaded in the container"

say "6. trigger → dispatch → success"
dc exec -T -u www-data app php app/ops.php trigger --dag=docker_smoke >/dev/null
STATE=""
for _ in $(seq 1 30); do
  dc exec -T -u www-data app php app/scheduler.php >/dev/null 2>&1 || true
  STATE=$(dc exec -T -u www-data app php -r \
    'require "app/bootstrap.php"; $r = Db::one("SELECT state FROM pn_dag_run ORDER BY id DESC LIMIT 1"); echo $r["state"] ?? "none";')
  [[ "$STATE" == "success" || "$STATE" == "failed" ]] && break
  sleep 1
done
[[ "$STATE" == "success" ]] || fail "the run ended as '$STATE'"
ok "run succeeded"

say "7. per-attempt logs were written by www-data"
dc exec -T -u www-data app sh -c \
  "grep -rq 'docker smoke ok' logs/runs" || fail "task output not found in logs/runs"
ok "task output is in logs/runs"

printf '\n\033[32m== DOCKER SMOKE PASSED\033[0m\n'
