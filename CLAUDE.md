# Contributor guide — rocksoftware-cakephp-settings

A **CakePHP 5 plugin** (Composer package `makallio85/rocksoftware-cakephp-settings`; public
repository; CakePHP plugin `RockSoftware/Settings`) that gives an application one settings store
and one settings page. See `README.md` for what it does.

## Quick start

```bash
composer install
vendor/bin/phpunit
composer cs-check
composer stan
```

## Rules

1. **Pull requests target `dev`**, never `master`. Releases are tags on `dev` (`vX.Y.Z`) with a
   GitHub release.
2. **Declarations are code, values are data.** A setting exists because its owner declares it
   (`Settings::declare()`); the database holds only values
   (`rock_software_settings_values`) and their history (`rock_software_settings_changes`). Every
   write goes through `Settings::write()`, which adds the history row and clears the cache; never
   add a path that writes values without both.
3. **Access is the application's.** `RockSoftwareSettings.access` decides; none given means
   nobody, and nobody who is not signed in ever. Never add a default that lets someone in.
4. **No application knowledge.** The plugin knows no users table, no roles and no other plugin.
   Who changed a setting comes from the request's `identity` (`Service\Actor`). Other plugins and
   applications depend on this one, never the other way round.
5. **Naming.** Keys are `<owner>.<name>` in snake_case; yes/no keys and PHP names start with
   `is_`/`has_`/`can_`. One term per concept: a *declaration* (code), a *value* (a stored row), a
   *change* (a history row), a *section* (an owner's part of the page).
6. **Texts are translatable.** Every user-facing string goes through
   `__d('rock_software_settings', …)` and has a Finnish entry in
   `resources/locales/fi/rock_software_settings.po` in the same change (`TranslationsTest` fails
   otherwise). Developer-facing exceptions (a wrong declaration, an undeclared key) stay English.
7. **The page runs no script** and has no inline handlers: plain Bootstrap 5 markup that works
   inside the application's layout under `script-src 'self'`.
8. **Tests** run on SQLite. Fixtures ship in `src/TestSuite/Fixture` (list them in
   `SettingsFixtures::ALL`) and are empty, as on a fresh installation; each test saves what it
   needs. Test data is fictional (`*.example.test`, "Example App").
9. **Public repository.** No real company, application, person, host name, email address or
   phone number anywhere: code, tests, README, commits, issues, screenshots.
10. Do not commit `vendor/`, `composer.lock`, `tmp/` or `.phpunit.cache/`.
