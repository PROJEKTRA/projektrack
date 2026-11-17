# Modules

Each Community module lives in its own folder here (`modules/<Key>`, for example `modules/Timesheets`), commercial modules in `ee/modules/<Key>`. The module registry in `app/Modules` discovers every `module.json`, validates it and boots valid modules in dependency order. See ADR-0002 and `docs/architecture/modules.md` for the manifest schema and the rules modules follow:

- A module talks to other modules only through domain events and contracts, never through their tables.
- Required layout: `module.json`, `<Key>ServiceProvider.php` (namespace `Modules\<Key>`), `Domain/`, `Http/`, `Database/`, `lang/`, `resources/js/`, `tests/`.
- An invalid manifest is rejected with a logged reason; modules that require it are rejected too.
