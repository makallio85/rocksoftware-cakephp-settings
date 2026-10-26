# rocksoftware-cakephp-settings

A CakePHP 5 plugin that gives an application one settings store and one settings page.

- **Declarations are code.** The owner of a setting — this plugin, another plugin or the
  application — declares it in code: its key, type, label, help text, default and whether it is
  required.
- **Values are data.** A saved value is a database row with who changed it last and when; every
  change is kept in a history.
- **One way to read.** `Settings::get('application.name')` returns the stored value, else the
  declared default. Values are cached and the cache is cleared on save, so it is cheap enough for
  every request.
- **One page.** `/settings` shows one section per owner, each with its own form, Save and
  "Last changed … by …".

| | Name |
|---|---|
| Composer package | `makallio85/rocksoftware-cakephp-settings` |
| CakePHP plugin, namespace | `RockSoftware/Settings`, `RockSoftware\Settings\` |
| Routes | `/settings`, `/settings/<owner>` (the path is configurable) |
| Configuration, events | `RockSoftwareSettings.*` |
| Tables | `rock_software_settings_values`, `rock_software_settings_changes` |
| Translation domain | `rock_software_settings` (English msgids, Finnish `.po`) |

## Installing in an application

1. **Composer.** Run `composer require makallio85/rocksoftware-cakephp-settings:^1.0`.
2. **Plugin.** Add `$this->addPlugin(\RockSoftware\Settings\SettingsPlugin::class);` to
   `Application::bootstrap()`.
3. **Migrations.** Run `bin/cake migrations migrate -p RockSoftware/Settings` in the deploy
   entrypoint, and migrate the plugin in `tests/bootstrap.php` too. Until it has run, every
   setting reads as its default.
4. **Access.** In `config/app.php`, say who may open and save the settings page. Without it,
   nobody may:

   ```php
   'RockSoftwareSettings' => [
       'access' => fn($identity, $request): bool => in_array('admin', (array)$identity['roles'], true),
   ],
   ```

5. **Menu.** Link the page from the application's own Settings menu group, as any plugin page:

   ```php
   ['title' => __('Application'), 'url' => ['plugin' => 'RockSoftware/Settings', 'controller' => 'Settings', 'action' => 'section', 'application']],
   ['title' => __('All settings'), 'url' => ['plugin' => 'RockSoftware/Settings', 'controller' => 'Settings', 'action' => 'index']],
   ```

The page's controller extends the application's `App\Controller\AppController`, so it renders in
the application's layout, with its menu and its own authentication. The access callback is asked
after that authentication has run: a signed-out visitor is sent to sign in as on any other page,
and nobody who is not signed in ever gets in.

### Configuration

Every key has a default in `SettingsPlugin::DEFAULTS`. Override them under `RockSoftwareSettings`
in `config/app.php`. None of them is a setting on the page.

| Key | Default | |
|---|---|---|
| `access` | `null` | Who may open and save the page: `fn($identity, $request): bool`. Null means nobody |
| `path` | `'/settings'` | Where the page is |
| `cache` | `'default'` | The cache config holding the stored values |
| `breadcrumbParent` | `null` | For an application whose pages write their breadcrumb in the template: the page above, a title or `['title' => …, 'url' => …]` |
| `timezone` | `null` | The time zone of "Last changed"; null for `Ui.displayTimezone`, else `App.displayTimezone`, else Europe/Helsinki |
| `locale` | `null` | The language of the plugin's texts when it differs from the application's |

## Built-in settings

Section **Application** (`application`):

| Key | Type | Default | |
|---|---|---|---|
| `application.name` | text, required | `'Application'` | Application name: the top bar, the sign-in card, after every page title |
| `application.organization_name` | text | `''` | Line under the name: under the name on the sign-in card only |

`SettingsPlugin::APPLICATION_NAME` and `SettingsPlugin::APPLICATION_ORGANIZATION_NAME` name them.

An application that must show its own name from its first deploy seeds it in a migration of its
own, after the plugin's:

```php
use RockSoftware\Settings\Settings;

public function up(): void
{
    Settings::set('application.name', 'Example App');
}
```

## Reading a setting

```php
use RockSoftware\Settings\Settings;

$name = Settings::get('application.name');   // the stored value, else the default
```

- The value has the type's PHP type: `string` for text, `bool` for yes/no, `int|null` for a whole
  number, `string|null` (a choice's value) for a choice.
- Reading a key nobody declared throws `InvalidArgumentException`: it is a typo or a missing
  declaration, never a missing value.
- Every stored value is read with one query, kept in the cache config `cache`, and within one
  process for a few seconds more (`Settings::MEMO_SECONDS`). Saving clears both. A value changed
  directly in the database shows once `Settings::clearCache()` runs or the cache expires.
- A stored value the declaration no longer allows (a choice removed, a type changed) reads as the
  default.

## Declaring settings

The owner declares its settings in its `bootstrap()` — a plugin in its `Plugin::bootstrap()`, the
application in `Application::bootstrap()`. A key is `<owner>.<name>`, both in snake_case; the owner
is the section of the page and the name is the form field.

```php
use RockSoftware\Settings\SettingDeclaration;
use RockSoftware\Settings\Settings;

Settings::declareSection(
    'reports',
    fn(): string => __('Reports'),
    fn(): string => __('How reports look.'),
    order: 50,                                    // lower comes first; Application is 0
);

Settings::declare(SettingDeclaration::text(
    'reports.title',
    label: fn(): string => __('Title'),
    help: fn(): string => __('Printed at the top of every report.'),
    default: 'Report',
    isRequired: true,
    maxLength: 100,
));
Settings::declare(SettingDeclaration::longText('reports.footer', label: fn(): string => __('Footer')));
Settings::declare(SettingDeclaration::yesNo('reports.is_footer_shown', label: fn(): string => __('Show the footer'), default: true));
Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', label: fn(): string => __('Rows per page'), default: 50, min: 10, max: 500));
Settings::declare(SettingDeclaration::choice(
    'reports.paper',
    label: fn(): string => __('Paper'),
    choices: ['a4' => fn(): string => __('A4'), 'letter' => fn(): string => __('Letter')],
    default: 'a4',
    isRequired: true,
));
```

- **Texts as closures.** A label, help text, section title or choice label is a string or a
  closure returning one. Give a closure for a translated text: it runs when the page is shown, in
  the request's language.
- **Yes/no keys** are named as the question they answer: `is_…`, `has_…`, `can_…`.
- **Choice values** are non-numeric strings (`a4`, not `1`).
- An owner without `declareSection()` gets a section titled by its name.
- Declaring a key again replaces its declaration.
- A default that is not of the type, or a choice default not in the list, throws when declared.

## The settings page

| Route | |
|---|---|
| `GET /settings` | Every section, each with its own form and Save |
| `GET /settings/<owner>` | One section, such as Settings › Application |
| `POST /settings/<owner>` | Save that section: `values[<name>]` per field; `return_to=section` comes back to the one section |

Each field is checked by its type:

- a required field cannot be empty;
- a whole number must be whole and within `min`/`max`;
- text must fit `maxLength`;
- a choice must be in the list.

A refused save writes nothing: the page shows each reason under its field and keeps what was
typed. Fields are trimmed; a save that changes nothing says so and writes nothing.

The page is Bootstrap 5 markup. It runs no script and has no inline handlers, so it works under a
`script-src 'self'` Content-Security-Policy. An application can restyle it by overriding
`templates/plugin/RockSoftware/Settings/Settings/index.php` and `section.php`.

**Breadcrumb.** The page sets `breadcrumbTitle` (the section's title, or "Settings") and the page
title.

- **An application whose layout builds the breadcrumb** lists the page in its own menu; the page
  then shows none of its own.
- **An application whose pages write their breadcrumb in the template** sets `breadcrumbParent`. A
  section's page then shows "parent › Settings › Application".

### Who changed what

The plugin takes who saved from the request's `identity` attribute, as the application's
authentication leaves it. It needs no users table. It keeps:

- the identity's identifier: `getIdentifier()`, else its `id`;
- a name to show: its `name`, else its `email`, else `#` and the identifier.

The history therefore reads the same after the person is gone.

`Settings::EVENT_CHANGED` (`RockSoftwareSettings.changed`) fires on the global event manager after
every save that changed something, for the application's audit log. Its data is `keys` (the keys
changed) and `identity`.

## Writing from code

```php
Settings::save('reports', ['title' => 'Monthly report', 'rows_per_page' => '25'], $identity);  // SaveResult
Settings::set('reports.rows_per_page', 25);           // one setting; throws InvalidSettingValueException
Settings::isStored('reports.rows_per_page');          // whether a value is stored, rather than the default applying
Settings::changes('reports.rows_per_page');           // the history, newest first
Settings::lastChange('reports');                      // the section's latest stored change
```

`Settings::save()` checks the values the way the page does and returns a `SaveResult`, with
`isSaved()`, `errors` (per field) and `changedKeys`.

## What is stored

- `rock_software_settings_values`: one row per stored setting. It holds `setting_key`, `value`
  (JSON), `changed_by_identifier`, `changed_by_name` and `modified`. A setting without a row has its
  default.
- `rock_software_settings_changes`: one row per changed setting per save. It holds `setting_key`,
  `old_value` and `new_value` (JSON), who and `created`. An `old_value` of SQL `NULL` means nothing
  was stored before, so the default applied.

The first save of a section stores every field in it, defaults included. "Last changed" then says
who confirmed them.

## Testing an application that uses it

The plugin ships its fixtures, autoloaded, under `RockSoftware\Settings\TestSuite\Fixture`. Both
are empty, as on a fresh installation.

```php
use RockSoftware\Settings\TestSuite\Fixture\SettingsFixtures;

protected array $fixtures = [...SettingsFixtures::ALL, 'app.Users'];
```

A test that declares settings of its own calls `Settings::resetDeclarations()` in `tearDown()`,
which forgets every declaration but the plugin's own. Give the tests an `Array` cache for the
`default` config, or call `Settings::clearCache()` between tests.

## Developing the plugin

```bash
composer install
vendor/bin/phpunit
composer cs-check
composer stan
```

Tests run on SQLite. CI runs them on PHP 8.3 and 8.4, with the coding standard and PHPStan.

## License

Proprietary.
