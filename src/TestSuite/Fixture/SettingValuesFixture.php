<?php
declare(strict_types=1);

namespace RockSoftware\Settings\TestSuite\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty: a fresh installation has stored no setting, so every setting has its
 * declared default. Tests save what they need.
 */
class SettingValuesFixture extends TestFixture
{
    public string $tableAlias = 'RockSoftware/Settings.SettingValues';

    /**
     * @var array<array<string, mixed>>
     */
    public array $records = [];
}
