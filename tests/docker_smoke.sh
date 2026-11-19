#!/usr/bin/env bash
#
# Docker end-to-end smoke test.
#
#   tests/docker_smoke.sh [--keep]
#
# Requires a running Docker daemon. Builds the image, boots a fresh compose
# project (own database + volumes), checks that the first admin password is
# generated and printed, signs in, loads and runs a DAG, and verifies the
# per-attempt log. Everything is removed afterwards unless --keep is given.
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
export PIPNIX_ADMIN_PASS=          # exercise the generated-password path
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
for _ in $(seq 1 90); do
  dc logs app 2>/dev/null | grep -q 'starting Apache' && break
  sleep 2
done
dc logs app 2>/dev/null | grep -q 'starting Apache' || {
  dc logs app 2>&1 | tail -20
  fail "the app container did not finish booting"
}
ok "container booted"

say "2. first admin password was generated and printed once"
PASS=$(dc logs app 2>/dev/null | sed -n 's/^.*\[pipnix\]     password: \(.*\)$/\1/p' | head -1)
[[ -n "$PASS" ]] || fail "no generated password in the logs"
[[ "$(dc exec -T app stat -c '%a' app/config.php)" == "640" ]] \
  || fail "app/config.php should be 0640"
[[ "$(dc exec -T app stat -c '%U' app/config.php)" == "www-data" ]] \
  || fail "app/config.php should belong to www-data"
ok "generated admin password found; config.php is 0640 www-data"

say "3. web console is up and the password works"
for _ in $(seq 1 60); do
  [[ "$(curl -s -o /dev/null -w '%{http_code}' "$B/login" || true)" == "200" ]] && break
  sleep 2
done
rm -f "$JAR"
T=$(curl -s -c "$JAR" "$B/login" | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&username=admin&password=$PASS" "$B/login")
[[ "$LOC" == "$B/" ]] || fail "login did not redirect to the dashboard (got '$LOC')"
for PAGE in / /dags /runs /logs /python /admin /docs/dags; do
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$B$PAGE")
  [[ "$CODE" == "200" ]] || fail "$PAGE returned $CODE"
done
ok "login + every page"

say "4. the scheduler tick loads DAGs (supercronic, every minute)"
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

say "5. trigger → dispatch → success"
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

say "6. per-attempt logs were written by www-data"
dc exec -T -u www-data app sh -c \
  "grep -rq 'docker smoke ok' logs/runs" || fail "task output not found in logs/runs"
ok "task output is in logs/runs"

printf '\n\033[32m== DOCKER SMOKE PASSED\033[0m\n'
