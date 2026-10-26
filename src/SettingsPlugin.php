<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\I18n\I18n;
use Cake\I18n\Package;
use Cake\I18n\Parser\PoFileParser;
use Cake\Routing\RouteBuilder;

/**
 * One settings store and one settings page for the application. Owners (this
 * plugin, other plugins, the application) declare their settings in code
 * (`Settings::declare()`); values live in `rock_software_settings_values`,
 * every change in `rock_software_settings_changes`; `Settings::get()` reads
 * them. Loading the plugin, running its migration and giving `access` is
 * enough.
 *
 * Routes, under `RockSoftwareSettings.path` (`/settings`):
 *
 * - `GET /settings` — every section
 * - `GET /settings/<owner>` — one section, such as `/settings/application`
 * - `POST /settings/<owner>` — save that section
 *
 * Built-in settings, section Application:
 *
 * - `application.name` (required): the application's name.
 * - `application.organization_name`: the line under the name.
 */
class SettingsPlugin extends BasePlugin
{
    /** The plugin's name, for `addPlugin()` and URL arrays. */
    public const NAME = 'RockSoftware/Settings';

    /** The settings key under `Configure`. */
    public const CONFIG_KEY = 'RockSoftwareSettings';

    /** The translation domain of every text the plugin shows. */
    public const TRANSLATION_DOMAIN = 'rock_software_settings';

    /** The owner of the built-in settings. */
    public const OWNER_APPLICATION = 'application';

    /** The application's name. */
    public const APPLICATION_NAME = 'application.name';

    /** The line under the application's name. */
    public const APPLICATION_ORGANIZATION_NAME = 'application.organization_name';

    /**
     * Every setting of the plugin itself and its default. An application
     * overrides them in `Configure::write('RockSoftwareSettings', [...])`
     * (usually `config/app.php`). None of them is a setting on the page.
     *
     * - `access`: who may open and save the settings page; a callable
     *   receiving the identity and the request and returning `true`. Null
     *   means nobody.
     * - `path`: where the page is.
     * - `cache`: the cache config holding the stored values.
     * - `breadcrumbParent`: the page above the settings page in an
     *   application whose pages write their breadcrumb in the template: a
     *   title, or `['title' => …, 'url' => …]`. Null for none.
     * - `timezone`: the time zone of "Last changed"; null for
     *   `Ui.displayTimezone`, else `App.displayTimezone`, else Europe/Helsinki.
     * - `locale`: the language of the plugin's texts when it differs from the
     *   application's locale (e.g. `fi`); null follows the application.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'access' => null,
        'path' => '/settings',
        'cache' => 'default',
        'breadcrumbParent' => null,
        'timezone' => null,
        'locale' => null,
    ];

    protected ?string $name = self::NAME;

    protected bool $middlewareEnabled = false;

    protected bool $consoleEnabled = false;

    protected bool $bootstrapEnabled = true;

    /**
     * Fills in every setting the application left out, registers the texts
     * and declares the built-in settings.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application.
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        self::applyDefaults();
        self::loadTranslations();
        self::declareApplicationSettings();
    }

    /**
     * @return void
     */
    public static function applyDefaults(): void
    {
        Configure::write(
            self::CONFIG_KEY,
            array_replace(self::DEFAULTS, (array)Configure::read(self::CONFIG_KEY, [])),
        );
    }

    /**
     * One setting of the plugin, or its default when the application has not set it.
     *
     * @param string $key A key of `DEFAULTS`.
     * @return mixed
     */
    public static function setting(string $key): mixed
    {
        $settings = (array)Configure::read(self::CONFIG_KEY, []);

        return array_key_exists($key, $settings) ? $settings[$key] : (self::DEFAULTS[$key] ?? null);
    }

    /**
     * The section Application: the application's name and the line under it.
     *
     * @return void
     */
    public static function declareApplicationSettings(): void
    {
        Settings::declareSection(
            self::OWNER_APPLICATION,
            fn(): string => __d('rock_software_settings', 'Application'),
            fn(): string => __d(
                'rock_software_settings',
                'How this application names itself: in the top bar, on the sign-in page and in the browser tab.',
            ),
            0,
        );
        Settings::declare(SettingDeclaration::text(
            self::APPLICATION_NAME,
            label: fn(): string => __d('rock_software_settings', 'Application name'),
            help: fn(): string => __d(
                'rock_software_settings',
                'Shown in the top bar, on the sign-in card and after every page title.',
            ),
            default: 'Application',
            isRequired: true,
            maxLength: 100,
        ));
        Settings::declare(SettingDeclaration::text(
            self::APPLICATION_ORGANIZATION_NAME,
            label: fn(): string => __d('rock_software_settings', 'Line under the name'),
            help: fn(): string => __d(
                'rock_software_settings',
                'Shown under the name on the sign-in card only. Leave it empty when the name already says it.',
            ),
            maxLength: 100,
        ));
    }

    /**
     * The time zone "Last changed" is shown in.
     *
     * @return string
     */
    public static function displayTimezone(): string
    {
        $zones = [
            self::setting('timezone'),
            Configure::read('Ui.displayTimezone'),
            Configure::read('App.displayTimezone'),
        ];
        foreach ($zones as $zone) {
            if (is_string($zone) && $zone !== '') {
                return $zone;
            }
        }

        return 'Europe/Helsinki';
    }

    /**
     * Registers the plugin's texts (domain `rock_software_settings`) from its
     * own `resources/locales`. CakePHP finds a plugin's catalogue by domain
     * name only for plugins without a vendor prefix, so the plugin registers it.
     *
     * @return void
     */
    public static function loadTranslations(): void
    {
        $path = dirname(__DIR__) . DS . 'resources' . DS . 'locales' . DS;
        I18n::config(self::TRANSLATION_DOMAIN, function (string $domain, string $locale) use ($path): Package {
            $fixed = self::setting('locale');
            $locale = is_string($fixed) && $fixed !== '' ? $fixed : $locale;
            $messages = [];
            foreach ([$locale, substr($locale, 0, 2)] as $folder) {
                $file = $path . $folder . DS . self::TRANSLATION_DOMAIN . '.po';
                if (is_file($file)) {
                    $messages = (new PoFileParser())->parse($file);
                    break;
                }
            }

            return new Package('default', null, $messages);
        });
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routes The route builder.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            self::NAME,
            ['path' => (string)self::setting('path')],
            function (RouteBuilder $builder): void {
                $builder->connect('/', ['controller' => 'Settings', 'action' => 'index'])
                    ->setMethods(['GET']);
                $builder->connect('/{owner}', ['controller' => 'Settings', 'action' => 'section'])
                    ->setPatterns(['owner' => '[a-z][a-z0-9_]*'])
                    ->setPass(['owner'])
                    ->setMethods(['GET']);
                $builder->connect('/{owner}', ['controller' => 'Settings', 'action' => 'save'])
                    ->setPatterns(['owner' => '[a-z][a-z0-9_]*'])
                    ->setPass(['owner'])
                    ->setMethods(['POST']);
            },
        );
    }
}
