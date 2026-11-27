# US Vesta – PHP edition

PHP 8.1+ / MySQL port of the original Django project (`vishaljsp/usvesta`).
Runs on any standard PHP hosting (Hostinger Business/Premium web hosting) with no build step
and no Composer packages.

## Layout

```
index.php            front controller (all requests go here via .htaccess)
app/core/            mini framework: template engine (Django template syntax), ORM, router, HTTP, auth
app/models.php       models – same tables/columns as the Django database
app/views/*.php      page & API handlers (ported from cloth_app/views.py)
app/routes.php       URL map (mirror of cloth_app/urls.py)
app/lib/             Delhivery shipping, .xlsx reader for bulk upload
templates/           the original Django templates (rendered unchanged)
static/, media/      assets and uploaded images
database/schema.sql  MySQL schema – created automatically on first run
```

## Configuration

Secrets are **never** stored in this repository. Copy `config.sample.php` to
`../usvesta-config.php` (one folder above the web root) and fill it in.

## First run

On the first request the app creates all tables. If `../usvesta-data.sql` exists next to the
config file and the store is empty, it is imported once and renamed to `usvesta-data.sql.imported`.

## Local development

```
php -S 127.0.0.1:8000 index.php
```
with a config that uses `'db' => ['driver' => 'sqlite', 'path' => '/path/to/db.sqlite3']`
(the original Django SQLite file works as-is).

## Deploys

Every push to the `main` branch deploys automatically once the repository is connected in
hPanel (Websites → Git / GitHub auto-deployment).
