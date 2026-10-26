<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Model\Table;

use Cake\ORM\Table;
use RockSoftware\Settings\Model\Entity\SettingValue;

/**
 * The stored settings, one row per key. Read and written through
 * `RockSoftware\Settings\Settings`, which keeps its cache in step.
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
 */
class SettingValuesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rock_software_settings_values');
        $this->setPrimaryKey('id');
        $this->setEntityClass(SettingValue::class);
        $this->addBehavior('Timestamp');
    }
}
