<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Test\TestCase;

use ArrayObject;
use Cake\Cache\Cache;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RockSoftware\Settings\InvalidSettingValueException;
use RockSoftware\Settings\SettingDeclaration;
use RockSoftware\Settings\Settings;
use RockSoftware\Settings\SettingsPlugin;
use RockSoftware\Settings\TestSuite\Fixture\SettingsFixtures;

class SettingsTest extends TestCase
{
    protected array $fixtures = SettingsFixtures::ALL;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::resetDeclarations();
        Settings::clearCache();
    }

    protected function tearDown(): void
    {
        EventManager::instance()->off(Settings::EVENT_CHANGED);
        Settings::resetDeclarations();
        Settings::clearCache();
        parent::tearDown();
    }

    public function testTheApplicationSectionIsBuiltIn(): void
    {
        $this->assertSame(['application'], array_map(fn($section) => $section->owner, Settings::sections()));
        $this->assertSame('Application', Settings::section('application')?->title());
        $this->assertSame(
            [SettingsPlugin::APPLICATION_NAME, SettingsPlugin::APPLICATION_ORGANIZATION_NAME],
            array_keys(Settings::declarations('application')),
        );
        $this->assertTrue(Settings::declaration('application.name')->isRequired);
        $this->assertFalse(Settings::declaration('application.organization_name')->isRequired);
    }

    public function testAFreshInstallationReadsTheDeclaredDefaults(): void
    {
        $this->assertSame('Application', Settings::get('application.name'));
        $this->assertSame('', Settings::get('application.organization_name'));
        $this->assertFalse(Settings::isStored('application.name'));
    }

    public function testReadingAnUndeclaredSettingIsAMistake(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Settings::get('application.colour');
    }

    public function testAnOwnerDeclaresItsSettingsAndSection(): void
    {
        Settings::declareSection('reports', 'Reports', 'How reports look.', 50);
        Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', 'Rows per page', default: 50));
        Settings::declare(SettingDeclaration::yesNo('reports.is_footer_shown', 'Show the footer', default: true));
        Settings::declare(SettingDeclaration::text('beta.code', 'Code'));

        $this->assertSame(
            ['application', 'reports', 'beta'],
            array_map(fn($section) => $section->owner, Settings::sections()),
        );
        $this->assertSame('Beta', Settings::section('beta')?->title());
        $this->assertSame('How reports look.', Settings::section('reports')?->description());
        $this->assertSame(50, Settings::get('reports.rows_per_page'));
        $this->assertTrue(Settings::get('reports.is_footer_shown'));
        $this->assertNull(Settings::section('nobody'));
    }

    public function testSavingStoresTheValueAndItsHistory(): void
    {
        $identity = new ArrayObject(['id' => 7, 'name' => 'Pat Example', 'email' => 'pat@example.test']);

        $result = Settings::save('application', ['name' => '  Example App ', 'organization_name' => ''], $identity);

        $this->assertTrue($result->isSaved());
        $this->assertSame(['application.name', 'application.organization_name'], $result->changedKeys);
        $this->assertSame('Example App', Settings::get('application.name'));
        $this->assertTrue(Settings::isStored('application.organization_name'));

        $value = Settings::lastChange('application');
        $this->assertSame('7', $value?->changed_by_identifier);
        $this->assertSame('Pat Example', $value->changed_by_name);

        $changes = Settings::changes('application.name');
        $this->assertCount(1, $changes);
        $this->assertNull($changes[0]->old_value, 'Nothing was stored before: the default applied.');
        $this->assertSame('"Example App"', $changes[0]->new_value);
        $this->assertSame('7', $changes[0]->changed_by_identifier);
        $this->assertSame('Pat Example', $changes[0]->changed_by_name);
    }

    public function testEverySaveThatChangesAValueAddsAChange(): void
    {
        Settings::save('application', ['name' => 'Example App']);
        Settings::save('application', ['name' => 'Example App']);
        $result = Settings::save('application', ['name' => 'Example Portal']);

        $this->assertSame(['application.name'], $result->changedKeys);
        $changes = Settings::changes('application.name');
        $this->assertCount(2, $changes);
        $this->assertSame('"Example App"', $changes[0]->old_value);
        $this->assertSame('"Example Portal"', $changes[0]->new_value);
        $this->assertSame(1, TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingValues')->find()->count());
    }

    public function testASaveWithoutChangesWritesNothing(): void
    {
        Settings::save('application', ['name' => 'Example App']);

        $result = Settings::save('application', ['name' => 'Example App']);

        $this->assertTrue($result->isSaved());
        $this->assertFalse($result->hasChanges());
        $this->assertCount(1, Settings::changes('application.name'));
    }

    public function testARefusedFieldWritesNothing(): void
    {
        $result = Settings::save('application', ['name' => '   ', 'organization_name' => 'Example Ltd']);

        $this->assertFalse($result->isSaved());
        $this->assertSame(['name' => 'This is required.'], $result->errors);
        $this->assertFalse(Settings::isStored('application.organization_name'));
        $this->assertSame([], Settings::changes('application.organization_name'));
    }

    public function testAFieldLeftOutIsKept(): void
    {
        Settings::save('application', ['name' => 'Example App', 'organization_name' => 'Example Ltd']);

        Settings::save('application', ['name' => 'Example Portal']);

        $this->assertSame('Example Ltd', Settings::get('application.organization_name'));
    }

    public function testASaveOnlyTouchesItsOwnSection(): void
    {
        Settings::declare(SettingDeclaration::text('reports.title', 'Title', default: 'Reports'));

        Settings::save('application', ['name' => 'Example App', 'title' => 'Other']);

        $this->assertSame('Reports', Settings::get('reports.title'));
    }

    public function testEveryTypeIsStoredAndReadAsItsPhpType(): void
    {
        Settings::declare(SettingDeclaration::longText('reports.footer', 'Footer'));
        Settings::declare(SettingDeclaration::yesNo('reports.is_footer_shown', 'Show the footer'));
        Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', 'Rows', default: 50, min: 10));
        Settings::declare(SettingDeclaration::choice(
            'reports.paper',
            'Paper',
            ['a4' => 'A4', 'letter' => fn(): string => 'Letter'],
            default: 'a4',
            isRequired: true,
        ));

        $result = Settings::save('reports', [
            'footer' => "Line one\r\nLine two",
            'is_footer_shown' => '1',
            'rows_per_page' => '25',
            'paper' => 'letter',
        ]);

        $this->assertTrue($result->isSaved());
        Settings::clearCache();
        $this->assertSame("Line one\nLine two", Settings::get('reports.footer'));
        $this->assertTrue(Settings::get('reports.is_footer_shown'));
        $this->assertSame(25, Settings::get('reports.rows_per_page'));
        $this->assertSame('letter', Settings::get('reports.paper'));
        $this->assertSame(['a4' => 'A4', 'letter' => 'Letter'], Settings::declaration('reports.paper')->choices());
    }

    public function testEachTypeRefusesWhatItCannotHold(): void
    {
        Settings::declare(SettingDeclaration::wholeNumber('reports.rows_per_page', 'Rows', min: 10, max: 100));
        Settings::declare(SettingDeclaration::wholeNumber('reports.copies', 'Copies', isRequired: true));
        Settings::declare(SettingDeclaration::choice('reports.paper', 'Paper', ['a4' => 'A4']));
        Settings::declare(SettingDeclaration::yesNo('reports.is_footer_shown', 'Show the footer'));
        Settings::declare(SettingDeclaration::text('reports.title', 'Title', maxLength: 5));

        $result = Settings::save('reports', [
            'rows_per_page' => '2.5',
            'copies' => '',
            'paper' => 'a3',
            'is_footer_shown' => 'maybe',
            'title' => 'Too long',
        ]);

        $this->assertSame([
            'rows_per_page' => 'Enter a whole number.',
            'copies' => 'This is required.',
            'paper' => 'Choose one of the list.',
            'is_footer_shown' => 'Choose yes or no.',
            'title' => 'At most 5 characters.',
        ], $result->errors);
        $this->assertSame(
            ['rows_per_page' => 'At least 10.'],
            Settings::save('reports', ['rows_per_page' => '9'])->errors,
        );
        $this->assertSame(
            ['rows_per_page' => 'At most 100.'],
            Settings::save('reports', ['rows_per_page' => '101'])->errors,
        );
        $this->assertTrue(Settings::save('reports', ['rows_per_page' => '', 'paper' => ''])->isSaved());
        $this->assertNull(Settings::get('reports.rows_per_page'));
        $this->assertNull(Settings::get('reports.paper'));
    }

    public function testADeclarationIsCheckedWhenMade(): void
    {
        foreach (
            [
                fn() => SettingDeclaration::text('Application.Name', 'Name'),
                fn() => SettingDeclaration::text('name', 'Name'),
                fn() => SettingDeclaration::choice('reports.paper', 'Paper', []),
                fn() => SettingDeclaration::choice('reports.paper', 'Paper', ['a4' => 'A4'], default: 'a3'),
            ] as $make
        ) {
            try {
                $make();
                $this->fail('A wrong declaration was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAStoredValueTheDeclarationNoLongerAllowsReadsAsTheDefault(): void
    {
        Settings::declare(SettingDeclaration::choice('reports.paper', 'Paper', ['a4' => 'A4', 'a3' => 'A3'], default: 'a4'));
        Settings::save('reports', ['paper' => 'a3']);

        Settings::declare(SettingDeclaration::choice('reports.paper', 'Paper', ['a4' => 'A4'], default: 'a4'));

        $this->assertSame('a4', Settings::get('reports.paper'));
    }

    public function testValuesAreReadFromTheCacheUntilASaveClearsIt(): void
    {
        Settings::save('application', ['name' => 'Example App']);
        $this->assertSame('Example App', Settings::get('application.name'));

        // Changed behind the plugin's back: the cached value still answers.
        $values = TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingValues');
        $values->updateAll(['value' => '"Changed Directly"'], ['setting_key' => 'application.name']);
        $this->assertSame('Example App', Settings::get('application.name'));
        $this->assertIsArray(Cache::read(Settings::CACHE_KEY, 'default'));

        Settings::save('application', ['organization_name' => 'Example Ltd']);

        $this->assertNull(Cache::read(Settings::CACHE_KEY, 'default'));
        $this->assertSame('Changed Directly', Settings::get('application.name'));
        $this->assertSame('Example Ltd', Settings::get('application.organization_name'));
    }

    public function testOneQueryReadsEveryValue(): void
    {
        Settings::save('application', ['name' => 'Example App', 'organization_name' => 'Example Ltd']);
        Settings::get('application.name');

        // Without the table, only the cache can answer.
        Settings::clearCache();
        Settings::get('application.name');
        $connection = TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingValues')->getConnection();
        $connection->execute('ALTER TABLE rock_software_settings_values RENAME TO rss_values_away');
        try {
            $this->assertSame('Example App', Settings::get('application.name'));
            $this->assertSame('Example Ltd', Settings::get('application.organization_name'));
        } finally {
            $connection->execute('ALTER TABLE rss_values_away RENAME TO rock_software_settings_values');
        }
    }

    public function testBeforeTheMigrationEverySettingHasItsDefault(): void
    {
        $connection = TableRegistry::getTableLocator()->get('RockSoftware/Settings.SettingValues')->getConnection();
        $connection->execute('ALTER TABLE rock_software_settings_values RENAME TO rss_values_away');
        try {
            $this->assertSame('Application', Settings::get('application.name'));
        } finally {
            $connection->execute('ALTER TABLE rss_values_away RENAME TO rock_software_settings_values');
        }
    }

    public function testSetFromCodeIsCheckedAndRecorded(): void
    {
        $this->assertTrue(Settings::set('application.name', 'Example App'));
        $this->assertFalse(Settings::set('application.name', 'Example App'));
        $this->assertSame('Example App', Settings::get('application.name'));
        $this->assertNull(Settings::changes('application.name')[0]->changed_by_name);

        $this->expectException(InvalidSettingValueException::class);
        Settings::set('application.name', '');
    }

    public function testAChangeIsAnnouncedForTheAuditLog(): void
    {
        $events = [];
        EventManager::instance()->on(Settings::EVENT_CHANGED, function (EventInterface $event) use (&$events): void {
            $events[] = $event;
        });
        $identity = new ArrayObject(['id' => 7, 'email' => 'pat@example.test']);

        Settings::save('application', ['name' => 'Example App'], $identity);
        Settings::save('application', ['name' => 'Example App'], $identity);

        $this->assertCount(1, $events);
        $this->assertSame(['application.name'], $events[0]->getData('keys'));
        $this->assertSame($identity, $events[0]->getData('identity'));
        $this->assertSame('pat@example.test', Settings::lastChange('application')?->changed_by_name);
    }
}
