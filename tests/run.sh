#!/usr/bin/env bash
set -euo pipefail

# A private socket and data directory per run: no ambient database credentials or service.
work=$(mktemp -d /tmp/sidebar-relations.XXXXXXXX)
bin=$(pg_config --bindir)
cleanup() {
  if [[ -f "$work/db/postmaster.pid" ]]; then "$bin/pg_ctl" -D "$work/db" -m immediate stop >/dev/null; fi
  rm -rf -- "$work"
}
trap cleanup EXIT
"$bin/initdb" -D "$work/db" -U sidebar --locale=C -E UTF8 --auth-local=trust --auth-host=reject >/dev/null
"$bin/pg_ctl" -D "$work/db" -l "$work/postgres.log" -o "-k $work -c listen_addresses=''" -w start >/dev/null
"$bin/createdb" --host="$work" --username=sidebar sidebar_test

mkdir -p "$work/project/config"
cp tests/sidebar-relations.php "$work/project/config/"
key=$(php -r 'echo bin2hex(random_bytes(32));')
run() {
  env -i PATH="$PATH" HOME="$work" \
    CRAFT_BASE_PATH="$work/project" CRAFT_VENDOR_PATH="$PWD/vendor" CRAFT_ENVIRONMENT=testing \
    CRAFT_DB_DSN="pgsql:host=$work;dbname=sidebar_test" CRAFT_DB_USER=sidebar \
    CRAFT_SECURITY_KEY="$key" CRAFT_EDITION=pro CRAFT_ALLOW_ADMIN_CHANGES=true CRAFT_ALLOW_UPDATES=false \
    SIDEBAR_CONFIG="${2:-array}" php tests/integration.php "$1"
}
if ! run install >"$work/install.log"; then cat "$work/install.log" >&2; exit 1; fi
run seed
run admin
run editor
run editor typed
run guest
run invalid invalid
