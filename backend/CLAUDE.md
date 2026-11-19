# CyberCompass API (Laravel 13, PHP 8.5)

PHP, Composer and MySQL run **only in Docker**; nothing PHP-related is installed on the host. Do not install PHP locally.

## Commands (run from the repository root)

| Task | Command |
|---|---|
| Start the stack | `docker compose up -d` |
| Artisan | `docker compose exec api php artisan <command>` |
| Tests (Pest, MySQL `cybercompass_testing`) | `docker compose exec api php artisan test` |
| Code style | `docker compose exec -u root api ./vendor/bin/pint` |
| Add a package / generate files | `docker compose exec -u root api composer require <pkg>` / `... php artisan make:<thing>` |
| OpenAPI (local only) | http://localhost:8000/docs/api |

`-u root` is needed for commands that write source files. The bind-mounted Windows tree appears root-owned inside the container. `storage/` and `bootstrap/cache` were made world-writable once, so the runtime user (`www-data`) can write there. `vendor/` lives in the `api-vendor` volume for speed, so the host copy, if any, may be stale.

## Architecture rules

Read `../docs/ARCHITECTURE.md` and `../docs/API_SPECIFICATION.md` first.

- Code is grouped by business module: `app/Modules/<Module>/{Http/Controllers,Http/Requests,Http/Resources,Actions,Services,Policies}` and `routes.php`. Add the module to the list in `routes/api.php`. Eloquent models stay in `app/Models`.
- Controllers stay thin: a Form Request goes in, one Action or Service is called, a Resource comes out. Multi-row writes go in `DB::transaction`.
- Responses use `{ data }`. Errors are rendered centrally by `App\Support\Http\ApiExceptionRenderer`, so never hand-build error JSON.
- Another user's record returns 404, never 403. Answer keys are never serialized before submission.
- Laravel 13 conventions: model metadata via attributes (`#[Fillable]`, `#[Hidden]`), and `Model::shouldBeStrict()` is on outside production.
- Every endpoint gets Pest feature tests (happy path, validation, authorization).
