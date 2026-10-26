<?php
declare(strict_types=1);

namespace RockSoftware\Settings\Model\Entity;

use Cake\ORM\Entity;

/**
 * One changed setting of one save.
 *
 * @property int $id
 * @property string $setting_key `<owner>.<name>`
 * @property string|null $old_value The value before, as JSON; null when nothing was stored (the default applied).
 * @property string $new_value The value after, as JSON.
 * @property string|null $changed_by_identifier
 * @property string|null $changed_by_name
 * @property \Cake\I18n\DateTime $created
 */
class SettingChange extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'setting_key' => true,
        'old_value' => true,
        'new_value' => true,
        'changed_by_identifier' => true,
        'changed_by_name' => true,
    ];
}
