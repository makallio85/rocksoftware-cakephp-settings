<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Model\Table;

use Cake\ORM\Table;
use RockSoftware\Settings\Model\Entity\SettingChange;

/**
 * Every change of every setting: written by `RockSoftware\Settings\Settings`
 * on each save, never changed afterwards.
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
 */
class SettingChangesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rock_software_settings_changes');
        $this->setPrimaryKey('id');
        $this->setEntityClass(SettingChange::class);
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }
}
