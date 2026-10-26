<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

use Cake\Cache\Cache;
use Cake\Database\Exception\DatabaseException;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Closure;
use InvalidArgumentException;
use PDOException;
use RockSoftware\Settings\Model\Entity\SettingValue;
use RockSoftware\Settings\Model\Table\SettingChangesTable;
use RockSoftware\Settings\Model\Table\SettingValuesTable;
use RockSoftware\Settings\Service\Actor;

/**
 * The application's settings: declared in code by their owners, stored in
 * the database, read from a cache.
 *
 *     Settings::get('application.name');   // the stored value, else the declared default
 *
 * Owners (this plugin, another plugin, the application) declare their
 * settings in their `bootstrap()`:
 *
 *     Settings::declareSection('reports', fn(): string => __d('reports', 'Reports'));
 *     Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', label: …, default: 50, min: 10));
 *
 * Every stored value is read with one query and kept in the cache config
 * `RockSoftwareSettings.cache` (`default`); within a process the values are
 * kept for `MEMO_SECONDS` more, so `get()` is cheap enough for every
 * request. A save clears both.
 */
final class Settings
{
    /** Fired after a save that changed something: `keys`, `identity`. */
    public const EVENT_CHANGED = 'RockSoftwareSettings.changed';

    /** The cache key holding every stored value. */
    public const CACHE_KEY = 'rock_software_settings_values';

    /** How long a process keeps the values it read before it reads the cache again. */
    public const MEMO_SECONDS = 5;

    /**
     * @var array<string, \RockSoftware\Settings\SettingDeclaration>
     */
    private static array $declarations = [];

    /**
     * @var array<string, \RockSoftware\Settings\SettingSection>
     */
    private static array $sections = [];

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    private static float $memoAt = 0.0;

    /**
     * Declares a setting, or replaces the declaration of its key.
     *
     * @param \RockSoftware\Settings\SettingDeclaration $declaration The setting.
     * @return void
     */
    public static function declare(SettingDeclaration $declaration): void
    {
        self::$declarations[$declaration->key] = $declaration;
    }

    /**
     * Declares an owner's section of the settings page, or replaces it.
     *
     * @param string $owner The owner, the first part of its settings' keys.
     * @param \Closure|string $title The title; a closure for a translated one.
     * @param \Closure|string|null $description The text under the title.
     * @param int $order Lower comes first; the built-in Application section is 0.
     * @return void
     */
    public static function declareSection(
        string $owner,
        Closure|string $title,
        Closure|string|null $description = null,
        int $order = 100,
    ): void {
        self::$sections[$owner] = new SettingSection($owner, $title, $description, $order);
    }

    /**
     * @param string $key `<owner>.<name>`.
     * @return \RockSoftware\Settings\SettingDeclaration
     * @throws \InvalidArgumentException When no setting has this key.
     */
    public static function declaration(string $key): SettingDeclaration
    {
        if (!isset(self::$declarations[$key])) {
            throw new InvalidArgumentException(sprintf('No setting "%s" is declared.', $key));
        }

        return self::$declarations[$key];
    }

    /**
     * The declared settings, in the order declared; one owner's only when given.
     *
     * @param string|null $owner An owner.
     * @return array<string, \RockSoftware\Settings\SettingDeclaration>
     */
    public static function declarations(?string $owner = null): array
    {
        if ($owner === null) {
            return self::$declarations;
        }

        return array_filter(
            self::$declarations,
            fn(SettingDeclaration $declaration): bool => $declaration->owner() === $owner,
        );
    }

    /**
     * The sections of the settings page: one per owner with a declared
     * setting, by `order`, then by title.
     *
     * @return list<\RockSoftware\Settings\SettingSection>
     */
    public static function sections(): array
    {
        $sections = [];
        foreach (self::$declarations as $declaration) {
            $owner = $declaration->owner();
            $sections[$owner] ??= self::section($owner);
        }
        $sections = array_values(array_filter($sections));
        usort(
            $sections,
            fn(SettingSection $a, SettingSection $b): int => [$a->order, $a->title()] <=> [$b->order, $b->title()],
        );

        return $sections;
    }

    /**
     * An owner's section, or null when the owner has declared no setting.
     *
     * @param string $owner The owner.
     * @return \RockSoftware\Settings\SettingSection|null
     */
    public static function section(string $owner): ?SettingSection
    {
        if (self::declarations($owner) === []) {
            return null;
        }

        return self::$sections[$owner]
            ?? new SettingSection($owner, ucfirst(str_replace('_', ' ', $owner)));
    }

    /**
     * A setting's value: the stored one, else the declared default.
     *
     * @param string $key `<owner>.<name>`.
     * @return mixed
     * @throws \InvalidArgumentException When no setting has this key.
     * @phpstan-impure
     */
    public static function get(string $key): mixed
    {
        $declaration = self::declaration($key);
        $values = self::values();
        if (array_key_exists($key, $values) && $declaration->isValueOfType($values[$key])) {
            return $values[$key];
        }

        return $declaration->default;
    }

    /**
     * Whether a value is stored for a setting, rather than its default applying.
     *
     * @param string $key `<owner>.<name>`.
     * @return bool
     * @phpstan-impure
     */
    public static function isStored(string $key): bool
    {
        self::declaration($key);

        return array_key_exists($key, self::values());
    }

    /**
     * Saves an owner's section, as its form on the settings page does: each
     * field by its name (`name` of `application.name`). A field left out is
     * kept as it is. When any field is refused, nothing is written.
     *
     * A setting changes when its value differs from the stored one, or when
     * nothing was stored yet. Each change updates its row and adds a row to
     * the change history; then the cache is cleared and `EVENT_CHANGED` fired.
     *
     * @param string $owner The owner.
     * @param array<string, mixed> $input Each field's submitted value.
     * @param mixed $identity Who saves: the request's `identity`, or null for code.
     * @return \RockSoftware\Settings\SaveResult
     */
    public static function save(string $owner, array $input, mixed $identity = null): SaveResult
    {
        $values = [];
        $errors = [];
        foreach (self::declarations($owner) as $key => $declaration) {
            if (!array_key_exists($declaration->name(), $input)) {
                continue;
            }
            try {
                $values[$key] = $declaration->parse($input[$declaration->name()]);
            } catch (InvalidSettingValueException $exception) {
                $errors[$declaration->name()] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            return new SaveResult($errors);
        }

        return new SaveResult([], self::write($values, $identity));
    }

    /**
     * Sets one setting from code, such as an application's migration seeding
     * its name.
     *
     * @param string $key `<owner>.<name>`.
     * @param mixed $value The value, of the setting's type (a form's string is accepted too).
     * @param mixed $identity Who sets it; null for the application itself.
     * @return bool Whether it changed.
     * @throws \RockSoftware\Settings\InvalidSettingValueException When the setting refuses the value.
     */
    public static function set(string $key, mixed $value, mixed $identity = null): bool
    {
        return self::write([$key => self::declaration($key)->parse($value)], $identity) !== [];
    }

    /**
     * The latest stored change of an owner's settings, for "Last changed … by …".
     *
     * @param string $owner The owner.
     * @return \RockSoftware\Settings\Model\Entity\SettingValue|null
     */
    public static function lastChange(string $owner): ?SettingValue
    {
        $keys = array_keys(self::declarations($owner));
        if ($keys === []) {
            return null;
        }

        /** @var \RockSoftware\Settings\Model\Entity\SettingValue|null */
        return self::valuesTable()->find()
            ->where(['setting_key IN' => $keys])
            ->orderBy(['modified' => 'DESC', 'id' => 'DESC'])
            ->first();
    }

    /**
     * A setting's change history, newest first.
     *
     * @param string $key `<owner>.<name>`.
     * @param int $limit The most changes returned.
     * @return list<\RockSoftware\Settings\Model\Entity\SettingChange>
     */
    public static function changes(string $key, int $limit = 50): array
    {
        self::declaration($key);

        /** @var list<\RockSoftware\Settings\Model\Entity\SettingChange> */
        return self::changesTable()->find()
            ->where(['setting_key' => $key])
            ->orderBy(['created' => 'DESC', 'id' => 'DESC'])
            ->limit($limit)
            ->all()
            ->toList();
    }

    /**
     * Forgets the values read, in this process and in the cache.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$memo = null;
        $config = self::cacheConfig();
        if ($config !== null) {
            Cache::delete(self::CACHE_KEY, $config);
        }
    }

    /**
     * Forgets every declaration but the plugin's own. For tests that declare
     * settings of their own.
     *
     * @return void
     */
    public static function resetDeclarations(): void
    {
        self::$declarations = [];
        self::$sections = [];
        SettingsPlugin::declareApplicationSettings();
    }

    /**
     * Writes the values that changed, with their history, in one transaction.
     *
     * @param array<string, mixed> $values Each key and its parsed value.
     * @param mixed $identity Who.
     * @return list<string> The keys that changed.
     */
    private static function write(array $values, mixed $identity): array
    {
        if ($values === []) {
            return [];
        }
        $actor = Actor::fromIdentity($identity);
        $valuesTable = self::valuesTable();
        $changesTable = self::changesTable();

        $changed = $valuesTable->getConnection()->transactional(
            function () use ($values, $actor, $valuesTable, $changesTable): array {
                /** @var array<string, \RockSoftware\Settings\Model\Entity\SettingValue> $rows */
                $rows = $valuesTable->find()
                    ->where(['setting_key IN' => array_keys($values)])
                    ->all()
                    ->indexBy('setting_key')
                    ->toArray();
                $changed = [];
                foreach ($values as $key => $value) {
                    $json = self::encode($value);
                    $row = $rows[$key] ?? null;
                    if ($row !== null && $row->value === $json) {
                        continue;
                    }
                    $oldJson = $row?->value;
                    $row ??= $valuesTable->newEntity(['setting_key' => $key]);
                    $row->patch([
                        'value' => $json,
                        'changed_by_identifier' => $actor->identifier,
                        'changed_by_name' => $actor->name,
                    ]);
                    $valuesTable->saveOrFail($row);
                    $changesTable->saveOrFail($changesTable->newEntity([
                        'setting_key' => $key,
                        'old_value' => $oldJson,
                        'new_value' => $json,
                        'changed_by_identifier' => $actor->identifier,
                        'changed_by_name' => $actor->name,
                    ]));
                    $changed[] = $key;
                }

                return $changed;
            },
        );

        if ($changed !== []) {
            self::clearCache();
            EventManager::instance()->dispatch(new Event(self::EVENT_CHANGED, null, [
                'keys' => $changed,
                'identity' => $identity,
            ]));
        }

        return $changed;
    }

    /**
     * Every stored value by key: from this process's memo, else the cache,
     * else the database (then cached).
     *
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        if (self::$memo !== null && microtime(true) - self::$memoAt < self::MEMO_SECONDS) {
            return self::$memo;
        }
        $config = self::cacheConfig();
        $values = $config === null ? null : Cache::read(self::CACHE_KEY, $config);
        if (!is_array($values)) {
            try {
                $values = [];
                foreach (self::valuesTable()->find()->all() as $row) {
                    /** @var \RockSoftware\Settings\Model\Entity\SettingValue $row */
                    $values[$row->setting_key] = json_decode($row->value, true);
                }
            } catch (DatabaseException | PDOException $exception) {
                // Before the plugin's migration has run (a first deploy), every
                // setting has its default rather than every page failing.
                Log::warning('Settings could not be read, defaults apply: ' . $exception->getMessage());

                return [];
            }
            if ($config !== null) {
                Cache::write(self::CACHE_KEY, $values, $config);
            }
        }
        self::$memo = $values;
        self::$memoAt = microtime(true);

        return $values;
    }

    /**
     * The cache config to use, or null when the application has not configured it.
     *
     * @return string|null
     */
    private static function cacheConfig(): ?string
    {
        $config = SettingsPlugin::setting('cache');

        return is_string($config) && Cache::getConfig($config) !== null ? $config : null;
    }

    /**
     * @param mixed $value A value.
     * @return string
     */
    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return \RockSoftware\Settings\Model\Table\SettingValuesTable
     */
    private static function valuesTable(): SettingValuesTable
    {
        /** @var \RockSoftware\Settings\Model\Table\SettingValuesTable */
        return TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingValues');
    }

    /**
     * @return \RockSoftware\Settings\Model\Table\SettingChangesTable
     */
    private static function changesTable(): SettingChangesTable
    {
        /** @var \RockSoftware\Settings\Model\Table\SettingChangesTable */
        return TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingChanges');
    }
}
