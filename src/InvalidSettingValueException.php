<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

use InvalidArgumentException;

/**
 * A value a setting refuses. The message is for the person who entered it,
 * in their language.
 */
class InvalidSettingValueException extends InvalidArgumentException
{
}
