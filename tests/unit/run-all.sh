#!/usr/bin/env bash
# Runs every read-only check and prints one PASS/FAIL line per check.
# Usage: bash tests/unit/run-all.sh   (from the project root)
set -u
cd "$(dirname "$0")/../.." || exit 1
pass=0; fail=0
run() {
  local label="$1"; shift
  if "$@" > /tmp/abmm-check.$$ 2>&1; then
    echo "PASS  $label"; pass=$((pass+1))
  else
    echo "FAIL  $label"; tail -5 /tmp/abmm-check.$$ | sed 's/^/      /'; fail=$((fail+1))
  fi
}
php tests/unit/render-form-as.php super_admin > /dev/null 2>&1
run "family form cascade (bun)"            bun test tests/unit/form-cascade.test.js
run "city suggestions from tehsil (bun)"   bun test tests/unit/city-suggest.test.js
run "export headers, row and Anchal"        php tests/unit/export-print-check.php
run "surname search"                        php tests/unit/surname-search-check.php
run "family search filters"                 php tests/unit/family-filters-check.php
run "report filters"                        php tests/unit/report-filters-check.php
run "hanging data"                          php tests/unit/hanging-data-check.php
run "roles and permissions"                 php tests/unit/role-check.php
run "sub-admin area limits"                 php tests/unit/sub-admin-scope-check.php
run "staff API endpoints"                   php tests/unit/staff-api-check.php
run "state to Pradesh to Anchal mapping"    php tests/unit/geo-mapping-check.php
run "meeting save with file (rolled back)"  php tests/unit/meeting-save-check.php
run "admin pages load (no server errors)"   php tests/unit/admin-smoke-check.php super_admin state_admin district_operator
rm -f /tmp/abmm-check.$$
echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
