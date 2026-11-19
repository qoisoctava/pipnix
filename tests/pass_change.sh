#!/usr/bin/env bash
# Forced password change (weak install password → must_change_password).
#
#   tests/pass_change.sh [--keep]
#
# Covers: install admin:admin, login redirect, all pages blocked until the
# password changes, weak/mismatched new passwords refused, strong one
# accepted, old password dead, admin reset re-arms the flag.
set -euo pipefail
cd "$(dirname "$0")/.."

MYSQL_HOST="${PN_MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${PN_MYSQL_PORT:-3307}"
MYSQL_USER="${PN_MYSQL_USER:-root}"
MYSQL_PASS="${PN_MYSQL_PASS:-}"
DB="pipnix_pwd"
PORT="${PN_PWD_PORT:-65189}"
KEEP=0
[[ "${1:-}" == "--keep" ]] && KEEP=1

mysql() {
  MYSQL_PWD="$MYSQL_PASS" command mysql --protocol=tcp \
    -h"$MYSQL_HOST" -P"$MYSQL_PORT" -u"$MYSQL_USER" "$@"
}
say() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail() { printf '\033[31mFAIL:\033[0m %s\n' "$*"; exit 1; }
ok()   { printf '\033[32mok\033[0m — %s\n' "$*"; }

B="http://127.0.0.1:$PORT"
JAR=/tmp/pn_pwd_admin.jar
JAR2=/tmp/pn_pwd_other.jar
SERVER_PID=""
cleanup() {
  [[ -n "$SERVER_PID" ]] && kill "$SERVER_PID" 2>/dev/null || true
  rm -f "$JAR" "$JAR2"
  if [[ $KEEP -eq 0 ]]; then
    rm -f app/config.php
    mysql -e "DROP DATABASE IF EXISTS \`$DB\`;" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

# token <jar> <path>
token() {
  curl -s -b "$1" -c "$1" "$B$2" \
    | sed -n 's/.*name="_csrf" value="\([a-f0-9]*\)".*/\1/p' | head -1
}

say "1. database + install with admin:admin"
mysql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4;"
cat > app/config.php <<PHP
<?php
return [
    'db' => ['host' => '$MYSQL_HOST', 'port' => $MYSQL_PORT, 'name' => '$DB',
             'user' => '$MYSQL_USER', 'pass' => '$MYSQL_PASS'],
    'timezone' => 'UTC',
    'python_bin' => '$(command -v python3)',
    'lock' => ['name' => 'pipnix_pwd', 'timeout' => 20],
    'tick' => ['max_launches_per_tick' => 4],
    'alerts' => ['enabled' => false],
];
PHP
php app/install.php --admin=admin:admin >/dev/null
FLAG=$(mysql -N -B -e "SELECT must_change_password FROM \`$DB\`.pn_user WHERE username='admin';")
[[ "$FLAG" == "1" ]] || fail "admin:admin was not flagged (flag=$FLAG)"
ok "install flags the weak password"

say "2. server"
php -S 127.0.0.1:"$PORT" -t public public/index.php >/tmp/pn_pwd_server.log 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 30); do
  curl -s -o /dev/null "$B/login" && break
  sleep 0.3
done
ok "listening on :$PORT"

say "3. login redirects to the password form; everything else is blocked"
T=$(token "$JAR" /login)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&username=admin&password=admin" "$B/login")
[[ "$LOC" == *"/account/password" ]] || fail "login did not redirect (got '$LOC')"
ok "login → /account/password"
for PAGE in / /dags /runs /logs /python /admin; do
  LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" "$B$PAGE")
  [[ "$LOC" == *"/account/password" ]] || fail "$PAGE was not blocked (got '$LOC')"
done
ok "all pages redirect while the flag is set"

say "4. weak / mismatched new passwords are refused"
T=$(token "$JAR" /account/password)
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&current_password=admin&new_password=password&confirm_password=password" \
  "$B/account/password"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" -c "$JAR" "$B/")
[[ "$LOC" == *"/account/password" ]] || fail "common password was accepted"
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&current_password=admin&new_password=Good-Pass-1&confirm_password=Other-Pass-2" \
  "$B/account/password"
[[ "$(mysql -N -B -e "SELECT must_change_password FROM \`$DB\`.pn_user WHERE username='admin';")" == "1" ]] \
  || fail "mismatch cleared the flag"
ok "weak + mismatched refused, flag intact"

say "5. strong password accepted, old one dead"
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&current_password=admin&new_password=Good-Pass-1&confirm_password=Good-Pass-1" \
  "$B/account/password"
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$B/")
[[ "$CODE" == "200" ]] || fail "dashboard not reachable after change ($CODE)"
[[ "$(mysql -N -B -e "SELECT must_change_password FROM \`$DB\`.pn_user WHERE username='admin';")" == "0" ]] \
  || fail "flag not cleared"
ok "change accepted, dashboard open"

rm -f "$JAR2"
T2=$(token "$JAR2" /login)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" -c "$JAR2" \
  -d "_csrf=$T2&username=admin&password=admin" "$B/login")
[[ -z "$LOC" ]] || fail "old password still works"
ok "old admin:admin rejected"
rm -f "$JAR2"
T2=$(token "$JAR2" /login)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" -c "$JAR2" \
  -d "_csrf=$T2&username=admin&password=Good-Pass-1" "$B/login")
[[ "$LOC" == "$B/" ]] || fail "new password login failed (got '$LOC')"
ok "new password works"

say "6. admin reset re-arms the flag for the target only"
T=$(token "$JAR" /admin)
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&username=op1&email=&password=Temp-Pass-9&role=viewer" "$B/admin/users/new"
OTHER_ID=$(mysql -N -B -e "SELECT id FROM \`$DB\`.pn_user WHERE username='op1';")
[[ -n "$OTHER_ID" ]] || fail "user op1 not created"
curl -s -o /dev/null -b "$JAR" -c "$JAR" \
  -d "_csrf=$T&password=Reset-Pass-9" "$B/admin/users/$OTHER_ID/password"
[[ "$(mysql -N -B -e "SELECT must_change_password FROM \`$DB\`.pn_user WHERE id=$OTHER_ID;")" == "1" ]] \
  || fail "reset did not flag op1"
ok "reset flags op1"
rm -f "$JAR2"
T2=$(token "$JAR2" /login)
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR2" -c "$JAR2" \
  -d "_csrf=$T2&username=op1&password=Reset-Pass-9" "$B/login")
[[ "$LOC" == *"/account/password" ]] || fail "op1 not forced (got '$LOC')"
ok "op1 is forced to pick a new password"

say "7. a strong install password is not flagged"
php app/install.php --admin=strong:Good-Pass-2 >/dev/null
[[ "$(mysql -N -B -e "SELECT must_change_password FROM \`$DB\`.pn_user WHERE username='strong';")" == "0" ]] \
  || fail "strong password was flagged"
ok "strong password install is not forced"

printf '\n\033[32m== PASSWORD CHANGE TESTS PASSED\033[0m\n'
