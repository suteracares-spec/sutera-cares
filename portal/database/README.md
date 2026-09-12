# Database

**The migrations in `migrations/` are the only definition of the schema.**

There used to be a hand-written `schema.sql` here for loading via
phpMyAdmin, because the server has no SSH and so cannot run `artisan`.
It drifted from the migrations within a day — it used a `password_hash`
column where Laravel expects `password`, and it never created the
`sessions`, `cache` or `jobs` tables that the database session, cache
and queue drivers need. Loading it produced a database that looked
complete and could not log anyone in.

Two files describing one structure will always drift. There is now one.

## Building the database on the server

Without SSH, run the migrations through the web server instead:
upload `run-migrations.php` to the site folder, open it once, delete it.
It boots the application, runs `migrate:fresh` and seeds the service
catalogue, and refuses to run if the `patients` table already holds
rows.

With SSH or cPanel Terminal, just use artisan:

    cd ~/portal_app && php artisan migrate --force
