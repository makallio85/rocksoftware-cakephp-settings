<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Model\Entity;

use Cake\ORM\Entity;

/**
 * One stored setting.
 *
 * @property int $id
 * @property string $setting_key `<owner>.<name>`
 * @property string $value The value as JSON.
 * @property string|null $changed_by_identifier
 * @property string|null $changed_by_name
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified When it last changed.
 */
class SettingValue extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'setting_key' => true,
        'value' => true,
        'changed_by_identifier' => true,
        'changed_by_name' => true,
    ];
}
