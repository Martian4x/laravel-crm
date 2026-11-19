#!/usr/bin/env bash
#
# Import the old cPanel CRM database into az1 and bring its schema forward.
#
# The old instance at customers.azimio.co.tz runs this same application at
# roughly commit 9e769175: it has Phase 7.1/7.3 and none of Phase 8. The
# delta to the deployed code is 12 migrations and every one of them is
# additive (8 CREATE TABLE, 4 ADD COLUMN) -- no drops, renames or data
# transformations. So the whole migration is: import the dump as-is, then
# let `artisan migrate` apply the 12.
#
# 2026_11_19_000002 backfills existing tenants with activated_at =
# created_at and marks existing users verified, which is exactly the
# situation a real import creates. Without it every migrated user would
# land on the "verify your email" wall and every tenant would read as
# pending.
#
# Run ON az1, as root:
#   bin/migrate-from-cpanel.sh --dump /root/import/customers.sql.gz \
#                              [--storage /root/import/storage-app.tar.gz] \
#                              [--yes]
#
# Without --yes it does everything except write: sanity-checks the dump,
# reports what it would strip and what it would import, and stops.
#
set -euo pipefail

# Overridable so the script can be rehearsed against a scratch database
# before it is pointed at the real one. The defaults are production.
DB_NAME="${DB_NAME:-azimio_clients}"
DB_USER="${DB_USER:-azimio_clients}"
DB_PASS_FILE="${DB_PASS_FILE:-/root/.clients-db-pass}"
APP_DIR="${APP_DIR:-/var/www/clients/current}"
SHARED_DIR="${SHARED_DIR:-/var/www/clients/shared}"
BACKUP_DIR="${BACKUP_DIR:-/root/db-backups}"
RUN_USER="${RUN_USER:-clients}"

DUMP=""
STORAGE=""
CONFIRM="no"

while [ $# -gt 0 ]; do
    case "$1" in
        --dump)    DUMP="$2";    shift 2 ;;
        --storage) STORAGE="$2"; shift 2 ;;
        --yes)     CONFIRM="yes"; shift ;;
        *) echo "unknown argument: $1" >&2; exit 2 ;;
    esac
done

[ -n "$DUMP" ] || { echo "--dump is required" >&2; exit 2; }
[ -f "$DUMP" ]  || { echo "no such dump: $DUMP" >&2; exit 2; }
[ -f "$DB_PASS_FILE" ] || { echo "missing $DB_PASS_FILE" >&2; exit 2; }

PASS="$(cat "$DB_PASS_FILE")"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
chmod 700 "$WORK"

mysql_q() { mysql -u "$DB_USER" -p"$PASS" "$DB_NAME" -N -B -e "$1"; }

say() { printf '\n== %s\n' "$1"; }

# ---------------------------------------------------------------------------
# 1. Decompress and sanitise.
#
# A cPanel/phpMyAdmin dump routinely carries three things that would either
# fail or do damage here:
#
#   CREATE DATABASE / USE <olddb>  -- writes into the WRONG database, or
#                                     creates one nobody manages
#   DEFINER=`cpaneluser`@`localhost` -- that MySQL account does not exist on
#                                     az1, so views and triggers fail to load
#   SQL SECURITY DEFINER            -- same problem
#
# They are removed into a cleaned copy rather than ignored, so what actually
# gets imported is a file that can be read and kept.
# ---------------------------------------------------------------------------
say "Reading $DUMP"

RAW="$WORK/raw.sql"
case "$DUMP" in
    *.gz)  gunzip -c "$DUMP" > "$RAW" ;;
    *.zip) unzip -p "$DUMP" > "$RAW" ;;
    *)     cp "$DUMP" "$RAW" ;;
esac

RAW_BYTES=$(wc -c < "$RAW")
[ "$RAW_BYTES" -gt 1000 ] || { echo "dump is only $RAW_BYTES bytes -- that is not a database" >&2; exit 1; }
echo "   uncompressed: $(numfmt --to=iec "$RAW_BYTES" 2>/dev/null || echo "$RAW_BYTES bytes")"

STRIPPED_DB=$(grep -ciE '^(CREATE DATABASE|USE )' "$RAW" || true)
STRIPPED_DEF=$(grep -ci 'DEFINER=' "$RAW" || true)
echo "   stripping $STRIPPED_DB database-selection line(s), $STRIPPED_DEF DEFINER clause(s)"

CLEAN="$WORK/clean.sql"
sed -E \
    -e '/^CREATE DATABASE/d' \
    -e '/^USE `/d' \
    -e 's/DEFINER=`[^`]*`@`[^`]*`//g' \
    -e 's/\/\*!50013 SQL SECURITY DEFINER \*\///g' \
    -e 's/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g' \
    "$RAW" > "$CLEAN"

# ---------------------------------------------------------------------------
# 2. Confirm it is this application's database and not something else.
#    Importing the wrong dump over production is the expensive mistake here,
#    so refuse rather than guess.
# ---------------------------------------------------------------------------
say "Checking the dump is an AzimioCustomers database"

MISSING=""
for t in tenants users persons organizations leads migrations; do
    grep -qE "CREATE TABLE [\`]?${t}[\`]?" "$CLEAN" || MISSING="$MISSING $t"
done
[ -z "$MISSING" ] || { echo "dump is missing expected table(s):$MISSING" >&2; exit 1; }
echo "   found tenants, users, persons, organizations, leads, migrations"

# The old instance must not already carry the Phase 8 tables; if it does,
# this is not the instance we fingerprinted and the 12-migration assumption
# is wrong.
# NB: the backtick is written as a bracket expression, not as \` -- GNU grep
# reads \` in an ERE as a start-of-buffer anchor, so the pattern would never
# match and this guard would silently pass everything.
if grep -qE "CREATE TABLE [\`]?sms_(messages|batches|credit_ledger)[\`]?" "$CLEAN"; then
    echo "dump already contains sms_* tables -- source is newer than expected." >&2
    echo "Re-check the schema delta before importing." >&2
    exit 1
fi
echo "   no sms_* tables (as expected for the old instance)"

SRC_MIGRATIONS=$(grep -c "INSERT INTO \`migrations\`" "$CLEAN" || true)
echo "   migrations table present in $SRC_MIGRATIONS insert statement(s)"

if [ "$CONFIRM" != "yes" ]; then
    say "DRY RUN -- nothing written"
    echo "The dump checks out. Re-run with --yes to import it."
    exit 0
fi

# ---------------------------------------------------------------------------
# 3. Back up what is there now. Always, even though az1 is near-empty:
#    the one thing worse than a failed import is a failed import with no
#    way back.
# ---------------------------------------------------------------------------
say "Backing up the current $DB_NAME"

mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
TS=$(date +%Y%m%d%H%M%S)
BACKUP="$BACKUP_DIR/${DB_NAME}-before-import-$TS.sql.gz"
mysqldump --single-transaction --routines --triggers --events \
    -u "$DB_USER" -p"$PASS" "$DB_NAME" | gzip > "$BACKUP"
chmod 600 "$BACKUP"
echo "   $BACKUP ($(du -h "$BACKUP" | cut -f1))"

# ---------------------------------------------------------------------------
# 4. Take the site down. A request served mid-import sees half a schema.
# ---------------------------------------------------------------------------
say "Putting the site into maintenance mode"
sudo -u "$RUN_USER" php "$APP_DIR/artisan" down --render="errors::503" >/dev/null 2>&1 || \
    sudo -u "$RUN_USER" php "$APP_DIR/artisan" down >/dev/null
systemctl stop clients-queue.service
echo "   maintenance mode on, queue worker stopped"

restore_site() {
    systemctl start clients-queue.service || true
    sudo -u "$RUN_USER" php "$APP_DIR/artisan" up >/dev/null 2>&1 || true
}

# Before the import starts, a failure should just put the site back. Once the
# tables are being replaced it must NOT: serving a half-imported schema is
# worse than staying in maintenance mode. IMPORT_STARTED flips at that point
# and the handler then leaves the site down and says how to restore.
IMPORT_STARTED="no"

on_exit() {
    status=$?
    rm -rf "$WORK"
    if [ "$status" -ne 0 ] && [ "$IMPORT_STARTED" = "yes" ]; then
        echo >&2
        echo "FAILED mid-import. The site is LEFT IN MAINTENANCE MODE on purpose." >&2
        echo "Restore with:" >&2
        echo "  gunzip -c $BACKUP | mysql -u $DB_USER -p\"\$(cat $DB_PASS_FILE)\" $DB_NAME" >&2
        echo "  sudo -u $RUN_USER php $APP_DIR/artisan up" >&2
        echo "  systemctl start clients-queue.service" >&2
        exit "$status"
    fi
    restore_site
    exit "$status"
}
trap on_exit EXIT

# ---------------------------------------------------------------------------
# 5. Replace the schema wholesale.
#
#    DROP + CREATE rather than importing over the top: the existing database
#    is a fresh install whose `migrations` rows would otherwise collide with
#    the dump's, and a half-merged migrations table is how you get a
#    migration that runs twice.
# ---------------------------------------------------------------------------
say "Importing"
IMPORT_STARTED="yes"

# Drop every table rather than the database itself: DROP DATABASE needs a
# privilege the application user is not granted, and recreating the database
# would also discard its grants.
mysql_q "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;')
         FROM information_schema.tables WHERE table_schema = '$DB_NAME'" > "$WORK/drop.sql"

{ echo 'SET FOREIGN_KEY_CHECKS=0;'; cat "$WORK/drop.sql"; echo 'SET FOREIGN_KEY_CHECKS=1;'; } \
    | mysql -u "$DB_USER" -p"$PASS" "$DB_NAME"

REMAINING=$(mysql_q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
[ "$REMAINING" = "0" ] || { echo "$REMAINING table(s) survived the drop -- aborting" >&2; exit 1; }

mysql -u "$DB_USER" -p"$PASS" "$DB_NAME" < "$CLEAN"
echo "   imported"

count() { mysql_q "SELECT COUNT(*) FROM \`$1\`" 2>/dev/null || echo "-"; }

say "Row counts as imported"
BEFORE=""
for t in tenants users persons organizations leads activities quotes products migrations; do
    n=$(count "$t")
    BEFORE="$BEFORE$t=$n "
    printf '   %-16s %s\n' "$t" "$n"
done

TENANTS=$(count tenants)
USERS=$(count users)
[ "$TENANTS" != "0" ] || { echo "imported 0 tenants -- refusing to continue" >&2; exit 1; }
[ "$USERS" != "0" ]   || { echo "imported 0 users -- refusing to continue" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 6. Bring the schema forward. This is where the 12 additive migrations land.
# ---------------------------------------------------------------------------
say "Applying migrations"
sudo -u "$RUN_USER" php "$APP_DIR/artisan" migrate --force

# ---------------------------------------------------------------------------
# 7. Verify the migrations added and changed nothing else.
#    Every one of the 12 is additive, so every row count above must be
#    identical afterwards. If one moved, a migration did something it should
#    not have and the backup from step 3 is the way back.
# ---------------------------------------------------------------------------
say "Verifying nothing was lost"
DRIFT=""
for pair in $BEFORE; do
    t="${pair%%=*}"; was="${pair##*=}"
    now=$(count "$t")
    if [ "$t" != "migrations" ] && [ "$was" != "$now" ]; then
        DRIFT="$DRIFT $t($was->$now)"
    fi
    printf '   %-16s %s -> %s\n' "$t" "$was" "$now"
done

if [ -n "$DRIFT" ]; then
    echo >&2
    echo "ROW COUNTS CHANGED:$DRIFT" >&2
    echo "Restore with: gunzip -c $BACKUP | mysql -u $DB_USER -p<pass> $DB_NAME" >&2
    exit 1
fi
echo "   all counts unchanged"

say "Checking the Phase 8 tables arrived"
for t in sms_sender_profiles sms_credit_ledger sms_messages sms_batches sms_templates sms_opt_outs; do
    printf '   %-22s %s\n' "$t" "$(count "$t")"
done

# The backfill in 2026_11_19_000002 is what keeps migrated tenants and users
# out of the pending/unverified state. Confirm it actually ran.
say "Checking the activation backfill"
printf '   tenants with activated_at:   %s / %s\n' "$(mysql_q 'SELECT COUNT(*) FROM tenants WHERE activated_at IS NOT NULL')" "$TENANTS"
printf '   users with email_verified_at: %s / %s\n' "$(mysql_q 'SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL')" "$USERS"

# ---------------------------------------------------------------------------
# 8. Uploaded files.
# ---------------------------------------------------------------------------
if [ -n "$STORAGE" ]; then
    say "Restoring uploaded files"
    [ -f "$STORAGE" ] || { echo "no such archive: $STORAGE" >&2; exit 1; }
    # Into its own directory first: an archive from elsewhere is untrusted
    # input and may contain paths that escape the target.
    EX="$WORK/storage"; mkdir -p "$EX"
    tar -xzf "$STORAGE" -C "$EX"
    # The archive may be rooted at storage/ or at app/ depending on how it
    # was made, so find the app/ directory rather than assuming.
    SRC="$EX"
    if [ -d "$EX/storage/app" ]; then
        SRC="$EX/storage"
    elif [ ! -d "$EX/app" ]; then
        echo "archive contains neither app/ nor storage/app/" >&2
        exit 1
    fi
    cp -a "$SRC/app/." "$SHARED_DIR/storage/app/"
    chown -R "$RUN_USER:$RUN_USER" "$SHARED_DIR/storage"
    echo "   $(du -sh "$SHARED_DIR/storage/app" | cut -f1) in shared/storage/app"
fi

# ---------------------------------------------------------------------------
# 9. Clear anything cached against the old schema, and drop sessions:
#    az1's APP_KEY differs from the old instance's, so inherited session
#    payloads cannot be decrypted. Users log in again; password hashes are
#    bcrypt and unaffected.
# ---------------------------------------------------------------------------
say "Clearing caches and stale sessions"
mysql_q "TRUNCATE TABLE sessions" 2>/dev/null && echo "   sessions truncated" || echo "   no sessions table (file driver)"
mysql_q "TRUNCATE TABLE cache" 2>/dev/null || true
for c in cache:clear config:cache route:cache view:cache; do
    sudo -u "$RUN_USER" php "$APP_DIR/artisan" "$c" >/dev/null && echo "   $c"
done

say "Bringing the site back up"
IMPORT_STARTED="no"
restore_site
sleep 2
CODE=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: clients.azimio.co.tz' http://127.0.0.1/admin/login || echo 000)
echo "   /admin/login -> $CODE"
[ "$CODE" = "200" ] || { echo "site is not serving 200 -- investigate before announcing" >&2; exit 1; }

say "Done"
cat <<'NOTE'
   Imported, migrated and verified. The site is back up.

   DNS is deliberately untouched: customers.azimio.co.tz still points at
   the old cPanel host. Check the migrated data on az1 first (log in via
   clients.azimio.co.tz), then repoint the DNS as the last step.

   Leave the old cPanel instance running until the cutover is confirmed.
NOTE
