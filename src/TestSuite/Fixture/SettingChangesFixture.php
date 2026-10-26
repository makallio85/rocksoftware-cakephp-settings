<?php
declare(strict_types=1);

namespace RockSoftware\Settings\TestSuite\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Empty: a fresh installation has no change history.
 */
class SettingChangesFixture extends TestFixture
{
    public string $tableAlias = 'RockSoftware/Settings.SettingChanges';

    /**
     * @var array<array<string, mixed>>
     */
    public array $records = [];
}
