# Tegora website

OctoberCMS 4 site for Tegora (Moralt exterior doors, Romania).

- `themes/tegora`: templates, CSS, JS, content blueprints and starting content
- `plugins/tegora/site`: lead emails, first-time content loader, post-deploy route
- `storage/app/media/site`: photos the client can replace from the back office

## Deploy

Pushing to `main` runs `.github/workflows/deploy.yml`. It runs Composer install, packs the release into one zip and uploads it over FTPS. It then calls `_deploy/extract.php` on the server to unpack it in batches, and finally calls `/_tegora/post-deploy` to run migrations, load first-time content and clear caches.

GitHub environment `codefor.ge ENV` needs `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_DIR` (ending in `/`) and `DEPLOY_TOKEN`.

The server needs its own `.env`, which is never deployed. `.env.staging.example` is the template, and `DEPLOY_TOKEN` must match the GitHub secret.

## Content rules

- Text and photos are edited in the back office and live in the database, so a deploy never overwrites them.
- `tegora:seed` only loads a blueprint's starting content while it has none, so changing a seed file does not change a live site. Change content in the back office instead.
- Theme files come from git. Do not edit them on the server: the next deploy overwrites them.

## Local preview

```bash
php -d extension=zip artisan tegora:seed
```

The local copy uses SQLite in `storage/database.sqlite` (not committed).
