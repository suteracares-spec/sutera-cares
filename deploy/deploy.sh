#!/bin/bash
# =====================================================================
# Deploys a pushed commit to the cPanel server. Runs ON the server,
# called by the post-receive hook in ~/repos/sutera-cares.git after
# every `git push cpanel main`, from a fresh export of that commit.
#
#   www.suteracares.org            -> ~/public_html
#   providers.suteracares.org      -> ~/providers.suteracares.org
#   providers.suteracares.org/portal
#       application                -> ~/portal_app          (outside the web root)
#       public files               -> ~/providers.suteracares.org/portal
#
# What it never touches: the portal's .env, vendor/ and storage/, the
# portal's index.php (it holds absolute paths for this server), and
# anything on the server that is not part of a site (cgi-bin,
# .well-known, mail, error logs).
#
# The portal goes into maintenance mode for the few seconds of the
# copy and the database update, and comes back even if a step fails.
# Before any database update, the database is backed up.
# =====================================================================
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHARITY="$HOME/public_html"
PROVIDER="$HOME/providers.suteracares.org"
PORTAL_WEB="$PROVIDER/portal"
PORTAL_APP="$HOME/portal_app"
BACKUPS="$HOME/deploy-backups/auto"
KEEP_BACKUPS=14

say() { echo "deploy: $*"; }

# Files, not directories, so rsync never deletes what the sites share
# the folder with. Permissions as the host expects them.
RSYNC=(rsync -rlt --checksum --chmod=D755,F644)

# ---- Charity site --------------------------------------------------------
say "charity site -> $CHARITY"
"${RSYNC[@]}" "$SRC"/{index.html,404.html,robots.txt,sitemap.xml,.htaccess} "$CHARITY"/
"${RSYNC[@]}" "$SRC"/css "$SRC"/js "$SRC"/img "$CHARITY"/

# ---- Provider site -------------------------------------------------------
say "provider site -> $PROVIDER"
"${RSYNC[@]}" "$SRC"/provider/{index.html,.htaccess} "$PROVIDER"/
"${RSYNC[@]}" "$SRC"/provider/css "$SRC"/provider/img "$PROVIDER"/

# ---- Portal --------------------------------------------------------------
cd "$PORTAL_APP"

# The server has no Composer, so vendor/ cannot be rebuilt here. A
# changed lock file means new packages that are not on the server:
# stop, rather than deploy code that would fatal on its first request.
if ! cmp -s "$SRC/portal/composer.lock" "$PORTAL_APP/composer.lock"; then
    say "STOPPED: portal/composer.lock changed, but the server cannot run Composer."
    say "Upload a matching vendor/ first; the two sites above were deployed."
    exit 1
fi

php artisan down --retry=15 >/dev/null
trap 'php artisan up >/dev/null; say "portal is back up"' EXIT

say "portal application -> $PORTAL_APP"
"${RSYNC[@]}" --delete \
    --exclude=/.env --exclude=/vendor/ --exclude=/storage/ --exclude=/public/ \
    --exclude=/tests/ --exclude=/bootstrap/cache/ --exclude=/node_modules/ \
    --exclude=error_log \
    "$SRC"/portal/ "$PORTAL_APP"/

say "portal public files -> $PORTAL_WEB"
"${RSYNC[@]}" --exclude=/index.php "$SRC"/portal/public/ "$PORTAL_WEB"/

if php artisan migrate:status --no-ansi 2>/dev/null | grep -q Pending; then
    mkdir -p "$BACKUPS" && chmod 700 "$BACKUPS"
    dump="$BACKUPS/database-$(date +%Y%m%d-%H%M%S).sql.gz"
    env_value() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
    DB_HOST="$(env_value DB_HOST || true)"
    # Credentials stay in the environment; they are never printed.
    MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump --single-transaction --no-tablespaces \
        -h "${DB_HOST:-localhost}" -u "$(env_value DB_USERNAME)" "$(env_value DB_DATABASE)" 2>/dev/null \
        | gzip > "$dump"
    chmod 600 "$dump"
    say "database backed up to $dump"
    ls -1t "$BACKUPS"/database-*.sql.gz | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm --

    php artisan migrate --force --no-ansi
fi

php artisan config:clear >/dev/null
php artisan route:clear >/dev/null
php artisan view:clear >/dev/null

say "done: $(git --git-dir="$HOME/repos/sutera-cares.git" log -1 --format='%h %s' main)"
