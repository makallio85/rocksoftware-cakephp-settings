<?php
declare(strict_types=1);

namespace RockSoftware\Settings;

/**
 * What kind of value a setting holds, and so how the settings page asks for
 * it and how a submitted value is checked. A code-level discriminator of
 * declarations; it is never stored.
 */
enum SettingType: string
{
    /** One line of text: a `string`. */
    case Text = 'text';

    /** Several lines of text: a `string`. */
    case LongText = 'long_text';

    /** Yes or no: a `bool`. */
    case YesNo = 'yes_no';

    /** A whole number: an `int`, or `null` when an optional one is left empty. */
    case WholeNumber = 'whole_number';

    /** One value from a list: a `string` key of the declared choices, or `null` when an optional one is left empty. */
    case Choice = 'choice';

    /** Email addresses, one per line: a `list<string>`, empty when none. */
    case EmailAddresses = 'email_addresses';
}
