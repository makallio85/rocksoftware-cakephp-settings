<?php
declare(strict_types=1);

namespace RockSoftware\Settings\TestSuite\Fixture;

/**
 * Every fixture an application's test needs for the plugin's tables, for its
 * `$fixtures`: `[...SettingsFixtures::ALL, 'app.Users']`.
 */
final class SettingsFixtures
{
    /**
     * @var list<class-string<\Cake\TestSuite\Fixture\TestFixture>>
     */
    public const ALL = [
        SettingValuesFixture::class,
        SettingChangesFixture::class,
    ];
}
