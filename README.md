# Tegora website

OctoberCMS 4 site for Tegora (Moralt exterior doors, Romania).

- `themes/tegora`: pages, section partials (with the page text), CSS, JS, content blueprints and starting content
- `plugins/tegora/site`: lead emails, first-time content loader, post-deploy route
- `storage/app/media/site`: photos the client can replace from the back office

## Deploy

Pushing to `main` runs `.github/workflows/deploy.yml`. It runs Composer install, packs the release into one zip and uploads it over FTPS. It then calls `_deploy/extract.php` on the server to unpack it in batches, and finally calls `/_tegora/post-deploy` to run migrations, load first-time content and clear caches.

GitHub environment `codefor.ge ENV` needs `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_DIR` (ending in `/`) and `DEPLOY_TOKEN`.

The server needs its own `.env`, which is never deployed. `.env.staging.example` is the template, and `DEPLOY_TOKEN` must match the GitHub secret.

## Content rules

- **Page text lives in the theme.** Each page in `themes/tegora/pages/` is a list of section partials, found in `themes/tegora/partials/<page>/` (the home page uses `partials/home/`). Each section is one file of plain HTML with its text written in, so it can be copied out, edited and pasted back whole. The first line of each file names the page and section.
- **Repeating content lives in the back office** (Tailor, stored in the database): projects, the inspiration gallery, legal pages, company details ("Date firmă") and form submissions. "Produse (demo)" is not used by the site; it shows how product pages could be edited through forms. `partials/product/page.htm` is its template.
- `tegora:seed` only loads a blueprint's starting content while it has none, so changing a seed file does not change a live site.
- **Deploys overwrite theme and media files.** Every deploy unpacks all files in the release, so a partial or photo changed on the server is replaced by the git version. Bring server-side edits into git before deploying.

## Local preview

```bash
php -d extension=zip artisan tegora:seed
```

The local copy uses SQLite in `storage/database.sqlite` (not committed).
